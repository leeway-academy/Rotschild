<?php

namespace App\Controller;

use App\Entity\Banco;
use App\Entity\SaldoBancario;
use http\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;

class AdminController extends BaseAdminController
{
    /**
     * @param Banco $banco
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

        $saldo = new SaldoBancario();
        $fecha = new \DateTime();
        $saldo->setFecha($fecha);
        $saldo->setBanco($banco);

        $form = $this
            ->createFormBuilder( $saldo )
            ->setAttribute('class', 'form-horizontal  new-form')
            ->add( 'valor', NumberType::class )
            ->add('Guardar cambios', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        if ( $form->isSubmitted() && $form->isValid() ) {
            $em->persist( $saldo );
            $em->flush();

            return $this->redirectToRoute('easyadmin', array('entity' => 'Banco', 'action' => 'list'));
        } else {

            return $this->render(
                'admin/cargar_saldo.html.twig',
                [
                    'form' => $form
                        ->createView(),
                    'entity' => $saldo,
                    'banco' => $banco->getNombre(),
                    'fecha' => $fecha,
                ]
            );
        }
    }
}
