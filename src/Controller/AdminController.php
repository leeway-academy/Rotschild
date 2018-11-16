<?php

namespace App\Controller;

use App\Entity\Banco;
use App\Entity\ExtractoBancario;
use App\Entity\GastoFijo;
use App\Entity\Movimiento;
use App\Entity\RenglonExtracto;
use App\Entity\SaldoBancario;
use Doctrine\Common\Collections\Criteria;
use http\Exception\InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Service\ExcelReportsProcessor;
use Symfony\Component\Validator\Constraints\DateTime;

class AdminController extends BaseAdminController
{
    private $excelReportsProcessor;

    public function __construct(ExcelReportsProcessor $excelReportProcessor )
    {
        $this->setExcelReportProcessor( $excelReportProcessor );
    }

    /**
     * @Route(name="cargar_saldo",path="/banco/cargarSaldo")
     */
    public function cargarSaldoAction( Request $request )
    {
        if ( 'Banco' !== $request->get('entity') ) {

            throw new InvalidArgumentException();
        }

        if ( empty( $request->get('id') ) ) {

            throw new InvalidArgumentException();
        }

        $em = $this->getDoctrine()->getManager();
        $repository = $this->getDoctrine()->getRepository('App:Banco');

        $id = $request->query->get('id');
        $banco = $repository->find($id);

        $fecha = new \DateTime('Yesterday');

        if ( ( $saldo = $banco->getSaldo($fecha) ) == null ) {
            $saldo = new SaldoBancario();
            $saldo->setFecha( $fecha );
            $saldo->setBanco( $banco );
        }

        $form = $this
            ->createFormBuilder( $saldo )
            ->setAttribute('class', 'form-horizontal  new-form')
            ->add( 'valor', NumberType::class )
            ->add('Guardar cambios', SubmitType::class)
            ->getForm();

        $saldoProyectado = $banco->getSaldoProyectado($fecha)->getValor();

        $form->handleRequest($request);
        if ( $form->isSubmitted() && $form->isValid() ) {
            $saldo->setDiferenciaConProyectado( $saldo->getValor() - $saldoProyectado );
            $em->persist( $saldo );
            $em->flush();

            return $this->redirectToRoute('easyadmin', array('entity' => 'Banco', 'action' => 'list'));
        } else {

            return $this->render(
                'admin/cargar_saldo.html.twig',
                [
                    'form' => $form->createView(),
                    'entity' => $saldo,
                    'banco' => $banco->getNombre(),
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

        foreach ( $banco->getSaldos() as $saldo ) {
            if ( $hoy->diff( $saldo->getFecha() )->d > 15 ) { // @todo Extract to config
                $banco->removeSaldo( $saldo );
            } else {
                break;
            }
        }
        $period = new \DatePeriod( new \DateTimeImmutable(), new \DateInterval('P1D'), 180 ); // @todo Extract to config

        foreach ( $period as $dia ) {
            $banco->saldosProyectados[ $dia->format( 'Y-m-d') ] = $banco->getSaldoProyectado( $dia );
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

        return $this->executeDynamicMethod('render<EntityName>Template', array('show', $this->entity['templates']['show'], $parameters));
    }

    /**
     * @param GastoFijo $gastoFijo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function persistGastoFijoEntity( GastoFijo $gastoFijo )
    {
        $this->em->persist($gastoFijo);
        $this->em->flush();

        $curDate = new \DateTimeImmutable();

        if ( $curDate->format('d') > $gastoFijo->getDia() ) {
            $curDate = new \DateTimeImmutable( $gastoFijo->getDia().'-'.$curDate->format('m-Y') );
        } else {
            $curDate = (new \DateTimeImmutable('first day of next month'))->add(new \DateInterval('P'.($gastoFijo->getDia() - 1).'D' ) );
        }

        if ( ( $fechaFin = $gastoFijo->getFechaFin() ) === null ) {
            $fechaFin = $curDate->add(new \DateInterval('P12M'));
        }

        $oneMonth = new \DateInterval('P1M');
        while ( $curDate->diff( $fechaFin )->days > 0 ) {
            $movimiento = new Movimiento();
            $movimiento
                ->setConcepto( $gastoFijo->getConcepto() )
                ->setFecha( $curDate )
                ->setImporte( $gastoFijo->getImporte() * - 1 )
                ->setBanco( $gastoFijo->getBanco() )
                ->setClonDe( $gastoFijo )
            ;

            $this->em->persist( $movimiento );
            $curDate = $curDate->add($oneMonth);
        }


        $this->em->flush();
    }
    /**
     * @param GastoFijo $gastoFijo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function updateGastoFijoEntity( GastoFijo $gastoFijo )
    {
        $hoy = new \DateTimeImmutable();

        foreach ( $gastoFijo->getMovimientos() as $movimiento ) {
            if ($movimiento->getFecha()->diff($hoy)->d >= 0) {
                $movimiento->setConcepto($gastoFijo->getConcepto());
                $movimiento->setImporte($gastoFijo->getImporte() * -1);
                $movimiento->setBanco($gastoFijo->getBanco());
                $this->em->persist($movimiento);
            }
        }

        $this->em->flush();
    }

    /**
     * @param GastoFijo $gastoFijo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function removeGastoFijoEntity( GastoFijo $gastoFijo )
    {
        $hoy = new \DateTimeImmutable();

        foreach ( $gastoFijo->getMovimientos() as $movimiento ) {
            if ( $movimiento->getFecha()->diff( $hoy )->d >= 0 ) {
                $this->em->remove($movimiento);
            }
        }

        $this->em->remove($gastoFijo);
        $this->em->flush();
    }

    /**
     * @param Request $request
     * @Route(path="/importExcelReports", name="import_excel_reports")
     */
    public function importExcelReportsAction( Request $request )
    {
        $formBuilder = $this->createFormBuilder()
            ->setAttribute('class', 'form-vertical new-form');

        $banks = $this->getDoctrine()->getRepository('App:Banco')->findAll();
        foreach ( $banks as $bank ) {
            $formBuilder->add(
                'BankSummary_'.$bank->getId(),
                FileType::class,
                [
                    'label' => 'Extracto del banco '.$bank->getNombre(),
                    'required' => false,
                ]
            );
        }

        $formBuilder
            ->add(
                'IssuedChecks',
                FileType::class,
                [
                    'label' => 'Cheques propios emitidos',
                    'required' => false,
                ]
            )
            ->add(
                'AppliedChecks',
                FileType::class,
                [
                    'label' => 'Cheques aplicados',
                    'required' => false,
                ]
            )
        ;

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

        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            $em = $this->getDoctrine()->getManager();

            foreach ( $form->getData() as $name => $item ) {
                if ( !is_null($item) && $item->getType() == 'file' && in_array( $item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel' ] ) ) {
                    $parts = preg_split('/_/', $name );
                    $fileName = $parts[0];

                    if ( $parts[0] == 'BankSummary' ) {
                        $bank = $em->getRepository('App:Banco')->find($parts[1]);
                        $fileName .= '_'.$bank->getNombre();
                    }

                    $fileName .= '_'.(new \DateTimeImmutable())->format('d-m-y').'.'.$item->guessExtension();
                    $item->move( $this->getParameter('reports_path'), $fileName );
                }
            }

            return $this->redirectToRoute('import_excel_reports');
        }

        return $this->render(
            'admin/import_excel_reports.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }


    /**
     * @param ExcelReportsProcessor $excelReportProcessor
     */
    public function setExcelReportProcessor(ExcelReportsProcessor $excelReportProcessor ): AdminController
    {
        $this->excelReportsProcessor = $excelReportProcessor;

        return $this;
    }

    /**
     * @return ExcelReportsProcessor
     */
    public function getExcelReportProcessor() : ExcelReportsProcessor
    {
        return $this->excelReportsProcessor;
    }

    /**
     * @param Request $request
     * @Route(path="/matchBankSummaries", name="match_bank_summaries")
     */
    public function matchBankSummaries( Request $request )
    {
        $em = $this->getDoctrine()->getManager();
        $banks = $em->getRepository('App:Banco')->findAll();

        $form = $this
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
                    'choice_label' => function( Banco $b ) {

                        return $b->__toString();
                    },
                    'choice_value' => function( Banco $b = null ) {

                        return !empty($b) ? $b->getId() : null;
                    },
                    'label' => 'Banco',
                    'required' => false,
                ]
            )
            ->add(
                'submit',
                SubmitType::class,
                [
                    'label' => 'Confirmar',
                    'attr' => [
                        'class' => 'btn btn-primary',
                        'style' => 'display: none;'
                        ],
                ]
            )
            ->getForm()
        ;

        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            $keyword = 'match_';
            $renglonExtractoRepository = $em->getRepository('App:RenglonExtracto');
            $movimientoRepository = $em->getRepository('App:Movimiento');
            foreach ( $form->getData() as $name => $datum ) {
                if ( substr( $name, 0, strlen( $keyword ) == $keyword ) ) {
                    $summaryLineId = preg_split('/_/', $name )[1];

                    if ( $summaryLine = $renglonExtractoRepository->find( $summaryLineId ) ) {
                        $em->remove( $summaryLine );
                    }
                    if ( $tx = $movimientoRepository->find( $datum ) ) {
                        $tx->setConcretado(true);
                        $em->persist($tx);
                    }
                }
            }

            $em->flush();

            return $this->redirectToRoute('match_bank_summaries');
        }

        return $this->render(
            'admin/match_bank_summaries.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @param Banco $banco
     * @Route(name="get_unmatched_summary_lines", path="/bank/{id}/unmatchedSummaryLines")
     */
    public function getUnmatchedSummaryLines( Request $request, Banco $banco )
    {
        if ( !$request->isXmlHttpRequest() ) {

            throw new BadRequestHttpException('This method should be called via ajax', null, 400);
        }

        $ret = [];
        foreach ( $banco->getExtractos() as $extracto ) {
            foreach( $extracto->getRenglones() as $renglon ) {
                $ret[ $renglon->getId() ] =
                    [
                        'date' => $renglon->getFecha()->format('d-m-y'),
                        'concept' => $renglon->getConcepto(),
                        'amount' => $renglon->getImporte(),
                    ];
            }
        }

        return new JsonResponse( $ret );
    }

    /**
     * @param Banco $banco
     * @Route(name="get_projected_debits", path="/bank/{id}/projectedDebits")
     */
    public function getProjectedDebits( Request $request, Banco $banco )
    {
        if ( !$request->isXmlHttpRequest() ) {

            throw new BadRequestHttpException('This method should be called via ajax', null, 400);
        }

        $ret = [];
        foreach ( $banco->getDebitosProyectados() as $debitoProyectado ) {
            $ret[ $debitoProyectado->getId() ] =
                [
                    'date' => $debitoProyectado->getFecha()->format('d-m-y'),
                    'concept' => $debitoProyectado->getConcepto(),
                    'amount' => $debitoProyectado->getImporte(),
                ];
        }

        return new JsonResponse( $ret );
    }

    /**
     * @param Banco $banco
     * @Route(name="get_projected_credits", path="/bank/{id}/projectedCredits")
     */
    public function getProjectedCrebits( Request $request, Banco $banco )
    {
        if ( !$request->isXmlHttpRequest() ) {

            throw new BadRequestHttpException('This method should be called via ajax', null, 400);
        }

        $ret = [];
        foreach ( $banco->getCreditosProyectados() as $creditoProyectado ) {
            $ret[ $creditoProyectado->getId() ] =
                [
                    'date' => $creditoProyectado->getFecha()->format('d-m-y'),
                    'concept' => $creditoProyectado->getConcepto(),
                    'amount' => $creditoProyectado->getImporte(),
                ];
        }

        return new JsonResponse( $ret );
    }

    /**
     * @param Request $request
     * @Route(path="/confirmIssuedChecks", name="confirm_issued_checks")
     */
    public function confirmIssuedChecks( Request $request )
    {
        $formBuilder = $this->createFormBuilder();

        $checks = $this->getExcelReportProcessor()->getIssuedChecks(
            IOFactory::load($this->getParameter('reports_path').DIRECTORY_SEPARATOR.$reportFileName)
        );

        $banks = $this->getDoctrine()->getRepository('App:Banco')->findAll();

        foreach ( $checks as $k => &$check ) {
            $check['bank'] = current( array_filter( $banks, function( $b ) use ( $check ) {

                return $b->getCodigo() == intval( $check['bankCode'] );
            }) );
            $formBuilder->add(
                'check_'.$k,
                CheckboxType::class,
                [
                    'value' => 1,
                ]
            );
        }

        $formBuilder->add(
            'submit',
            SubmitType::class,
            [
                'label' => 'Confirmar',
            ]
        );

        $form = $formBuilder->getForm();
        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            $objectManager = $this->getDoctrine()->getManager();
            foreach ( $form->getData() as $k => $datum ) {
                $movimiento = new Movimiento();
                $checkInfo = $checks[preg_split('/_/', $k)[1]];
                $movimiento->setConcepto( 'Cheque '. $checkInfo['checkNumber'] );
                $movimiento->setFecha( $checkInfo['date'] );
                $movimiento->setImporte( $checkInfo['amount'] * -1 );
                $movimiento->setBanco( $checkInfo['bank'] );
                $objectManager->persist( $movimiento );
            }
            $objectManager->flush();
            $this->markReportAsProcessed( $request, $reportFileName );

            return $this->proccessExcelReports( $request );
        } else {

            return $this->render(
                'admin/confirm_issued_checks.html.twig',
                [
                    'form' => $form->createView(),
                    'checks' => $checks,
                ]
            );
        }
    }

    /**
     * @param Request $request
     * @Route(path="/processedAppliedChecks", name="process_applied_checks")
     */

    public function processAppliedChecks( Request $request )
    {
        $formBuilder = $this->createFormBuilder();

        $checks = $this->getExcelReportProcessor()->getAppliedChecks(
            IOFactory::load($this->getParameter('reports_path').DIRECTORY_SEPARATOR.$reportFileName)
        );

        $bancos = $this->getDoctrine()->getRepository('App:Banco')->findAll();
        $criteria = Criteria::create()
            ->where( Criteria::expr()->lt('importe', 0) )
            ->andWhere( Criteria::expr()->eq('concretado', false ))
        ;
        $debits = $this->getDoctrine()->getRepository('App:Movimiento')->matching( $criteria );

        foreach ($checks as $k => $check) {
            $formBuilder->add(
                'bank_'.$k,
                ChoiceType::class,
                [
                    'choices' => $bancos,
                    'choice_label' => function( $choiceValue, $key, $value ) {

                        return $choiceValue.'';
                    },
                    'choice_value' => 'id',
                    'required' => false,
                ]
            );
            $formBuilder->add(
                'debit_'.$k,
                ChoiceType::class,
                [
                    'choices' => $debits,
                    'choice_label' => function( $choiceValue, $key, $value ) {

                        return $choiceValue.'';
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

        $form->handleRequest( $request );
        if ( $form->isSubmitted() && $form->isValid() ) {
            $em = $this->getDoctrine()->getManager();
            foreach ( $checks as $k => $check ) {
                $movimiento = $form['debit_'.$k]->getData();
                $recipientBank = $form['bank_'.$k]->getData();
                if ( !empty( $recipientBank ) || !empty( $movimiento ) ) {
                    if ( !empty( $recipientBank ) ) {
                        $movimiento = new Movimiento();
                        $movimiento
                            ->setBanco( $recipientBank )
                            ->setImporte( $check['amount'] )
                            ->setFecha( $check['creditDate'] )
                            ->setConcepto( 'Acreditacion de cheque '.$check['checkNumber'] )
                        ;
                    } elseif ( !empty( $movimiento ) ) {
                        $movimiento->setConcretado( true );
                    }
                    $em->persist( $movimiento );
                }
            }

            $em->flush();

            $this->markReportAsProcessed($request, $reportFileName);

            return $this->proccessExcelReports( $request );
        }

        return $this->render(
            'admin/process_applied_checks.html.twig',
            [
                'form' => $form->createView(),
                'checks' => $checks,
            ]
        );
    }
}
