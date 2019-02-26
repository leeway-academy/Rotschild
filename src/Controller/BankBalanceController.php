<?php

namespace App\Controller;

use App\Entity\Bank;
use App\Entity\Movimiento;
use App\Entity\SaldoBancario;
use Doctrine\Common\Collections\Criteria;
use http\Exception\InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BankBalanceController extends AdminController
{
    /**
     * @deprecated
     */
    private function calculatePastBalances( \DatePeriod $past, array $banks ) : array
    {
        $balances = [];

        foreach ($past as $pastDate) {
            if (count($banks) == 1) {
                $bank = current($banks);
                $pastBalance = $bank->getBalance($pastDate);
                if ( empty( $pastBalance ) ) {
                    $pastBalance = new SaldoBancario();
                    $pastBalance->setValor( 0 );
                } else {
                    $pastBalance = clone $pastBalance;
                }
                $pastBalance->setFecha( $pastDate );
                $balances[] = $pastBalance;
            } else {
                $totalBalance = 0;

                foreach ($banks as $bank) {
                    $balance = $bank->getBalance($pastDate);
                    $totalBalance += $balance ? $balance->getValor() : 0;
                }

                $consolidatedBalance = new SaldoBancario();
                $consolidatedBalance
                    ->setValor( $totalBalance )
                    ->setFecha( $pastDate )
                ;

                $balances[] = clone $consolidatedBalance;
            }
        }

        return $balances;
    }

    /**
     * @param $dateFrom
     * @param $dateTo
     * @param array $banks
     * @return array
     * @throws \Exception
     * @deprecated
     */
    private function calculateBalances(\DateTimeInterface $dateFrom, \DateTimeInterface $dateTo, array $banks): array
    {
        $dateFrom = $dateFrom instanceof \DateTimeImmutable ? $dateFrom : \DateTimeImmutable::createFromMutable($dateFrom);

        $oneDay = new \DateInterval('P1D');
        $today = new \DateTimeImmutable();

        $past = new \DatePeriod($dateFrom, $oneDay, $today <= $dateTo ? $today->sub($oneDay) : $dateTo->add($oneDay));

        $balances = $this->calculatePastBalances( $past, $banks );

        $criteria = new Criteria();
        $criteria
            ->where(Criteria::expr()->gte('fecha', $dateFrom))
            ->andWhere(Criteria::expr()->lte('fecha', $dateTo))
            ->andWhere($this->getDoctrine()->getRepository('App:Movimiento')->getProjectedCriteria())
            ->orderBy(
                [
                    'fecha' => 'ASC'
                ]
            );

        if (count($banks) == 1) {
            $bank = current($banks);
            $criteria->andWhere(Criteria::expr()->eq('bank', $bank));

            $todayBalance = $bank->getBalance($today);
            if ( empty( $todayBalance ) ) {
                $todayBalance = new SaldoBancario();
                $todayBalance->setValor( 0 );
            } else {
                $todayBalance = clone $todayBalance;
            }
            $todayBalance->setFecha( $today );
        } else {
            $totalBalance = 0;

            foreach ($banks as $bank) {
                $balance = $bank->getBalance($today);
                $totalBalance += $balance ? $balance->getValor() : 0;
            }

            $todayBalance = new SaldoBancario();
            $todayBalance
                ->setValor( $totalBalance )
                ->setFecha( $today )
                ;
        }

        $transactions = $this->getDoctrine()->getRepository('App:Movimiento')->matching($criteria);

        $balances[] = $todayBalance;

        $currentBalance = clone $todayBalance;

        $period = new \DatePeriod($today->add( $oneDay ), $oneDay, $dateTo);

        foreach ($period as $date) {
            $currentBalance->setFecha( $date );

            $dailyTransactions = $transactions->filter(function (Movimiento $transaction) use ($date) {

                return $transaction->getFecha()->format('y-m-d') == $date->format('y-m-d');
            });

            $dailyBalance = 0;
            foreach ($dailyTransactions as $transaction) {
                $dailyBalance += $transaction->getImporte();
            }

            $currentBalance->setValor( $currentBalance->getValor() + $dailyBalance );
            $balances[] = $currentBalance;
            $currentBalance = clone last( $balances );
        }

        return $balances;
    }

    /**
     * @param Request $request
     * @Route(name="show_bank_balance", path="/bank/showBalance")
     */
    public function showBankBalance(Request $request)
    {
        $startDate = (new \DateTimeImmutable())->sub( new \DateInterval('P7D') );
        $days = $this->getParameter('projected_balances_days');
        $endDate = $startDate->add(new \DateInterval("P{$days}D"));

        $banks = $this->getDoctrine()->getRepository('App:Bank')->findAll();
        $form = $this
            ->createFormBuilder()
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

        if ($form->isSubmitted() && $form->isValid()) {
            $startDate = $form['dateFrom']->getData();
            $endDate = $form['dateTo']->getData();
        }

        $today = new \DateTimeImmutable('today 00:00:00');
        $yesterday = new \DateTimeImmutable('-1 days 00:00:00');

        return $this->render(
            'admin/show_bank_balance.html.twig',
            [
                'today' => $today,
                'form' => $form->createView(),
                'banks' => $banks,
                'toBeLoadedBalances' => $endDate >= $yesterday && $startDate <= $yesterday,
                'past' => $this->generatePastPeriod( $startDate, $endDate ),
                'future' => $this->generateFuturePeriod( $startDate, $endDate ),
            ]
        );
    }

    private function generateFuturePeriod(\DateTimeInterface $start, \DateTimeInterface $end): ?\DatePeriod
    {
        if ( $end < $start ) {

            throw new InvalidArgumentException('End date must be after start date');
        }

        $firstDay = new \DateTimeImmutable();

        if ( $end < $firstDay ) {

            return null;
        }

        if ( $start < $firstDay ) {
            $start = $firstDay;
        }

        $interval = new \DateInterval('P1D');

        return new \DatePeriod( $start, $interval, $end->add($interval) );
    }

    /**
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @return \DatePeriod|null
     * @throws \Exception
     */
    private function generatePastPeriod( \DateTimeInterface $start, \DateTimeInterface $end ) : ?\DatePeriod
    {
        if ( $end < $start ) {

            throw new InvalidArgumentException('End date must be after start date');
        }

        $lastDay = new \DateTimeImmutable('-1 days 00:00:00');

        if ( $start > $lastDay ) {

            return null;
        }

        if ( $end > $lastDay ) {
            $end = $lastDay;
        }

        return new \DatePeriod( $start, new \DateInterval('P1D'), $end );
    }

    /**
     * @param Request $request
     * @Route(name="send_bank_balance", path="/bank/sendBalance", options={"expose"=true})
     */
    public function sendBankBalance(Request $request)
    {
        $dateFrom = new \DateTimeImmutable($request->get('dateFrom'));
        $days = $this->getParameter('projected_balances_days');
        $dateTo = $request->get('dateTo') ? new \DateTimeImmutable($request->get('dateTo')) : $dateFrom->add(new \DateInterval("P{$days}D"));
        $bank = $request->get('bank');

        $banks = $bank ? [$this->getDoctrine()->getRepository('App:Bank')->find($bank)] : $this->getDoctrine()->getRepository('App:Bank')->findAll();

        $balances = $this->calculateBalances($dateFrom, $dateTo, $banks);

        $message = (new \Swift_Message($this->get('translator')->trans('Bank balances summary')))
            ->setFrom('rotschild@blasting.com.ar')
            ->setTo($this->getParameter('send_balances_to'))
            ->setBody(
                $this->renderView(
                    'emails/balances.html.twig',
                    [
                        'banks' => $banks,
                        'balances' => $balances,
                    ]
                ),
                'text/html'
            );

        $this->get('mailer')->send($message);

        return new JsonResponse($this->get('translator')->trans('Email sent!'));
    }

    /**
     * @Route(name="load_bank_balance",path="/bank/{id}/loadBalance/{dateString}", options={"expose"=true})
     * @ParamConverter(class="App\Entity\Bank", name="bank")
     */
    public function loadBalance(Request $request, Bank $bank, string $dateString )
    {
        $date = new \DateTimeImmutable( $dateString );
        $em = $this->getDoctrine()->getManager();

        $startDate = $date->sub(new \DateInterval('P1D'));
        $initialBalance = $bank->getLastActualBalanceBefore($startDate);

        if ( empty($initialBalance) ) {
            $initialBalance = $bank->createBalance( $date, 0 );
        }

        $finalExpectedBalance = clone $initialBalance;
        $finalExpectedBalance->setFecha( $date );

        $transactionsBetween = $bank->getTransactionsBetween( $initialBalance ? $initialBalance->getFecha() : $startDate, $date, true);

        foreach ( $transactionsBetween as $transaction ) {
            $finalExpectedBalance->setValor( $finalExpectedBalance->getValor() + $transaction->getImporte() );
        }

        $actualBalance = $bank->getPastActualBalance($date);

        if ( empty($actualBalance) ) {
            $actualBalance = new SaldoBancario();
            $actualBalance
                ->setFecha( $date )
                ->setBank( $bank )
                ->setValor( $finalExpectedBalance->getValor() )
            ;
        }

        $form = $this
            ->createFormBuilder($actualBalance)
            ->setAttribute('class', 'form-horizontal new-form')
            ->add('valor', MoneyType::class,
                [
                    'label' => 'Saldo real',
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

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
//            $actualBalance->setDiferenciaConProyectado($finalExpectedBalance->getValor() - $actualBalance->getValor());
            $em->persist($actualBalance);
            $em->flush();

            return $this->redirectToRoute(
                'show_bank_balance',
                [
                ]
            );
        }

        return $this->render(
            'admin/load_bank_balance.html.twig',
            [
                'form' => $form->createView(),
                'bank' => $bank,
                'fecha' => $date,
                'transactions' => $transactionsBetween,
                'initialBalance' => $initialBalance,
                'finalBalance' => $finalExpectedBalance,
            ]
        );
    }
}