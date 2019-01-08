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

class IssuedChecksController extends AdminController
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
                    if (in_array($item->getMimeType(), ['application/wps-office.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'application/octet-stream' ])) {
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

        $debits = $managerRegistry
            ->getRepository('App:Movimiento')
            ->findNonCheckProjectedDebits();

        $issuedChecks = $managerRegistry
            ->getRepository('App:ChequeEmitido')
            ->findAll();

        $nullOption = [ '-1' => 'Payment to providers' ];

        foreach ($issuedChecks as $k => $check) {
            if ( $check->getChildDebit() ) {
                unset( $issuedChecks[$k] );

                continue;
            }
            $formBuilder->add(
                'match_' . $check->getId(),
                ChoiceType::class,
                [
                    'choices' => array_merge( $nullOption, $debits->toArray() ),
                    'choice_value' => function( $o ) use ( $nullOption ) {
                        if ( $o instanceof Movimiento ) {

                            return $o->getId();
                        } elseif ( $o == $nullOption[ '-1' ] ) {

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
                if ($datum) {
                    $parts = preg_split('/_/', $k);
                    $k = $parts[1];
                    $check = current(array_filter( $issuedChecks, function( ChequeEmitido $c ) use ( $k ) {

                        return $c->getId() == $k;
                    }));
                    /**
                     * A new debit is created for the check itself
                     */
                    $checkDebit = $check->createChildDebit();
                    $objectManager->persist( $checkDebit );

                    if ( $datum instanceof Movimiento ) {
                        /**
                         * If it's an existing transaction this check marks it as payed
                         */
                        $datum->setWitness( $check );
                        $objectManager->persist( $datum );
                    }

                    $objectManager->persist($check);
                }
            }

            $objectManager->flush();

            return $this->redirectToRoute('process_issued_checks');
        }

        return $this->render(
            'admin/process_issued_checks.html.twig',
            [
                'form' => $form->createView(),
                'checks' => $issuedChecks,
            ]
        );
    }
}
