<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 12/28/18
 * Time: 1:31 PM
 */

namespace App\Controller;

use App\Entity\Movimiento;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AppliedCheckController extends BaseAdminController
{
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

        $bancos = $this->getDoctrine()->getRepository('App:Bank')->findAll();
        $debits = $this->getDoctrine()->getRepository('App:Movimiento')->findProjectedCredits();
        $checks = $this->getDoctrine()->getRepository('App:AppliedCheck')->findAll();

        foreach ($checks as $k => $check) {
            if ( $check->isMatched() ) {
                unset( $checks[$k] );

                continue;
            }
            $formBuilder
                ->add(
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
                )
                ->add(
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
                )
                ->add(
                    'outside_'.$k,
                    CheckboxType::class,
                    [
                        'required' => false,
                        'label' => 'check.applied.outside',
                        'attr' => [
                            'class' => 'check_outside',
                        ]
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
                $movimiento = $form['debit_' . $k]->getData();
                $recipientBank = $form['bank_' . $k]->getData();
                $outside = $form['outside_' . $k]->getData();
                if (!empty($recipientBank) || !empty($movimiento)) {
                    if (!empty($recipientBank)) {
                        $movimiento = new Movimiento();
                        $movimiento
                            ->setBank($recipientBank)
                            ->setImporte($check->getAmount())
                            ->setFecha($check->getCreditDate())
                            ->setConcepto('Acreditacion de cheque ' . $check->getNumber())
                        ;

                        $check->setChildCredit($movimiento);
                        $em->persist($movimiento);
                        $em->persist($check);
                    } elseif (!empty($movimiento)) {
                        $movimiento->setWitness( $check );
                        $em->persist( $movimiento );
                    }
                } elseif ( $outside ) {
                    $check->setAppliedOutside( true );
                    $em->persist($check);
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