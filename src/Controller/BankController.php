<?php

namespace App\Controller;

use App\Entity\Bank;
use App\Entity\Movimiento;
use App\Entity\RenglonExtracto;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BankController extends BaseAdminController
{
    /**
     * @Route(name="match_bank_summary_lines", path="/bank/{id}/match_summary_lines")
     * @ParamConverter(name="bank", class="App\Entity\Bank")
     * @param Bank $bank
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function matchSummaryLines( Bank $bank, Request $request )
    {
        $existingTxPefix = 'transaction';
        $newTxPrefix = 'new_tx';

        $summaryLines = [
            'credits' => [],
            'debits' => []
        ];

        $projectedCredits = $bank->getCreditosProyectados();
        $projectedDebits = $bank->getDebitosProyectados();

        /**
         * @todo Externalize this concepts into a db table
         */
        $newCreditConcepts = [
            '-1' => 'payment.received',
            '-2' => 'return.tax',
            '-3' => 'bank.comission.return',
        ];

        $newDebitConcepts = [
            '-4' => 'bank.comission.charge',
            '-5' => 'tax',
            '-6' => 'investment.fixed_term',
            '-7' => 'fines',
            '-8' => 'fees',
            '-9' => 'salary.advances',
            '-10' => 'transfer.own_account',
            '-11' => 'expenses.shareholders',
        ];

        $formBuilder = $this->createFormBuilder();
        $dateFrom = $request->get('dateFrom') ? new \DateTimeImmutable( $request->get('dateFrom')['date'] ) : null;
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable( $request->get('dateTo')['date'] ) : null;

        $movimientosRepo = $this->getDoctrine()->getRepository('App:Movimiento');

        foreach ( $bank->getExtractos() as $extracto ) {
            $lines = $extracto->getRenglones()->filter(function (RenglonExtracto $r) use ($dateFrom, $dateTo) {

                $ret = ( empty($dateFrom) || $r->getFecha() >= $dateFrom ) && ( empty($dateTo) || $r->getFecha() <= $dateTo );

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
                            'choices' => $renglon->getImporte() > 0 ? $projectedCredits : $projectedDebits,
                            'choice_value' => function (Movimiento $movimiento = null) {

                                return $movimiento ? $movimiento->getId() : '';
                            },
                            'choice_label' => function (Movimiento $movimiento) {

                                return $movimiento->__toString();
                            },
                            'label' => $renglon->getFecha()->format('d/m/Y') . ': ' . $renglon->getConcepto() . ' ' . $renglon->getImporte(),
                            'required' => false,
                            'multiple' => $renglon->getImporte() > 0,
                        ]
                    )
                    ->add(
                        $newTxPrefix.'_'.$renglon->getId(),
                        ChoiceType::class,
                        [
                            'choices' => $renglon->getImporte() > 0 ? $newCreditConcepts : $newDebitConcepts,
                            'choice_label' => function ($choiceValue, $key, $value) {

                                return $choiceValue . '';
                            },
                            'choice_value' => function ( $v ) use ( $newDebitConcepts, $newCreditConcepts ) {

                                return in_array( $v, $newDebitConcepts ) ? array_search( $v, $newDebitConcepts) : array_search( $v, $newCreditConcepts);
                            },
                            'required' => false,
                        ]
                    )
                ;
                $summaryLines[ $renglon->getImporte() > 0 ? 'credits' : 'debits' ][ $renglon->getId() ] = $renglon;
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
                'summaryLines' => $summaryLines,
                'newCreditConcepts' => $newCreditConcepts,
            ]
        );
    }
}
