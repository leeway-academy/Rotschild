<?php

namespace App\Controller;

use App\Entity\ChequeEmitido;
use App\Entity\Movimiento;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class IssuedCheckController extends AdminController
{
    /**
     * @param Request $request
     * @Route(name="import_issued_checks", path="/import/issuedChecks")
     */
    public function import(Request $request)
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
                if (!is_null($item) && $item->getType() == 'file') {
                    if (in_array($item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'application/octet-stream'])) {
                        $fileName = 'IssuedChecks_' . (new \DateTimeImmutable())->format('d-m-y') . '.' . $item->guessExtension();
                        $item->move($this->getParameter('reports_path'), $fileName);

                        $lines = $this->getExcelReportProcessor()->getIssuedChecks(
                            IOFactory::load($this->getParameter('reports_path') . DIRECTORY_SEPARATOR . $fileName)
                        );

                        foreach ($lines as $k => $line) {
                            $issuedCheck = new ChequeEmitido();
                            $issuedCheck
                                ->setImporte($line['amount'])
                                ->setFecha($line['date'])
                                ->setBanco($em->getRepository('App:Bank')->findOneBy(['codigo' => $line['bankCode']]))
                                ->setNumero($line['checkNumber']);

                            $checkDebit = $issuedCheck->createChildDebit();
                            $em->persist($checkDebit);
                            $em->persist($issuedCheck);
                        }

                        $em->flush();

                        $this->addFlash(
                            'success',
                            'Cheques emitidos importados'
                        );
                    } else {
                        $this->addFlash(
                            'error',
                            'El archivo enviado no tiene el formato correcto'
                        );
                    }
                }
            }
        }

        return $this->render(
            'admin/import_excel_reports.html.twig',
            [
                'form' => $form->createView(),
                'reportName' => 'checks.issued',
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(path="/checks/issued/process", name="process_issued_checks")
     */
    public function process(Request $request)
    {
        $formBuilder = $this->createFormBuilder();

        $managerRegistry = $this->getDoctrine();

        $transactionRepository = $managerRegistry
            ->getRepository('App:Movimiento');

        $debits = $transactionRepository
            ->findNonCheckProjectedDebits();

        $chequeEmitidoRepository = $managerRegistry
            ->getRepository('App:ChequeEmitido');
        $nonProcessed = $chequeEmitidoRepository
            ->findNonProcessed()
        ;

        $processed = array_filter(
            $chequeEmitidoRepository
                ->findProcessed(),
            function( ChequeEmitido $c ) use ( $transactionRepository ) {

                return empty($transactionRepository->findByWitness( $c ));
            });

        $nullOptions = [
            '-1' => 'Payment to providers',
            '-2' => 'N/A',
        ];

        foreach ($nonProcessed as $k => $check) {
            $formBuilder->add(
                'match_' . $check->getId(),
                ChoiceType::class,
                [
                    'choices' => [
                        'Gasto no proyectado' => $nullOptions,
                        'Gasto proyectado' => $debits->toArray(),
                    ],
                    'choice_value' => function ($o) use ($nullOptions) {
                        if ($o instanceof Movimiento) {

                            return $o->getId();
                        } elseif (in_array($o, $nullOptions)) {

                            return array_search($o, $nullOptions);
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
                    'label' => $this->trans('Confirm'),
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
                if ($datum) {
                    $parts = preg_split('/_/', $k);
                    $k = $parts[1];
                    $check = current(array_filter($nonProcessed, function (ChequeEmitido $c) use ($k) {

                        return $c->getId() == $k;
                    }));

                    if ($datum instanceof Movimiento) {
                        /**
                         * If it's an existing transaction this check marks it as payed
                         */
                        $datum->setWitness($check);
                        $objectManager->persist($datum);
                    }

                    $check->setProcessed(true);
                }
            }

            $objectManager->flush();

            return $this->redirectToRoute('process_issued_checks');
        }

        return $this->render(
            'admin/process_issued_checks.html.twig',
            [
                'form' => $form->createView(),
                'nonProcessed' => $nonProcessed,
                'processed' => $processed,
            ]
        );
    }

    /**
     * @Route(name="mark_issued_check_unprocessed", path="/checks/issued/{id}/mark_unprocessed")
     * @param ChequeEmitido $chequeEmitido
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function markUnProcessed( ChequeEmitido $chequeEmitido )
    {
        $chequeEmitido->makeAvailable();
        $entityManager = $this
            ->getDoctrine()
            ->getManager();
        $entityManager
            ->persist($chequeEmitido)
            ;

        $entityManager->flush();

        return $this->redirectToRoute('process_issued_checks');
    }
}