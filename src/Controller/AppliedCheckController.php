<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 12/28/18
 * Time: 1:31 PM
 */

namespace App\Controller;

use App\Entity\AppliedCheck;
use App\Entity\Movimiento;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AppliedCheckController extends AdminController
{
    /**
     * @param Request $request
     * @Route(name="import_applied_checks", path="/import/appliedChecks")
     */
    public function import(Request $request)
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
                if (!is_null($item) && $item->getType() == 'file' ) {
                    if ( in_array($item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'application/octet-stream'])) {
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

                        $this->addFlash(
                            'success',
                            'Cheques aplicados importados'
                        );
                    } else {
                        $this->addFlash(
                            'error',
                            'El informe de Cheques aplicados no tiene el formato correcto'
                        );
                    }
                }
            }
        }

        return $this->render(
            'admin/import_excel_reports.html.twig',
            [
                'form' => $form->createView(),
                'reportName' => 'checks.applied',
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(path="/processedAppliedChecks", name="process_applied_checks")
     */

    public function process(Request $request)
    {
        /*
         * @todo This could be much more intelligent since the destination of the check is part of the
         * Excel file...
         */
        $formBuilder = $this->createFormBuilder();

        $bankRepository = $this->getDoctrine()->getRepository('App:Bank');
        $banks = $bankRepository->findAll();
        $movimientoRepository = $this->getDoctrine()->getRepository('App:Movimiento');
        $debits = $movimientoRepository->findNonCheckProjectedDebits();

        $checks = array_filter( $this
            ->getDoctrine()
            ->getRepository('App:AppliedCheck')
            ->findAll(), function(AppliedCheck $check ) use ( $movimientoRepository ) {
            /**
             * @todo Third condition = isAppliedInternally... could it be refactored into a check method?
             */

            return !($check->isAppliedOutside() || $check->isDeposited() || $movimientoRepository->findByWitness( $check ));
        } );

        $destinationBanks = [];
        foreach ( $banks as $bank ) {
            $destinationBanks[ $bank->getNombre() ] = 'bank_'.$bank->getId();
        }

        $destinationDebits = [];
        foreach ( $debits as $debit ) {
            $destinationDebits[ $debit->getConcepto() ] = 'debit_'.$debit->getId();
        }

        $destinations = [
            'Banks' => $destinationBanks,
            'Debitos proyectados' => $destinationDebits,
            'Other' => [
                'Applied outside' => 'outside',
            ]
        ];

        foreach ($checks as $k => $check) {
            $formBuilder
                ->add(
                    'check_' . $k,
                    ChoiceType::class,
                    [
                        'choices' => $destinations,
                        'required' => false,
                    ]
                )
            ;
        }

        $formBuilder->add(
            'apply',
            SubmitType::class,
            [
                'attr' => [
                    'class' => 'btn btn-primary',
                ]
            ]
        );

        $form = $formBuilder->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            foreach ($checks as $k => $check) {
                $destination = $form['check_'.$k]->getData();

                if ( empty( $destination ) ) {

                    continue;
                }
                if ( $destination == 'outside' ) {
                    $check->setAppliedOutside(true);
                    $em->persist($check);
                } else {
                    $parts = preg_split('/_/', $destination );

                    if ( $parts[0] == 'bank' ) {
                        $recipientBank = $bankRepository->find( $parts[1] );
                        $movimiento = new Movimiento();
                        $movimiento
                            ->setBank($recipientBank)
                            ->setImporte($check->getAmount())
                            ->setFecha($check->getCreditDate())
                            ->setConcepto($this->trans('check.acreditation') . ' ' . $check->getNumber())
                        ;

                        $check->setChildCredit($movimiento);
                        $em->persist($movimiento);
                        $em->persist($check);
                    } elseif ( $parts[0] == 'debit') {
                        $movimiento = $movimientoRepository->find( $parts[1] );
                        $movimiento->setWitness( $check );
                        $em->persist( $movimiento );
                    }
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
}