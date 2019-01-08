<?php

namespace App\Controller;

use App\Entity\Bank;
use App\Entity\Movimiento;
use App\Entity\SaldoBancario;
use App\Service\ExcelReportsProcessor;
use Doctrine\Common\Collections\Criteria;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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

    /**
     * @param Request $request
     * @Route(path="/bank/matchSummaries", name="match_bank_summaries", options={"expose"=true})
     */
    public function matchBankSummaries(Request $request)
    {
        $em = $this->getDoctrine();
        $banks = $em->getRepository('App:Bank')->findAll();
        $bank = $request->get('bankId') ? $banks[$request->get('bankId')] : current($banks);
        $filterForm = $this
            ->createFormBuilder(
                null,
                [
                    'allow_extra_fields' => true,
                ]
            )
            ->add(
                'bank',
                ChoiceType::class,
                [
                    'choices' => $banks,
                    'choice_label' => function (Bank $b) {

                        return $b->__toString();
                    },
                    'choice_value' => function (Bank $b = null) {

                        return !empty($b) ? $b->getId() : null;
                    },
                    'data' => $bank,
                ]
            )
            ->add(
                'dateFrom',
                DateType::class,
                [
                    'required' => false,
                ]
            )
            ->add(
                'dateTo',
                DateType::class,
                [
                    'required' => false,
                ]
            )
            ->add(
                'filter',
                SubmitType::class,
                [
                    'label' => 'Filter',
                    'attr' =>
                        [
                            'class' => 'btn btn-primary',
                        ]
                ]
            )
            ->getForm();

        $filterForm->handleRequest($request);

        $dateFrom = $dateTo = null;
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $bank = $filterForm['bank']->getData();
            $dateFrom = $filterForm['dateFrom']->getData();
            $dateTo = $filterForm['dateTo']->getData();
        }

        return $this->render(
            'admin/match_bank_summaries.html.twig',
            [
                'filterForm' => $filterForm->createView(),
                'bank' => $bank,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(name="show_bank_balance", path="/bank/showBalance")
     */
    public function showBankBalance(Request $request)
    {
        $startDate = new \DateTimeImmutable();
        $days = $this->getParameter('projected_balances_days');
        $endDate = $startDate->add(new \DateInterval("P{$days}D"));

        $banks = $this->getDoctrine()->getRepository('App:Bank')->findAll();
        $form = $this
            ->createFormBuilder()
            ->add(
                'bank',
                ChoiceType::class,
                [
                    'choices' => $banks,
                    'required' => false,
                    'choice_label' => function (Bank $b) {

                        return $b->__toString();
                    },
                    'choice_value' => function (Bank $b = null) {

                        return $b ? $b->getId() : '';
                    },
                ]
            )
            ->add(
                'dateFrom',
                DateType::class,
                [
                    'data' => $startDate,
                ]
            )
            ->add(
                'dateTo',
                DateType::class,
                [
                    'data' => $endDate,
                ]
            )
            ->add(
                'Submit',
                SubmitType::class,
                [
                    'label' => 'Query',
                    'attr' =>
                        [
                            'class' => 'btn btn-primary',
                        ]
                ]
            )
            ->getForm();

        $form->handleRequest($request);

        $balances = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $dateFrom = $form['dateFrom']->getData();
            $dateTo = $form['dateTo']->getData();
            $bank = $form['bank']->getData();

            $balances = $this->calculateBanksBalance( $dateFrom, $dateTo, $bank ? [ $bank ] : $banks );
        }

        return $this->render(
            'admin/show_bank_balance.html.twig',
            [
                'form' => $form->createView(),
                'balances' => $balances,
            ]
        );
    }

    /**
     * @param Request $request
     * @Route(name="send_bank_balance", path="/bank/sendBalance", options={"expose"=true})
     */
    public function sendBankBalance( Request $request )
    {
        $dateFrom = new \DateTimeImmutable( $request->get('dateFrom') );
        $days = $this->getParameter('projected_balances_days');
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable( $request->get('dateTo') ) : $dateFrom->add( new \DateInterval("P{$days}D") );
        $bank = $request->get('bank');

        $banks = $bank ? [ $this->getDoctrine()->getRepository('App:Bank')->find( $bank ) ] : $this->getDoctrine()->getRepository('App:Bank')->findAll();

        $balances = $this->calculateBanksBalance( $dateFrom, $dateTo, $banks );

        $message = ( new \Swift_Message($this->get('translator')->trans('Bank balances summary') ) )
            ->setFrom('rotschild@blasting.com.ar')
            ->setTo( $this->getParameter('send_balances_to'))
            ->setBody(
                $this->renderView(
                    'emails/balances.html.twig',
                    [
                        'banks' => $banks,
                        'balances' => $balances,
                    ]
                ),
                'text/html'
            )
        ;

        $this->get('mailer')->send($message);

        return new JsonResponse($this->get('translator')->trans('Email sent!'));
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
     * @param $dateFrom
     * @param $dateTo
     * @param array $banks
     * @return array
     * @throws \Exception
     */
    private function calculateBanksBalance( \DateTimeInterface $dateFrom, \DateTimeInterface $dateTo, array $banks ): array
    {
        $criteria = new Criteria();
        $criteria
            ->where(Criteria::expr()->gte('fecha', $dateFrom))
            ->andWhere(Criteria::expr()->lte('fecha', $dateTo))
            ->andWhere(Criteria::expr()->isNull('renglonExtracto' ))
            ->orderBy(
                [
                    'fecha' => 'ASC'
                ]
            );

        $dateFrom = $dateFrom instanceof \DateTimeImmutable ? $dateFrom : \DateTimeImmutable::createFromMutable($dateFrom);

        if ( count($banks) == 1 ) {
            $bank = current($banks);
            $criteria->andWhere( Criteria::expr()->eq('bank', $bank) );

            $balance = $bank->getBalance( $dateFrom );
            $totalBalance = $balance ? $balance->getValor() : 0;
        } else {
            $totalBalance = 0;

            foreach ($banks as $bank) {
                $balance = $bank->getBalance($dateFrom);

                $totalBalance += $balance ? $balance->getValor() : 0;
            }
        }

        $transactions = $this->getDoctrine()->getRepository('App:Movimiento')->matching($criteria);

        $period = new \DatePeriod($dateFrom, new \DateInterval('P1D'), $dateTo);

        $balances = [];
        foreach ($period as $date) {
            $dailyTransactions = $transactions->filter(function (Movimiento $transaction) use ($date) {

                return $transaction->getFecha()->diff($date)->days == 0;
            });

            $dailyBalance = 0;
            foreach ($dailyTransactions as $transaction) {
                $dailyBalance += $transaction->getImporte();
            }

            $totalBalance += $dailyBalance;
            $balances[$date->format('d/m/Y')] = $totalBalance;
        }

        return $balances;
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
