<?php

namespace App\Controller;

use App\Entity\Banco;
use App\Entity\GastoFijo;
use App\Entity\Movimiento;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Service\ExcelReportsProcessor;

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

    protected function updateGastoFijoEntity( GastoFijo $gastoFijo )
    {
        $hoy = new \DateTimeImmutable();

        foreach ( $gastoFijo->getMovimientos() as $movimiento ) {
            if ( $movimiento->getFecha()->diff( $hoy )->d >= 0 ) {
                $movimiento->setConcepto( $gastoFijo->getConcepto() );
                $movimiento->setImporte( $gastoFijo->getImporte() * -1 );
                $movimiento->setBanco( $gastoFijo->getBanco() );
                $this->em->persist($movimiento);
            }
        }

        $this->em->flush();
    }

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
            $session = $request->getSession();

            $excelReports = [];
            foreach ( $form->getData() as $name => $item ) {
                if ( !is_null($item) && $item->getType() == 'file' && in_array( $item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel' ] ) ) {
                    $fileName = md5(uniqid('', true)).'.'.$item->guessExtension();
                    $item->move( $this->getParameter('reports_path'), $fileName );
                    $excelReports[$name] = $fileName;
                }
            }

            $session->set( 'excelReports', $excelReports );

            return $this->proccessExcelReports( $request );
        }

        return $this->render(
            'admin/import_excel_reports.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @param Request $request
     * Router method (coordinator of Excel Reports processing)
     */
    public function proccessExcelReports( Request $request )
    {
        $session = $request->getSession();
        $reports = $session->get('excelReports');

        if ( !empty( $reports ) ) {
            list( $reportName, $reportFileName ) = [ current( array_keys( $reports ) ), current( $reports ) ];
            if ( substr( $reportName, 0, strlen('BankSummary_') ) === 'BankSummary_' ) {
                $bankId = preg_split('/_/', $reportName )[1];

                return $this->redirectToRoute(
                    'match_bank_summary',
                    [
                        'id' => $bankId,
                        'reportFileName' => $reportFileName
                    ]
                );
            } elseif ( $reportName == 'IssuedChecks' ) {

                return $this->redirectToRoute(
                    'confirm_issued_checks',
                    [
                        'reportFileName' => $reportFileName,
                    ]
                );
            } else {

                return $this->redirectToRoute(
                    'process_applied_checks',
                    [
                        'reportFileName' => $reportFileName,
                    ]
                );
            }
        }

        return $this->redirectToRoute('easyadmin');
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
     * @param Banco $banco
     * @param $reportFileName
     * @Route(path="/banco/{id}/processSummary/{reportFileName}", name="match_bank_summary")
     * @ParamConverter("bank", class="App\Entity\Banco")
     */
    public function matchBankSummary( Request $request, Banco $bank, string $reportFileName )
    {
        $actualTransactions = $this->getExcelReportProcessor()->getBankSummaryTransactions(
            IOFactory::load($this->getParameter('reports_path').DIRECTORY_SEPARATOR.$reportFileName),
            $bank->getXLSStructure()
        );

        $projectedTransactions = $bank->getMovimientos()->filter( function( Movimiento $m ) {
            return !$m->getConcretado();
        } );

        $debits = $bank->getDebitosProyectados();
        $credits = $bank->getCreditosProyectados();

        $formBuilder = $this->createFormBuilder();
        foreach ( $actualTransactions as $k => $t ) {
            if ( $t['amount'] > 0 ) {
                $projectedTransactions = $credits;
            } else {
                $projectedTransactions = $debits;
            }
            $formBuilder->add(
                'match_'.$k,
                ChoiceType::class,
                [
                    'choices' => $projectedTransactions,
                    'choice_label' => function( $choiceValue, $key, $value ) {

                        return $choiceValue.'';
                    },
                    'choice_value' => 'id',
                    'required' => false,
                ]
            );
        }

        $formBuilder->add(
            'submit',
            SubmitType::class,
            [
                'label' => 'Asociar',
            ]
        );

        $form = $formBuilder->getForm();
        $form->handleRequest( $request );
        if ( $form->isSubmitted() && $form->isValid() ) {
            $repo = $this->getDoctrine()->getRepository('App:Movimiento');
            $manager = $this->getDoctrine()->getManager();
            foreach ( $form->getExtraData() as $datum ) {
                if ( $movimiento = $repo->find( $datum ) ) {
                    $movimiento->setConcretado( true );
                    $manager->persist($movimiento);
                }
            }
            $manager->flush();

            $this->markReportAsProcessed($request, $reportFileName);

            return $this->proccessExcelReports( $request );
        } else {

            return $this->render(
                'admin/match_bank_summary.html.twig',
                [
                    'bank' => $bank,
                    'actualTransactions' => $actualTransactions,
                    'form' => $formBuilder->getForm()->createView(),
                ]
            );
        }
    }

    /**
     * @param Request $request
     * @Route(path="/confirmIssuedChecks/{reportFileName}", name="confirm_issued_checks")
     */
    public function confirmIssuedChecks( Request $request, string $reportFileName )
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
     * @Route(path="/processedAppliedChecks/{reportFileName}", name="process_applied_checks")
     */

    public function processAppliedChecks( Request $request, string $reportFileName )
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
                if ( !empty( $form['bank_'.$k] ) ) {
                    $recipientBank = $form['bank_'.$k]->getData();
                    $movimiento = new Movimiento();
                    $movimiento
                        ->setBanco( $recipientBank )
                        ->setImporte( $check['amount'] )
                        ->setFecha( $check['creditDate'] )
                    ;
                } elseif ( !empty( $form['debit_'.$k] ) ) {
                    $movimiento = $form['debit_'.$k]->getData();
                    $movimiento->setConcretado( true );
                }

                $em->persist( $movimiento );
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

    /**
     * @param Request $request
     * @param string $reportFileName
     */
    private function markReportAsProcessed(Request $request, string $reportFileName): void
    {
        $session = $request->getSession();
        $excelReports = array_filter($session->get('excelReports'), function ($e) use ($reportFileName) {
            return $e != $reportFileName;
        });
        $session->set('excelReports', $excelReports);
    }
}
