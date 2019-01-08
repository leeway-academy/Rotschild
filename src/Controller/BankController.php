<?php

namespace App\Controller;

use App\Entity\Bank;
use App\Entity\ExtractoBancario;
use App\Entity\Movimiento;
use App\Entity\RenglonExtracto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
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
    public function matchCreditSummaryLines( Bank $bank, Request $request )
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
        $dateFrom = $request->get('dateFrom') ? new \DateTimeImmutable( $request->get('dateFrom')['date'] ) : null;
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable( $request->get('dateTo')['date'] ) : null;

        $movimientosRepo = $this->getDoctrine()->getRepository('App:Movimiento');

        foreach ( $bank->getExtractos() as $extracto ) {
            $lines = $extracto->getRenglones()->filter(function (RenglonExtracto $r) use ($dateFrom, $dateTo) {

                $ret = ( empty($dateFrom) || $r->getFecha() >= $dateFrom ) && ( empty($dateTo) || $r->getFecha() <= $dateTo ) && $r->getImporte() > 0;

                return $ret;
            });

            foreach ($lines as $k => $renglon) {
                $transactions = $movimientosRepo->findByWitness( $renglon );

                if ( !empty($transactions) ) {
                    unset( $lines[$k] );

                    continue;
                }

                $formBuilder
                    ->add(
                        $existingTxPefix.'_' . $renglon->getId(),
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
                        $newTxPrefix.'_'.$renglon->getId(),
                        ChoiceType::class,
                        [
                            'choices' => $newCreditConcepts,
                            'choice_label' => function ($choiceValue, $key, $value) {

                                return $choiceValue . '';
                            },
                            'choice_value' => function ( $v ) use ( $newCreditConcepts ) {

                                return array_search( $v, $newCreditConcepts);
                            },
                            'required' => false,
                        ]
                    )
                ;
                $pendingCredits[ $renglon->getId() ] = $renglon;
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

        if ( $matchingForm->isSubmitted() && $matchingForm->isValid() ) {
            $em = $this->getDoctrine()->getManager();
            $renglonExtractoRepository = $em->getRepository('App:RenglonExtracto');
            foreach ($matchingForm->getData() as $name => $transaction) {
                if ( !empty($transaction) ) {
                    $summaryLineId = last(preg_split('/_/', $name));

                    if ($summaryLine = $renglonExtractoRepository->find($summaryLineId)) {
                        if (substr($name, 0, strlen($existingTxPefix)) == $existingTxPefix ) {
                            /**
                             * @todo: Look into the case for multiple associations!
                             */
                            if ( is_array( $transaction ) ) {
                                foreach ( $transaction as $t ) {
                                    $t->setWitness( $summaryLine );
                                    $em->persist($t);
                                }
                            }
                        } elseif ( substr($name, 0, strlen($newTxPrefix)) == $newTxPrefix ) {
                            /**
                             * @todo Create new transaction
                             */
                            $newTransaction = new Movimiento();
                            $newTransaction
                                ->setConcepto( $this->get('translator')->trans($transaction) )
                                ->setFecha( $summaryLine->getFecha() )
                                ->setImporte( $summaryLine->getImporte() )
                                ->setWitness( $summaryLine )
                                ->setBank( $summaryLine->getExtracto()->getBank() )
                            ;
                            $em->persist( $newTransaction );
                        }
                    } else {
                        /**
                         * Something really wrong happened... Is somebody messing with the system??
                         */
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
    public function matchSummaryDebitLines( Bank $bank, Request $request )
    {
        $existingTxPefix = 'transaction';
        $newTxPrefix = 'new_tx';

        $summaryLines = [];

        $projectedDebits = $bank->getDebitosProyectados();

        /**
         * @todo Externalize this concepts into a db table
         */
        $newDebitConcepts = [
            '-4' => 'bank.comission.charge',
            '-5' => 'tax.payed',
            '-6' => 'investment.fixed_term',
            '-7' => 'fines',
            '-8' => 'fees',
            '-9' => 'salary.advances',
            '-10' => 'transfer.own_account',
            '-11' => 'expenses.shareholders',
            '-12' => 'check.applied.rejection',
        ];

        $formBuilder = $this->createFormBuilder();
        $dateFrom = $request->get('dateFrom') ? new \DateTimeImmutable( $request->get('dateFrom')['date'] ) : null;
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable( $request->get('dateTo')['date'] ) : null;

        $movimientosRepo = $this->getDoctrine()->getRepository('App:Movimiento');

        foreach ( $bank->getExtractos() as $extracto ) {
            $lines = $extracto->getRenglones()->filter(function (RenglonExtracto $r) use ($dateFrom, $dateTo) {

                $ret = ( empty($dateFrom) || $r->getFecha() >= $dateFrom ) && ( empty($dateTo) || $r->getFecha() <= $dateTo ) && $r->getImporte() < 0;

                return $ret;
            });

            foreach ($lines as $k => $renglon) {
                $transactions = $movimientosRepo->findByWitness( $renglon );

                if ( !empty($transactions) ) {
                    unset( $lines[$k] );

                    continue;
                }

                $formBuilder
                    ->add(
                        $existingTxPefix.'_' . $renglon->getId(),
                        ChoiceType::class,
                        [
                            'choices' => $projectedDebits,
                            'choice_value' => function (Movimiento $movimiento = null) {

                                return $movimiento ? $movimiento->getId() : '';
                            },
                            'choice_label' => function (Movimiento $movimiento) {

                                return $movimiento->__toString();
                            },
                            'label' => $renglon->getFecha()->format('d/m/Y') . ': ' . $renglon->getConcepto() . ' ' . $renglon->getImporte(),
                            'required' => false,
                        ]
                    )
                    ->add(
                        $newTxPrefix.'_'.$renglon->getId(),
                        ChoiceType::class,
                        [
                            'choices' => $newDebitConcepts,
                            'choice_label' => function ($choiceValue, $key, $value) {

                                return $choiceValue . '';
                            },
                            'choice_value' => function ( $v ) use ( $newDebitConcepts ) {

                                return array_search( $v, $newDebitConcepts);
                            },
                            'required' => false,
                        ]
                    )
                ;
                $summaryLines[ $renglon->getId() ] = $renglon;
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

        if ( $matchingForm->isSubmitted() && $matchingForm->isValid() ) {
            $em = $this->getDoctrine()->getManager();
            $renglonExtractoRepository = $em->getRepository('App:RenglonExtracto');
            foreach ($matchingForm->getData() as $name => $transaction) {
                if ( !empty($transaction) ) {
                    $summaryLineId = last(preg_split('/_/', $name));

                    if ($summaryLine = $renglonExtractoRepository->find($summaryLineId)) {
                        if (substr($name, 0, strlen($existingTxPefix)) == $existingTxPefix ) {
                            /**
                             * @todo: Look into the case for multiple associations!
                             */
                            $transaction->setWitness( $summaryLine );
                            $em->persist($transaction);
                        } elseif ( substr($name, 0, strlen($newTxPrefix)) == $newTxPrefix ) {
                            /**
                             * @todo Create new transaction
                             */
                            $newTransaction = new Movimiento();
                            $newTransaction
                                ->setConcepto( $this->get('translator')->trans($transaction) )
                                ->setFecha( $summaryLine->getFecha() )
                                ->setImporte( $summaryLine->getImporte() )
                                ->setWitness( $summaryLine )
                                ->setBank( $summaryLine->getExtracto()->getBank() )
                            ;
                            $em->persist( $newTransaction );
                        }
                    } else {
                        /**
                         * Something really wrong happened... Is somebody messing with the system??
                         */
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
                'summaryLines' => $summaryLines,
                'newDebitConcepts' => $newDebitConcepts,
            ]
        );
    }
}
