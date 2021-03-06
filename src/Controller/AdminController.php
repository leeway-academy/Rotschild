<?php

namespace App\Controller;

use App\Service\ExcelReportsProcessor;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Flex\Response;

class AdminController extends BaseAdminController
{
    private $excelReportsProcessor;

    public function __construct(ExcelReportsProcessor $excelReportProcessor)
    {
        $this->setExcelReportProcessor($excelReportProcessor);
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

    protected function editDebitAction()
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

    protected function editCreditAction()
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

    public function undoDebitAction()
    {
        $id = $this->request->query->get('id');
        $objectManager = $this->getDoctrine()->getManager();

        $debit = $objectManager->getRepository('App:Movimiento')->find( $id );

        // @todo Really ugly hack... this should be done inside the dissociate action... I didn't know how to propagate the saving though :(
        if ( $wc = $debit->getWitnessClass() ) {
            $witnessRepo = $objectManager->getRepository( $wc );

            if ( $witness = $witnessRepo->find( $debit->getWitnessId() ) ) {
                $witness->makeAvailable();
                $objectManager->persist($witness);
            }
        }

        $debit->dissociate();

        $objectManager->persist($debit);
        $objectManager->flush();

        return $this->redirectToRoute('easyadmin', [
            'entity' => 'Debit',
            'action' => 'list',
        ]);
    }

    public function undoCreditAction()
    {
        $id = $this->request->query->get('id');
        $objectManager = $this->getDoctrine()->getManager();

        $credit = $objectManager->getRepository('App:Movimiento')->find( $id );

        $credit->dissociate();
        $objectManager->persist($credit);
        $objectManager->flush();

        return $this->redirectToRoute('easyadmin', [
            'entity' => 'Credit',
            'action' => 'list',
        ]);
    }
}