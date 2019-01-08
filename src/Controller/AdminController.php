<?php

namespace App\Controller;

use App\Entity\Bank;
use App\Entity\SaldoBancario;
use App\Service\ExcelReportsProcessor;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
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
     * @param $msg
     * @return string
     */
    protected function trans( $msg ) : string
    {
        return $this->get('translator')->trans( $msg );
    }
}
