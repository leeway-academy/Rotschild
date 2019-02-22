<?php

namespace App\Controller;

use App\Entity\Bank;
use App\Entity\ExtractoBancario;
use App\Entity\Movimiento;
use App\Entity\RenglonExtracto;
use App\Entity\SaldoBancario;
use Doctrine\Common\Collections\Criteria;
use http\Exception\InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BankController extends AdminController
{
    /**
     * @param Request $request
     * @Route(path="/import/bankSummaries", name="import_bank_summaries")
     */
    public function importSummaries(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->setAttribute('class', 'form-vertical new-form');

        $banks = $this
            ->getDoctrine()
            ->getRepository('App:Bank')
            ->findAll();

        foreach ($banks as $bank) {
            $formBuilder->add(
                'BankSummary_' . $bank->getId(),
                FileType::class,
                [
                    'label' => 'Extracto del banco ' . $bank->getNombre(),
                    'required' => false,
                ]
            );
        }

        $form = $formBuilder
            ->add(
                'Import',
                SubmitType::class,
                [
                    'attr' => [
                        'class' => 'btn btn-primary action-save',
                    ],
                    'label' => 'Importar',
                ]
            )
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            foreach ($form->getData() as $name => $item) {
                if (!is_null($item) && $item->getType() == 'file' ) {
                    if ( in_array($item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'application/octet-stream', 'application/zip'] )) {
                        $parts = preg_split('/_/', $name);
                        $fileName = $parts[0];

                        if ($parts[0] == 'BankSummary') {
                            $bank = $em->getRepository('App:Bank')->find($parts[1]);
                            $fileName .= '_' . $bank->getNombre();
                        }

                        $fileName .= '_' . (new \DateTimeImmutable())->format('d-m-y') . '.' . $item->guessExtension();
                        $item->move($this->getParameter('reports_path'), $fileName);

                        $lines = $this->getExcelReportProcessor()->getBankSummaryTransactions(
                            IOFactory::load($this->getParameter('reports_path') . DIRECTORY_SEPARATOR . $fileName),
                            $bank->getXLSStructure()
                        );

                        $extracto = new ExtractoBancario();
                        $extracto
                            ->setArchivo($fileName)
                            ->setBank($bank)
                            ->setFecha(new \DateTimeImmutable());
                        $em->persist($extracto);
                        foreach ($lines as $k => $line) {
                            $summaryLine = new RenglonExtracto();
                            $summaryLine
                                ->setImporte($line['amount'])
                                ->setFecha($line['date'])
                                ->setConcepto($line['concept'] . ' - ' . $line['extraData'])
                                ->setLinea($k);
                            $em->persist($summaryLine);
                            $extracto->addRenglon($summaryLine);
                        }

                        $em->flush();

                        $this->addFlash(
                            'success',
                            'Extractos importados'
                        );
                    } else {
                        $this->addFlash(
                            'error',
                            'El informe de extracto bancario tiene formato incorrecto'
                        );
                    }
                }
            }
        }

        return $this->render(
            'admin/import_excel_reports.html.twig',
            [
                'form' => $form->createView(),
                'reportName' => 'bank.summaries',
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(path="/bank/matchSummaries", name="match_bank_summaries", options={"expose"=true})
     */
    public function matchSummaries(Request $request)
    {
        $em = $this->getDoctrine();
        $banks = $em->getRepository('App:Bank')->findAll();
        $bank = $request->get('bankId') ? $banks[$request->get('bankId')] : current($banks);
        $filterForm = $this
            ->createFormBuilder(
                null,
                [
                    'allow_extra_fields' => true,
                ]
            )
            ->add(
                'bank',
                ChoiceType::class,
                [
                    'choices' => $banks,
                    'choice_label' => function (Bank $b) {

                        return $b->__toString();
                    },
                    'choice_value' => function (Bank $b = null) {

                        return !empty($b) ? $b->getId() : null;
                    },
                    'data' => $bank,
                ]
            )
            ->add(
                'dateFrom',
                DateType::class,
                [
                    'required' => false,
                ]
            )
            ->add(
                'dateTo',
                DateType::class,
                [
                    'required' => false,
                ]
            )
            ->add(
                'filter',
                SubmitType::class,
                [
                    'label' => 'Filter',
                    'attr' =>
                        [
                            'class' => 'btn btn-primary',
                        ]
                ]
            )
            ->getForm();

        $filterForm->handleRequest($request);

        $dateFrom = $dateTo = null;
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $bank = $filterForm['bank']->getData();
            $dateFrom = $filterForm['dateFrom']->getData();
            $dateTo = $filterForm['dateTo']->getData();
        }

        return $this->render(
            'admin/match_bank_summaries.html.twig',
            [
                'filterForm' => $filterForm->createView(),
                'bank' => $bank,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ]
        );
    }

    /**
     * @Route(name="match_bank_summary_credit_lines", path="/bank/{id}/match_summary_credit_lines")
     * @ParamConverter(name="bank", class="App\Entity\Bank")
     * @param Bank $bank
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function matchCreditSummaryLines(Bank $bank, Request $request)
    {
        $existingTxPefix = 'transaction';
        $newTxPrefix = 'new_tx';

        $pendingCredits = [];

        $projectedCredits = $bank->getCreditosProyectados();

        /**
         * @todo Externalize this concepts into a db table
         */
        $newCreditConcepts = [
            '-1' => 'payment.received',
            '-2' => 'tax.return',
            '-3' => 'bank.comission.return',
            '-4' => 'investment.recovery',
            '-5' => 'check.issued.rejection',
        ];

        $formBuilder = $this->createFormBuilder();
        $dateFrom = $request->get('dateFrom') ? new \DateTimeImmutable($request->get('dateFrom')['date']) : null;
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable($request->get('dateTo')['date']) : null;

        $movimientosRepo = $this->getDoctrine()->getRepository('App:Movimiento');

        foreach ($bank->getExtractos() as $extracto) {
            $lines = $extracto->getRenglones()->filter(function (RenglonExtracto $r) use ($dateFrom, $dateTo) {

                $ret = (empty($dateFrom) || $r->getFecha() >= $dateFrom) && (empty($dateTo) || $r->getFecha() <= $dateTo) && $r->getImporte() > 0;

                return $ret;
            });

            foreach ($lines as $k => $renglon) {
                $transactions = $movimientosRepo->findByWitness($renglon);

                if (!empty($transactions)) {
                    unset($lines[$k]);

                    continue;
                }

                $formBuilder
                    ->add(
                        $existingTxPefix . '_' . $renglon->getId(),
                        ChoiceType::class,
                        [
                            'choices' => $projectedCredits,
                            'choice_value' => function (Movimiento $movimiento = null) {

                                return $movimiento ? $movimiento->getId() : '';
                            },
                            'choice_label' => function (Movimiento $movimiento) {

                                return $movimiento->__toString();
                            },
                            'label' => $renglon->getFecha()->format('d/m/Y') . ': ' . $renglon->getConcepto() . ' ' . $renglon->getImporte(),
                            'required' => false,
                            'multiple' => true,
                        ]
                    )
                    ->add(
                        $newTxPrefix . '_' . $renglon->getId(),
                        ChoiceType::class,
                        [
                            'choices' => $newCreditConcepts,
                            'choice_label' => function ($choiceValue, $key, $value) {

                                return $choiceValue . '';
                            },
                            'choice_value' => function ($v) use ($newCreditConcepts) {

                                return array_search($v, $newCreditConcepts);
                            },
                            'required' => false,
                        ]
                    );
                $pendingCredits[$renglon->getId()] = $renglon;
            }
        }

        $formBuilder->add(
            'submit',
            SubmitType::class,
            [
                'label' => 'Confirm',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]
        );

        $matchingForm = $formBuilder->getForm();
        $matchingForm->handleRequest($request);

        if ($matchingForm->isSubmitted() && $matchingForm->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $renglonExtractoRepository = $em->getRepository('App:RenglonExtracto');
            foreach ($matchingForm->getData() as $name => $transaction) {
                if (!empty($transaction)) {
                    $summaryLineId = last(preg_split('/_/', $name));

                    if ($summaryLine = $renglonExtractoRepository->find($summaryLineId)) {
                        if (substr($name, 0, strlen($existingTxPefix)) == $existingTxPefix) {
                            /**
                             * @todo: Look into the case for multiple associations!
                             */
                            if (is_array($transaction)) {
                                foreach ($transaction as $t) {
                                    $t->setWitness($summaryLine);
                                    $em->persist($t);
                                }
                            }
                        } elseif (substr($name, 0, strlen($newTxPrefix)) == $newTxPrefix) {
                            $newTransaction = new Movimiento();
                            $newTransaction
                                ->setConcepto($this->get('translator')->trans($transaction))
                                ->setFecha($summaryLine->getFecha())
                                ->setImporte($summaryLine->getImporte())
                                ->setWitness($summaryLine)
                                ->setBank($summaryLine->getExtracto()->getBank());
                            $em->persist($newTransaction);
                        }
                    }
                }
            }

            $em->flush();

            return $this->redirectToRoute(
                $request->attributes->get('_route'),
                [
                    'id' => $bank->getId(),
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ]);
        }

        return $this->render(
            'admin/match_bank_summary_credit_lines.html.twig',
            [
                'matchingForm' => $matchingForm->createView(),
                'summaryLines' => $pendingCredits,
                'newCreditConcepts' => $newCreditConcepts,
            ]
        );
    }

    /**
     * @Route(name="match_bank_summary_debit_lines", path="/bank/{id}/match_summary_debit_lines")
     * @ParamConverter(name="bank", class="App\Entity\Bank")
     * @param Bank $bank
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @todo Refactor this method and matchSummaryCreditLines (look for commanlities)
     */
    public function matchDebitSummaryLines(Bank $bank, Request $request)
    {
        $summaryLines = [];

        $projectedDebits = $bank->getDebitosProyectados();

        $mo = [];

        foreach ($projectedDebits as $pd) {
            $mo[ $pd->getConcepto() . ' ( $' . $pd->getImporte().')' ] = $pd->getId();
        }

        $newDebitConcepts = [
            /**
             * @todo Externalize this concepts into a db table
             */
            'bank.comission.charge' => '-4',
            'tax.payed' => '-5',
            'investment.fixed_term' => '-6',
            'fines' => '-7',
            'fees' => '-8',
            'salary.advances' => '-9',
            'transfer.own_account' => '-10',
            'expenses.shareholders' => '-11',
            'check.applied.rejection' => '-12',
        ];
        $matchingOptions = [
            'Debitos proyectados' => $mo,
            'Nuevo Debito' => $newDebitConcepts,
        ];

        $formBuilder = $this->createFormBuilder();
        $dateFrom = $request->get('dateFrom') ? new \DateTimeImmutable($request->get('dateFrom')['date']) : null;
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable($request->get('dateTo')['date']) : null;

        $movimientosRepo = $this->getDoctrine()->getRepository('App:Movimiento');

        foreach ($bank->getExtractos() as $extracto) {
            $lines = $extracto->getRenglones()->filter(function (RenglonExtracto $r) use ($dateFrom, $dateTo) {

                $ret = (empty($dateFrom) || $r->getFecha() >= $dateFrom) && (empty($dateTo) || $r->getFecha() <= $dateTo) && $r->getImporte() < 0;

                return $ret;
            });

            foreach ($lines as $k => $renglon) {
                $transactions = $movimientosRepo->findByWitness($renglon);

                if (!empty($transactions)) {
                    unset($lines[$k]);

                    continue;
                }

                $formBuilder
                    ->add(
                        'summaryLine_' . $renglon->getId(),
                        ChoiceType::class,
                        [
                            'choices' => $matchingOptions,
                            'required' => false,
                        ]
                    );
                $summaryLines[$renglon->getId()] = $renglon;
            }
        }

        $formBuilder->add(
            'submit',
            SubmitType::class,
            [
                'label' => 'Confirm',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]
        );

        $matchingForm = $formBuilder->getForm();
        $matchingForm->handleRequest($request);

        if ($matchingForm->isSubmitted() && $matchingForm->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $renglonExtractoRepository = $em->getRepository('App:RenglonExtracto');
            foreach ($matchingForm->getData() as $name => $id) {
                if (!empty($id)) {
                    $summaryLineId = last(preg_split('/_/', $name));

                    if ($summaryLine = $renglonExtractoRepository->find($summaryLineId)) {
                        if ($id > 0) {
                            $transaction = $em->getRepository('App:Movimiento')->find($id);
                        } else {
                            $transaction = new Movimiento();
                            $transaction
                                ->setConcepto($this->get('translator')->trans( array_search( $id, $newDebitConcepts ) ) )
                                ->setFecha($summaryLine->getFecha())
                                ->setImporte($summaryLine->getImporte())
                                ->setBank($summaryLine->getExtracto()->getBank());
                        }

                        $transaction->setWitness($summaryLine);
                        $em->persist($transaction);
                    }
                }
            }

            $em->flush();

            return $this->redirectToRoute(
                $request->attributes->get('_route'),
                [
                    'id' => $bank->getId(),
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ]);
        }

        return $this->render(
            'admin/match_bank_summary_debit_lines.html.twig',
            [
                'matchingForm' => $matchingForm->createView(),
                'summaryLines' => $summaryLines
            ]
        );
    }

    private function calculatePastBalances( \DatePeriod $past, array $banks ) : array
    {
        $balances = [];

        foreach ($past as $pastDate) {
            if (count($banks) == 1) {
                $bank = current($banks);
                $pastBalance = $bank->getBalance($pastDate);
                if ( empty( $pastBalance ) ) {
                    $pastBalance = new SaldoBancario();
                    $pastBalance->setValor( 0 );
                } else {
                    $pastBalance = clone $pastBalance;
                }
                $pastBalance->setFecha( $pastDate );
                $balances[] = $pastBalance;
            } else {
                $totalBalance = 0;

                foreach ($banks as $bank) {
                    $balance = $bank->getBalance($pastDate);
                    $totalBalance += $balance ? $balance->getValor() : 0;
                }

                $consolidatedBalance = new SaldoBancario();
                $consolidatedBalance
                    ->setValor( $totalBalance )
                    ->setFecha( $pastDate )
                ;

                $balances[] = clone $consolidatedBalance;
            }
        }

        return $balances;
    }

    /**
     * @param $dateFrom
     * @param $dateTo
     * @param array $banks
     * @return array
     * @throws \Exception
     */
    private function calculateBalances(\DateTimeInterface $dateFrom, \DateTimeInterface $dateTo, array $banks): array
    {
        $dateFrom = $dateFrom instanceof \DateTimeImmutable ? $dateFrom : \DateTimeImmutable::createFromMutable($dateFrom);

        $oneDay = new \DateInterval('P1D');
        $today = new \DateTimeImmutable();

        $past = new \DatePeriod($dateFrom, $oneDay, $today <= $dateTo ? $today->sub($oneDay) : $dateTo->add($oneDay));

        $balances = $this->calculatePastBalances( $past, $banks );

        $criteria = new Criteria();
        $criteria
            ->where(Criteria::expr()->gte('fecha', $dateFrom))
            ->andWhere(Criteria::expr()->lte('fecha', $dateTo))
            ->andWhere($this->getDoctrine()->getRepository('App:Movimiento')->getProjectedCriteria())
            ->orderBy(
                [
                    'fecha' => 'ASC'
                ]
            );

        if (count($banks) == 1) {
            $bank = current($banks);
            $criteria->andWhere(Criteria::expr()->eq('bank', $bank));

            $todayBalance = $bank->getBalance($today);
            if ( empty( $todayBalance ) ) {
                $todayBalance = new SaldoBancario();
                $todayBalance->setValor( 0 );
            } else {
                $todayBalance = clone $todayBalance;
            }
            $todayBalance->setFecha( $today );
        } else {
            $totalBalance = 0;

            foreach ($banks as $bank) {
                $balance = $bank->getBalance($today);
                $totalBalance += $balance ? $balance->getValor() : 0;
            }

            $todayBalance = new SaldoBancario();
            $todayBalance
                ->setValor( $totalBalance )
                ->setFecha( $today )
                ;
        }

        $transactions = $this->getDoctrine()->getRepository('App:Movimiento')->matching($criteria);

        $balances[] = $todayBalance;

        $currentBalance = clone $todayBalance;

        $period = new \DatePeriod($today->add( $oneDay ), $oneDay, $dateTo);

        foreach ($period as $date) {
            $currentBalance->setFecha( $date );

            $dailyTransactions = $transactions->filter(function (Movimiento $transaction) use ($date) {

                return $transaction->getFecha()->format('y-m-d') == $date->format('y-m-d');
            });

            $dailyBalance = 0;
            foreach ($dailyTransactions as $transaction) {
                $dailyBalance += $transaction->getImporte();
            }

            $currentBalance->setValor( $currentBalance->getValor() + $dailyBalance );
            $balances[] = $currentBalance;
            $currentBalance = clone last( $balances );
        }

        return $balances;
    }

    /**
     * @param Request $request
     * @Route(name="show_bank_balance", path="/bank/showBalance")
     */
    public function showBankBalance(Request $request)
    {
        $startDate = (new \DateTimeImmutable())->sub( new \DateInterval('P7D') );
        $days = $this->getParameter('projected_balances_days');
        $endDate = $startDate->add(new \DateInterval("P{$days}D"));

        $banks = $this->getDoctrine()->getRepository('App:Bank')->findAll();
        $form = $this
            ->createFormBuilder()
            ->add(
                'dateFrom',
                DateType::class,
                [
                    'data' => $startDate,
                ]
            )
            ->add(
                'dateTo',
                DateType::class,
                [
                    'data' => $endDate,
                ]
            )
            ->add(
                'Submit',
                SubmitType::class,
                [
                    'label' => 'Query',
                    'attr' =>
                        [
                            'class' => 'btn btn-primary',
                        ]
                ]
            )
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $startDate = $form['dateFrom']->getData();
            $endDate = $form['dateTo']->getData();
        }

        $pastBalances = $this->generatePastBalances( $banks, $startDate, $endDate );
        $toBeLoadedBalances = $this->generateToBeLoadedBalances( $banks );
        $futureBalances = $this->generateFutureBalances( $banks, $startDate, $endDate );

        return $this->render(
            'admin/show_bank_balance.html.twig',
            [
                'today' => new \DateTimeImmutable(),
                'form' => $form->createView(),
                'pastBalances' => $pastBalances,
                'futureBalances' => $futureBalances,
                'toBeLoadedBalances' => $toBeLoadedBalances,
                'banks' => $banks,
            ]
        );
    }

    /**
     * @param array $banks
     * @return array
     */
    private function generateToBeLoadedBalances(array $banks ) : array
    {
        $balances = [];

        foreach ($banks as $bank) {
            $balances[ $bank->getId() ] = $bank->getPastCalculatedBalance( new \DateTimeImmutable('yesterday') );
        }

        return $balances;
    }

    /**
     * @param array $banks
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @return array
     * @throws \Exception
     */
    private function generateFutureBalances(array $banks, \DateTimeInterface $start, \DateTimeInterface $end) : array
    {
        if ( $end < $start ) {

            throw new InvalidArgumentException('End date must be after start date');
        }

        $firstDay = new \DateTimeImmutable();

        if ( $end < $firstDay ) {
            throw new InvalidArgumentException('Start date must be before today');
        }

        if ( $start < $firstDay ) {
            $start = $firstDay;
        }

        $balances = [];

        $period = new \DatePeriod( $start, new \DateInterval('P1D'), $end );

        foreach ( $period as $futureDate ) {
            foreach ( $banks as $bank ) {
                $balances[ $futureDate->format('d/m/Y')  ][ $bank->getId() ] = $bank->getFutureBalance( $futureDate );
            }
        }

        return $balances;
    }

    /**
     * @param array $banks
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     */
    private function generatePastBalances( array $banks, \DateTimeInterface $start, \DateTimeInterface $end ) : array
    {
        if ( $end < $start ) {

            throw new InvalidArgumentException('End date must be after start date');
        }

        $lastDay = new \DateTimeImmutable('-2 days');

        if ( $start > $lastDay ) {
            throw new InvalidArgumentException('Start date must be before today');
        }

        if ( $end > $lastDay ) {
            $end = $lastDay;
        }

        $balances = [];

        $period = new \DatePeriod( $start, new \DateInterval('P1D'), $end );

        foreach ( $period as $pastDate ) {
            foreach ( $banks as $bank ) {
                $balances[ $pastDate->format('d/m/Y') ][ $bank->getId() ] = $bank->getPastCalculatedBalance( $pastDate );
            }
        }

        return $balances;
    }
    /**
     * @param Request $request
     * @Route(name="send_bank_balance", path="/bank/sendBalance", options={"expose"=true})
     */
    public function sendBankBalance(Request $request)
    {
        $dateFrom = new \DateTimeImmutable($request->get('dateFrom'));
        $days = $this->getParameter('projected_balances_days');
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable($request->get('dateTo')) : $dateFrom->add(new \DateInterval("P{$days}D"));
        $bank = $request->get('bank');

        $banks = $bank ? [$this->getDoctrine()->getRepository('App:Bank')->find($bank)] : $this->getDoctrine()->getRepository('App:Bank')->findAll();

        $balances = $this->calculateBalances($dateFrom, $dateTo, $banks);

        $message = (new \Swift_Message($this->get('translator')->trans('Bank balances summary')))
            ->setFrom('rotschild@blasting.com.ar')
            ->setTo($this->getParameter('send_balances_to'))
            ->setBody(
                $this->renderView(
                    'emails/balances.html.twig',
                    [
                        'banks' => $banks,
                        'balances' => $balances,
                    ]
                ),
                'text/html'
            );

        $this->get('mailer')->send($message);

        return new JsonResponse($this->get('translator')->trans('Email sent!'));
    }

    /**
     * @Route(name="load_bank_balance",path="/bank/{id}/loadBalance/{dateString}", options={"expose"=true})
     * @ParamConverter(class="App\Entity\Bank", name="bank")
     */
    public function loadBalance(Request $request, Bank $bank, string $dateString )
    {
        $date = new \DateTimeImmutable( $dateString );
        $em = $this->getDoctrine()->getManager();

        if (($balance = $bank->getPastActualBalance($date)) == null) {
            $balance = new SaldoBancario();
            $balance->setFecha($date);
            $balance->setBank($bank);
        }

        $form = $this
            ->createFormBuilder($balance)
            ->setAttribute('class', 'form-horizontal new-form')
            ->add('valor', MoneyType::class,
                [
                    'label' => 'Saldo real',
                    'currency' => $this->getParameter('currency')
                ])
            ->add('Save', SubmitType::class,
                [
                    'attr' =>
                        [
                            'class' => 'btn btn-primary',
                        ],
                    'label' => 'action.save',
                ])
            ->getForm();

        $startDate = $date->sub(new \DateInterval('P1D'));
        $initialBalance = $bank->getPastCalculatedBalance($startDate);
        $finalExpectedBalance = $bank->getPastCalculatedBalance($date);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $balance->setDiferenciaConProyectado($balance->getValor() - $finalExpectedBalance->getValor());
            $em->persist($balance);
            $em->flush();

            return $this->redirectToRoute(
                'show_bank_balance',
                [
                ]
            );
        }

        return $this->render(
            'admin/load_bank_balance.html.twig',
            [
                'form' => $form->createView(),
                'bank' => $bank,
                'fecha' => $date,
                'transactions' => $bank->getTransactionsBetween( $startDate, $date, true ),
                'initialBalance' => $initialBalance,
                'finalBalance' => $finalExpectedBalance,
            ]
        );
    }
}