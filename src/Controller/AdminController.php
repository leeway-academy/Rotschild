<?php

namespace App\Controller;

use App\Entity\AppliedCheck;
use App\Entity\Bank;
use App\Entity\ChequeEmitido;
use App\Entity\ExtractoBancario;
use App\Entity\GastoFijo;
use App\Entity\Movimiento;
use App\Entity\RenglonExtracto;
use App\Entity\SaldoBancario;
use App\Service\ExcelReportsProcessor;
use Doctrine\Common\Collections\Criteria;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends BaseAdminController
{
    private $excelReportsProcessor;

    public function __construct(ExcelReportsProcessor $excelReportProcessor)
    {
        $this->setExcelReportProcessor($excelReportProcessor);
    }

    /**
     * @Route(name="load_bank_balance",path="/bank/{id}/loadBalance", options={"expose"=true})
     * @ParamConverter(class="App\Entity\Bank", name="bank")
     */
    public function loadBankBalance(Bank $bank, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $fecha = new \DateTimeImmutable('Yesterday');

        if (($saldo = $bank->getBalance($fecha)) == null) {
            $saldo = new SaldoBancario();
            $saldo->setFecha($fecha);
            $saldo->setBank($bank);
        }

        $form = $this
            ->createFormBuilder($saldo)
            ->setAttribute('class', 'form-horizontal new-form')
            ->add('valor', MoneyType::class,
                [
                    'label' => 'Amount',
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

        $saldoProyectado = $bank->getProjectedBalance($fecha)->getValor();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $saldo->setDiferenciaConProyectado($saldo->getValor() - $saldoProyectado);
            $em->persist($saldo);
            $em->flush();

            return $this->redirectToRoute(
                'easyadmin',
                [
                    'entity' => 'Bank',
                    'action' => 'list'
                ]
            );
        } else {

            return $this->render(
                'admin/load_bank_balance.html.twig',
                [
                    'form' => $form->createView(),
                    'entity' => $saldo,
                    'fecha' => $fecha,
                    'proyectado' => $saldoProyectado,
                ]
            );
        }
    }

    protected function showBancoAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_SHOW);

        $id = $this->request->query->get('id');
        $easyadmin = $this->request->attributes->get('easyadmin');
        $banco = $easyadmin['item'];

        $hoy = new \DateTimeImmutable();

        $period = new \DatePeriod(new \DateTimeImmutable(), new \DateInterval('P1D'), 180); // @todo Extract to config

        foreach ($period as $dia) {
            $banco->saldosProyectados[$dia->format('Y-m-d')] = $banco->getSaldoProyectado($dia);
        }

        $fields = $this->entity['show']['fields'];
        $deleteForm = $this->createDeleteForm($this->entity['name'], $id);

        $this->dispatch(EasyAdminEvents::POST_SHOW, array(
            'deleteForm' => $deleteForm,
            'fields' => $fields,
            'entity' => $banco,
        ));

        $parameters = array(
            'entity' => $banco,
            'fields' => $fields,
            'delete_form' => $deleteForm->createView(),
        );

        foreach ($banco->getSaldos() as $saldo) {
            if ($hoy->diff($saldo->getFecha())->days > 15) { // @todo Extract to config
                $banco->removeSaldo($saldo);
            } else {
                break;
            }
        }

        return $this->executeDynamicMethod('render<EntityName>Template', array('show', $this->entity['templates']['show'], $parameters));
    }

    /**
     * @param GastoFijo $gastoFijo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function persistGastoFijoEntity(GastoFijo $gastoFijo)
    {
        $this->em->persist($gastoFijo);
        $this->em->flush();

        $initDate = \DateTimeImmutable::createFromMutable( $gastoFijo->getFechaInicio() );

        $oneMonth = new \DateInterval('P1M');

        if ( $initDate->format('d') > $gastoFijo->getDia() ) {
            $initDate = $initDate
                ->sub( new \DateInterval('P'.($initDate->format('d') - $gastoFijo->getDia() ).'D') )
                ->add( $oneMonth );
        } else {
            $initDate = $initDate
                ->add( new \DateInterval('P'.( $gastoFijo->getDia() - $initDate->format('d') ).'D') );
        }

        if (($fechaFin = $gastoFijo->getFechaFin()) === null) {
            $fechaFin = $initDate->add(new \DateInterval('P12M'));
        }

        while ($initDate < $fechaFin) {
            $movimiento = new Movimiento();
            $movimiento
                ->setConcepto($gastoFijo->getConcepto())
                ->setFecha($initDate)
                ->setImporte($gastoFijo->getImporte() * -1)
                ->setBank($gastoFijo->getBank())
                ->setClonDe($gastoFijo);

            $this->em->persist($movimiento);
            $initDate = $initDate->add($oneMonth);
        }


        $this->em->flush();
    }

    /**
     * @param GastoFijo $gastoFijo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function updateGastoFijoEntity(GastoFijo $gastoFijo)
    {
        $today = new \DateTimeImmutable();

        foreach ($gastoFijo->getMovimientos() as $movimiento) {
            if ( $movimiento->getFecha() > $today ) {
                if (
                    !empty($gastoFijo->getFechaFin()) && $movimiento->getFecha() > $gastoFijo->getFechaFin()
                    ||
                    $movimiento->getFecha() < $gastoFijo->getFechaInicio()
                ) {
                    $this->em->remove( $movimiento );
                } else {
                    $movimiento->setConcepto($gastoFijo->getConcepto());
                    $movimiento->setImporte($gastoFijo->getImporte() * -1);
                    $movimiento->setBank($gastoFijo->getBank());
                    $this->em->persist($movimiento);
                }
            }
        }

        $this->em->flush();
    }

    /**
     * @param GastoFijo $gastoFijo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function removeGastoFijoEntity(GastoFijo $gastoFijo)
    {
        $today = new \DateTimeImmutable();

        foreach ($gastoFijo->getMovimientos() as $movimiento) {
            if ($movimiento->getFecha() == $today ) {
                $this->em->remove($movimiento);
            }
        }

        $this->em->remove($gastoFijo);
        $this->em->flush();
    }

    /**
     * @param Request $request
     * @Route(path="/import/bankSummaries", name="import_bank_summaries")
     */
    public function importBankSummaries(Request $request)
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
                if (!is_null($item) && $item->getType() == 'file' && in_array($item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])) {
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
                }
            }

            $this->addFlash(
                'notice',
                'Extractos importados'
            );
        }

        return $this->render(
            'admin/import_excel_reports.html.twig',
            [
                'form' => $form->createView(),
                'reportName' => 'Extractos Bancarios',
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(name="import_issued_checks", path="/import/issuedChecks")
     */
    public function importIssuedChecks(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->setAttribute('class', 'form-vertical new-form');

        $formBuilder->add(
            'reportFile',
            FileType::class,
            [
                'label' => 'Informe de cheques emitidos ',
                'required' => true,
            ]
        )->add(
            'Import',
            SubmitType::class,
            [
                'attr' => [
                    'class' => 'btn btn-primary action-save',
                ],
                'label' => 'Importar',
            ]
        )->getForm();

        $form = $formBuilder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            foreach ($form->getData() as $name => $item) {
                if (!is_null($item) && $item->getType() == 'file' && in_array($item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])) {
                    $fileName = 'IssuedChecks_' . (new \DateTimeImmutable())->format('d-m-y') . '.' . $item->guessExtension();
                    $item->move($this->getParameter('reports_path'), $fileName);

                    $lines = $this->getExcelReportProcessor()->getIssuedChecks(
                        IOFactory::load($this->getParameter('reports_path') . DIRECTORY_SEPARATOR . $fileName)
                    );

                    foreach ($lines as $k => $line) {
                        $chequeEmitido = new ChequeEmitido();
                        $chequeEmitido
                            ->setImporte($line['amount'])
                            ->setFecha($line['date'])
                            ->setBanco($em->getRepository('App:Bank')->findOneBy(['codigo' => $line['bankCode']]))
                            ->setNumero($line['checkNumber']);
                        $em->persist($chequeEmitido);
                    }

                    $em->flush();
                }
            }

            $this->addFlash(
                'notice',
                'Cheques emitidos importados'
            );
        }

        return $this->render(
            'admin/import_excel_reports.html.twig',
            [
                'form' => $form->createView(),
                'reportName' => 'Cheques emitidos',
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(name="import_applied_checks", path="/import/appliedChecks")
     */
    public function importAppliedChecks(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->setAttribute('class', 'form-vertical new-form');

        $formBuilder->add(
            'reportFile',
            FileType::class,
            [
                'label' => 'Informe de cheques aplicados ',
                'required' => true,
            ]
        )->add(
            'Import',
            SubmitType::class,
            [
                'attr' => [
                    'class' => 'btn btn-primary action-save',
                ],
                'label' => 'Importar',
            ]
        )->getForm();

        $form = $formBuilder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            foreach ($form->getData() as $name => $item) {
                if (!is_null($item) && $item->getType() == 'file' && in_array($item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])) {
                    $fileName = 'AppliedChecks_' . (new \DateTimeImmutable())->format('d-m-y') . '.' . $item->guessExtension();
                    $item->move($this->getParameter('reports_path'), $fileName);

                    $lines = $this->getExcelReportProcessor()->getAppliedChecks(
                        IOFactory::load($this->getParameter('reports_path') . DIRECTORY_SEPARATOR . $fileName)
                    );

                    foreach ($lines as $k => $line) {
                        $appliedCheck = new AppliedCheck();
                        $appliedCheck
                            ->setAmount($line['amount'])
                            ->setDate($line['date'])
                            ->setType($line['type'])
                            ->setDestination($line['destination'])
                            ->setIssuer($line['issuer'])
                            ->setSourceBank($line['sourceBank'])
                            ->setNumber($line['number']);

                        $em->persist($appliedCheck);
                    }

                    $em->flush();
                }
            }

            $this->addFlash(
                'notice',
                'Cheques aplicados importados'
            );
        }

        return $this->render(
            'admin/import_excel_reports.html.twig',
            [
                'form' => $form->createView(),
                'reportName' => 'Cheques aplicados',
            ]
        );
    }


    /**
     * @param ExcelReportsProcessor $excelReportProcessor
     */
    public function setExcelReportProcessor(ExcelReportsProcessor $excelReportProcessor): AdminController
    {
        $this->excelReportsProcessor = $excelReportProcessor;

        return $this;
    }

    /**
     * @return ExcelReportsProcessor
     */
    public function getExcelReportProcessor(): ExcelReportsProcessor
    {
        return $this->excelReportsProcessor;
    }

    /**
     * @param Request $request
     * @Route(path="/bank/matchSummaries", name="match_bank_summaries", options={"expose"=true})
     */
    public function matchBankSummaries(Request $request)
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
     * @Route(name="match_bank_summary_lines", path="/bank/{id}/match_summary_lines")
     * @ParamConverter(name="bank", class="App\Entity\Bank")
     * @param Bank $bank
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function matchBankSummaryLines( Bank $bank, Request $request )
    {
        $summaryLines = [];

        $projectedCredits = $bank->getCreditosProyectados();
        $projectedDebits = $bank->getDebitosProyectados();

        $formBuilder = $this->createFormBuilder();
        $dateFrom = $request->get('dateFrom') ? new \DateTimeImmutable( $request->get('dateFrom')['date'] ) : null;
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable( $request->get('dateTo')['date'] ) : null;

        foreach ( $bank->getExtractos() as $extracto ) {
            $lines = $extracto->getRenglones()->filter(function (RenglonExtracto $r) use ($dateFrom, $dateTo) {

                $ret = ( empty($dateFrom) || $r->getFecha() >= $dateFrom ) && ( empty($dateTo) || $r->getFecha() <= $dateTo ) && $r->getMovimientos()->isEmpty();

                return $ret;
            });

            foreach ($lines as $renglon) {
                $formBuilder
                    ->add(
                        'match-' . $renglon->getId(),
                        ChoiceType::class,
                        [
                            'choices' => $renglon->getImporte() > 0 ? $projectedCredits : $projectedDebits,
                            'choice_value' => function (Movimiento $movimiento = null) {

                                return $movimiento ? $movimiento->getId() : '';
                            },
                            'choice_label' => function (Movimiento $movimiento) {

                                return $movimiento->__toString();
                            },
                            'label' => $renglon->getFecha()->format('d/m/Y') . ': ' . $renglon->getConcepto() . ' ' . $renglon->getImporte(),
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

        if ( $matchingForm->isSubmitted() && $matchingForm->isValid() ) {
            $em = $this->getDoctrine()->getManager();
            $keyword = 'match-';
            $renglonExtractoRepository = $em->getRepository('App:RenglonExtracto');
            foreach ($matchingForm->getData() as $name => $transaction) {
                if (substr($name, 0, strlen($keyword)) == $keyword && $transaction) {
                    $summaryLineId = preg_split('/-/', $name)[1];

                    if ($summaryLine = $renglonExtractoRepository->find($summaryLineId)) {
                        $summaryLine->addMovimiento( $transaction );
                        $em->persist($transaction);
                    }
                }
            }

            $em->flush();

            return $this->redirectToRoute(
                'match_bank_summary_lines',
                [
                    'id' => $bank->getId(),
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ]);
        }

        return $this->render(
            'admin/match_bank_summary_lines.html.twig',
            [
                'matchingForm' => $matchingForm->createView(),
                'summaryLines' => $summaryLines
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(path="/checks/issued/confirm", name="confirm_issued_checks")
     */
    public function confirmIssuedChecks(Request $request)
    {
        $formBuilder = $this->createFormBuilder();

        $managerRegistry = $this->getDoctrine();

        $debits = $managerRegistry->getRepository('App:Movimiento')
            ->findPendingDebits();

        $issuedChecks = $managerRegistry->getRepository('App:ChequeEmitido')->findAll();
        foreach ($issuedChecks as $k => $check) {
            $formBuilder->add(
                'match_' . $k,
                ChoiceType::class,
                [
                    'choices' => array_merge(
                        [
                            "-1" => 'N/A'
                        ],
                        $debits->toArray()
                    ),
                    'choice_value' => function( $o ) {
                        if ( $o instanceof Movimiento ) {

                            return $o->getId();
                        } elseif ( $o == 'N/A' ) {

                            return "-1";
                        } else {

                            return "";
                        }
                    },
                    'choice_label' => function ($d) {

                        return $d . '';
                    },
                    'required' => false,
                    'attr' => [
                        'class' => 'selectTx',
                    ],
                ]
            );
        }

        $formBuilder
            ->add(
                'submit',
                SubmitType::class,
                [
                    'label' => 'Confirmar',
                    'attr' => [
                        'class' => 'btn btn-primary',
                    ]
                ]
            );

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $objectManager = $managerRegistry->getManager();
            foreach ($form->getData() as $k => $datum) {
                $parts = preg_split('/_/', $k);
                $k = $parts[1];
                $check = $issuedChecks[$k];
                if ($datum) {
                    $objectManager->remove($check);
                    unset($issuedChecks[$k]);

                    if ($datum instanceof Movimiento) {
                        $datum
                            ->setBank($check->getBanco())
                            ->setImporte($check->getImporte() * -1)
                            ->setConcepto('Cheque ' . $check->getNumero())
                            ->setFecha($check->getFecha())/** @Todo probably will need to be some time in the future */
                        ;

                        $objectManager->persist($datum);
                    }
                }

                $objectManager->flush();
            }

            return $this->redirectToRoute('confirm_issued_checks');
        }

        return $this->render(
            'admin/confirm_issued_checks.html.twig',
            [
                'form' => $form->createView(),
                'checks' => $issuedChecks,
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(path="/processedAppliedChecks", name="process_applied_checks")
     */

    public function processAppliedChecks(Request $request)
    {
        /*
         * @todo This could be much more intelligent since the destination of the check is part of the
         * Excel file...
         */
        $formBuilder = $this->createFormBuilder();

        $bancos = $this->getDoctrine()->getRepository('App:Bank')->findAll();
        $criteria = Criteria::create()
            ->where(Criteria::expr()->lt('importe', 0))
            ->andWhere(Criteria::expr()->eq('concretado', false));
        $debits = $this->getDoctrine()->getRepository('App:Movimiento')->matching($criteria);
        $checks = $this->getDoctrine()->getRepository('App:AppliedCheck')->findAll();

        foreach ($checks as $k => $check) {
            $formBuilder->add(
                'bank_' . $k,
                ChoiceType::class,
                [
                    'choices' => $bancos,
                    'choice_label' => function ($choiceValue, $key, $value) {

                        return $choiceValue . '';
                    },
                    'choice_value' => 'id',
                    'required' => false,
                ]
            );
            $formBuilder->add(
                'debit_' . $k,
                ChoiceType::class,
                [
                    'choices' => $debits,
                    'choice_label' => function ($choiceValue, $key, $value) {

                        return $choiceValue . '';
                    },
                    'choice_value' => 'id',
                    'required' => false,
                ]
            );
        }

        $formBuilder->add(
            'aplicar',
            SubmitType::class,
            [
            ]
        );

        $form = $formBuilder->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            foreach ($checks as $k => $check) {
                $movimiento = $form['debit_' . $k]->getData();
                $recipientBank = $form['bank_' . $k]->getData();
                if (!empty($recipientBank) || !empty($movimiento)) {
                    if (!empty($recipientBank)) {
                        $movimiento = new Movimiento();
                        $movimiento
                            ->setBank($recipientBank)
                            ->setImporte($check->getAmount())
                            ->setFecha($check->getCreditDate())
                            ->setConcepto('Acreditacion de cheque ' . $check->getNumber());
                    } elseif (!empty($movimiento)) {
                        $movimiento->setConcretado(true);
                    }
                    $em->persist($movimiento);
                    $em->remove($check);
                }
            }

            $em->flush();

            return $this->redirectToRoute('process_applied_checks');
        }

        return $this->render(
            'admin/process_applied_checks.html.twig',
            [
                'form' => $form->createView(),
                'checks' => $checks,
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(name="show_bank_balance", path="/bank/showBalance")
     */
    public function showBankBalance(Request $request)
    {
        $startDate = new \DateTimeImmutable();
        $days = $this->getParameter('projected_balances_days');
        $endDate = $startDate->add(new \DateInterval("P{$days}D"));

        $banks = $this->getDoctrine()->getRepository('App:Bank')->findAll();
        $form = $this
            ->createFormBuilder()
            ->add(
                'bank',
                ChoiceType::class,
                [
                    'choices' => $banks,
                    'required' => false,
                    'choice_label' => function (Bank $b) {

                        return $b->__toString();
                    },
                    'choice_value' => function (Bank $b = null) {

                        return $b ? $b->getId() : '';
                    },
                ]
            )
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

        $balances = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $dateFrom = $form['dateFrom']->getData();
            $dateTo = $form['dateTo']->getData();
            $bank = $form['bank']->getData();

            $balances = $this->calculateBanksBalance( $dateFrom, $dateTo, $bank ? [ $bank ] : $banks );
        }

        return $this->render(
            'admin/show_bank_balance.html.twig',
            [
                'form' => $form->createView(),
                'balances' => $balances,
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(name="send_bank_balance", path="/bank/sendBalance", options={"expose"=true})
     */
    public function sendBankBalance( Request $request )
    {
        $dateFrom = new \DateTimeImmutable( $request->get('dateFrom') );
        $days = $this->getParameter('projected_balances_days');
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable( $request->get('dateTo') ) : $dateFrom->add( new \DateInterval("P{$days}D") );
        $bank = $request->get('bank');

        $banks = $bank ? [ $this->getDoctrine()->getRepository('App:Bank')->find( $bank ) ] : $this->getDoctrine()->getRepository('App:Bank')->findAll();

        $balances = $this->calculateBanksBalance( $dateFrom, $dateTo, $banks );

        $message = ( new \Swift_Message($this->get('translator')->trans('Bank balances summary') ) )
            ->setFrom('rotschild@blasting.com.ar')
            ->setTo( $this->getParameter('send_balances_to'))
            ->setBody(
                $this->renderView(
                    'emails/balances.html.twig',
                    [
                        'banks' => $banks,
                        'balances' => $balances,
                    ]
                ),
                'text/html'
            )
        ;

        $this->get('mailer')->send($message);

        return new JsonResponse($this->get('translator')->trans('Email sent!'));
    }

    protected function newDebitoAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_NEW);

        $entity = $this->executeDynamicMethod('createNew<EntityName>Entity');

        $easyadmin = $this->request->attributes->get('easyadmin');
        $easyadmin['item'] = $entity;
        $this->request->attributes->set('easyadmin', $easyadmin);

        $fields = $this->entity['new']['fields'];

        $newForm = $this->executeDynamicMethod('create<EntityName>NewForm', array($entity, $fields));

        $newForm->handleRequest($this->request);
        if ($newForm->isSubmitted() && $newForm->isValid()) {
            $this->dispatch(EasyAdminEvents::PRE_PERSIST, array('entity' => $entity));

            $this->executeDynamicMethod('prePersist<EntityName>Entity', array($entity, true));
            $this->executeDynamicMethod('persist<EntityName>Entity', array($entity, $newForm));

            $this->dispatch(EasyAdminEvents::POST_PERSIST, array('entity' => $entity));

            if (!$this->request->isXmlHttpRequest()) {

                return $this->redirectToReferrer();
            } else {

                return new JsonResponse(
                    [
                        'id' => $entity->getId(),
                        'string' => $entity->__toString(),
                    ]
                );
            }
        }

        $this->dispatch(EasyAdminEvents::POST_NEW, array(
            'entity_fields' => $fields,
            'form' => $newForm,
            'entity' => $entity,
        ));

        $parameters = array(
            'form' => $newForm->createView(),
            'entity_fields' => $fields,
            'entity' => $entity,
        );

        return $this->executeDynamicMethod('render<EntityName>Template', array('new', $this->entity['templates']['new'], $parameters));
    }

    protected function newCreditoAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_NEW);

        $entity = $this->executeDynamicMethod('createNew<EntityName>Entity');

        $easyadmin = $this->request->attributes->get('easyadmin');
        $easyadmin['item'] = $entity;
        $this->request->attributes->set('easyadmin', $easyadmin);

        $fields = $this->entity['new']['fields'];

        $newForm = $this->executeDynamicMethod('create<EntityName>NewForm', array($entity, $fields));

        $newForm->handleRequest($this->request);
        if ($newForm->isSubmitted() && $newForm->isValid()) {
            $this->dispatch(EasyAdminEvents::PRE_PERSIST, array('entity' => $entity));

            $this->executeDynamicMethod('prePersist<EntityName>Entity', array($entity, true));
            $this->executeDynamicMethod('persist<EntityName>Entity', array($entity, $newForm));

            $this->dispatch(EasyAdminEvents::POST_PERSIST, array('entity' => $entity));

            if (!$this->request->isXmlHttpRequest()) {

                return $this->redirectToReferrer();
            } else {

                return new JsonResponse(
                    [
                        'id' => $entity->getId(),
                        'string' => $entity->__toString(),
                    ]
                );
            }
        }

        $this->dispatch(EasyAdminEvents::POST_NEW, array(
            'entity_fields' => $fields,
            'form' => $newForm,
            'entity' => $entity,
        ));

        $parameters = array(
            'form' => $newForm->createView(),
            'entity_fields' => $fields,
            'entity' => $entity,
        );

        return $this->executeDynamicMethod('render<EntityName>Template', array('new', $this->entity['templates']['new'], $parameters));
    }

    protected function editDebitoAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_EDIT);

        $id = $this->request->query->get('id');
        $easyadmin = $this->request->attributes->get('easyadmin');
        $entity = $easyadmin['item'];

        if ($this->request->isXmlHttpRequest() && $property = $this->request->query->get('property')) {
            $newValue = 'true' === mb_strtolower($this->request->query->get('newValue'));
            $fieldsMetadata = $this->entity['list']['fields'];

            if (!isset($fieldsMetadata[$property]) || 'toggle' !== $fieldsMetadata[$property]['dataType']) {
                throw new \RuntimeException(sprintf('The type of the "%s" property is not "toggle".', $property));
            }

            $this->updateEntityProperty($entity, $property, $newValue);

            // cast to integer instead of string to avoid sending empty responses for 'false'
            return new Response((int)$newValue);
        }

        $fields = $this->entity['edit']['fields'];

        $editForm = $this->executeDynamicMethod('create<EntityName>EditForm', array($entity, $fields));
        $deleteForm = $this->createDeleteForm($this->entity['name'], $id);

        $editForm->handleRequest($this->request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->dispatch(EasyAdminEvents::PRE_UPDATE, array('entity' => $entity));

            $this->executeDynamicMethod('preUpdate<EntityName>Entity', array($entity, true));
            $this->executeDynamicMethod('update<EntityName>Entity', array($entity, $editForm));

            $this->dispatch(EasyAdminEvents::POST_UPDATE, array('entity' => $entity));

            if (!$this->request->isXmlHttpRequest()) {

                return $this->redirectToReferrer();
            } else {

                return new JsonResponse(
                    [
                        'id' => $entity->getId(),
                        'string' => $entity->__toString(),
                    ]
                );
            }
        }

        $this->dispatch(EasyAdminEvents::POST_EDIT);

        $parameters = array(
            'form' => $editForm->createView(),
            'entity_fields' => $fields,
            'entity' => $entity,
            'delete_form' => $deleteForm->createView(),
        );

        return $this->executeDynamicMethod('render<EntityName>Template', array('edit', $this->entity['templates']['edit'], $parameters));
    }

    protected function editCreditoAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_EDIT);

        $id = $this->request->query->get('id');
        $easyadmin = $this->request->attributes->get('easyadmin');
        $entity = $easyadmin['item'];

        if ($this->request->isXmlHttpRequest() && $property = $this->request->query->get('property')) {
            $newValue = 'true' === mb_strtolower($this->request->query->get('newValue'));
            $fieldsMetadata = $this->entity['list']['fields'];

            if (!isset($fieldsMetadata[$property]) || 'toggle' !== $fieldsMetadata[$property]['dataType']) {
                throw new \RuntimeException(sprintf('The type of the "%s" property is not "toggle".', $property));
            }

            $this->updateEntityProperty($entity, $property, $newValue);

            // cast to integer instead of string to avoid sending empty responses for 'false'
            return new Response((int)$newValue);
        }

        $fields = $this->entity['edit']['fields'];

        $editForm = $this->executeDynamicMethod('create<EntityName>EditForm', array($entity, $fields));
        $deleteForm = $this->createDeleteForm($this->entity['name'], $id);

        $editForm->handleRequest($this->request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->dispatch(EasyAdminEvents::PRE_UPDATE, array('entity' => $entity));

            $this->executeDynamicMethod('preUpdate<EntityName>Entity', array($entity, true));
            $this->executeDynamicMethod('update<EntityName>Entity', array($entity, $editForm));

            $this->dispatch(EasyAdminEvents::POST_UPDATE, array('entity' => $entity));

            if (!$this->request->isXmlHttpRequest()) {

                return $this->redirectToReferrer();
            } else {

                return new JsonResponse(
                    [
                        'id' => $entity->getId(),
                        'string' => $entity->__toString(),
                    ]
                );
            }
        }

        $this->dispatch(EasyAdminEvents::POST_EDIT);

        $parameters = array(
            'form' => $editForm->createView(),
            'entity_fields' => $fields,
            'entity' => $entity,
            'delete_form' => $deleteForm->createView(),
        );

        return $this->executeDynamicMethod('render<EntityName>Template', array('edit', $this->entity['templates']['edit'], $parameters));
    }

    /**
     * @param $dateFrom
     * @param $dateTo
     * @param array $banks
     * @return array
     * @throws \Exception
     */
    private function calculateBanksBalance( \DateTimeInterface $dateFrom, \DateTimeInterface $dateTo, array $banks ): array
    {
        $criteria = new Criteria();
        $criteria
            ->where(Criteria::expr()->gte('fecha', $dateFrom))
            ->andWhere(Criteria::expr()->lte('fecha', $dateTo))
            ->andWhere(Criteria::expr()->isNull('renglonExtracto' ))
            ->orderBy(
                [
                    'fecha' => 'ASC'
                ]
            );

        $dateFrom = $dateFrom instanceof \DateTimeImmutable ? $dateFrom : \DateTimeImmutable::createFromMutable($dateFrom);

        if ( count($banks) == 1 ) {
            $bank = current($banks);
            $criteria->andWhere( Criteria::expr()->eq('bank', $bank) );

            $balance = $bank->getBalance( $dateFrom );
            $totalBalance = $balance ? $balance->getValor() : 0;
        } else {
            $totalBalance = 0;

            foreach ($banks as $bank) {
                $balance = $bank->getBalance($dateFrom);

                $totalBalance += $balance ? $balance->getValor() : 0;
            }
        }

        $transactions = $this->getDoctrine()->getRepository('App:Movimiento')->matching($criteria);

        $period = new \DatePeriod($dateFrom, new \DateInterval('P1D'), $dateTo);

        $balances = [];
        foreach ($period as $date) {
            $dailyTransactions = $transactions->filter(function (Movimiento $transaction) use ($date) {

                return $transaction->getFecha()->diff($date)->days == 0;
            });

            $dailyBalance = 0;
            foreach ($dailyTransactions as $transaction) {
                $dailyBalance += $transaction->getImporte();
            }

            $totalBalance += $dailyBalance;
            $balances[$date->format('d/m/Y')] = $totalBalance;
        }

        return $balances;
    }
}
