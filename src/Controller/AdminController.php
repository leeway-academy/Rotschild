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
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;

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

        $fecha = new \DateTime('Yesterday');
        $saldo = $banco->getSaldo($fecha);

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
                    'form' => $form->createView(),
                    'entity' => $saldo,
                    'banco' => $banco->getNombre(),
                    'fecha' => $fecha,
                    'proyectado' => $banco->getSaldo( $fecha )->getValor(),
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
            $banco->addSaldo( $banco->createSaldo( $dia ) );
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
}
