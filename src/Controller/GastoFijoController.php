<?php

namespace App\Controller;

use App\Entity\GastoFijo;
use App\Entity\Movimiento;
use Doctrine\Common\Collections\Criteria;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GastoFijoController extends BaseAdminController
{
    /**
     * @Route("/fixedExpense/query", name="fixed_expense_query")
     */
    public function index( Request $request )
    {
        $today = new \DateTimeImmutable();
        $month = $request->get('month', $today->format('m'));
        $year = $request->get('year', $today->format('y'));

        $firstDay = new \DateTime($year.'-'.$month.'-01');
        $lastDay = new \DateTime( $firstDay->format('Y-m-t') );

        $movimientoRepository = $this->getDoctrine()->getRepository('App:Movimiento');
        $gastosFijos = $movimientoRepository
            ->matching(
                Criteria::create()->where( $movimientoRepository->getFixedExpenseCriteria() )
                ->andWhere( Criteria::expr()->gte('fecha', $firstDay ) )
                ->andWhere( Criteria::expr()->lte('fecha', $lastDay ) )
            );

        return $this->render('gasto_fijo/query.html.twig', [
            'fixedExpenses' => $gastosFijos,
        ]);
    }

    /**
     * @param GastoFijo $gastoFijo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function persistGastoFijoEntity(GastoFijo $gastoFijo)
    {
        $this->em->persist($gastoFijo);
        $this->em->flush();

        $initDate = \DateTimeImmutable::createFromMutable( $gastoFijo->getFechaInicio() );

        $oneMonth = new \DateInterval('P1M');

        if ( $initDate->format('d') > $gastoFijo->getDia() ) {
            $initDate = $initDate
                ->sub( new \DateInterval('P'.($initDate->format('d') - $gastoFijo->getDia() ).'D') )
                ->add( $oneMonth );
        } else {
            $initDate = $initDate
                ->add( new \DateInterval('P'.( $gastoFijo->getDia() - $initDate->format('d') ).'D') );
        }

        if (($fechaFin = $gastoFijo->getFechaFin()) === null) {
            $fechaFin = $initDate->add(new \DateInterval('P12M'));
        }

        while ($initDate < $fechaFin) {
            $movimiento = new Movimiento();
            $movimiento
                ->setConcepto($gastoFijo->getConcepto())
                ->setFecha($initDate)
                ->setImporte($gastoFijo->getImporte() * -1)
                ->setBank($gastoFijo->getBank())
                ->setClonDe($gastoFijo);

            $this->em->persist($movimiento);
            $initDate = $initDate->add($oneMonth);
        }


        $this->em->flush();
    }

    /**
     * @param GastoFijo $gastoFijo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function updateGastoFijoEntity(GastoFijo $gastoFijo)
    {
        $today = new \DateTimeImmutable();

        foreach ($gastoFijo->getMovimientos() as $movimiento) {
            if ( $movimiento->getFecha() > $today ) {
                if (
                    !empty($gastoFijo->getFechaFin()) && $movimiento->getFecha() > $gastoFijo->getFechaFin()
                    ||
                    $movimiento->getFecha() < $gastoFijo->getFechaInicio()
                ) {
                    $this->em->remove( $movimiento );
                } else {
                    $movimiento->setConcepto($gastoFijo->getConcepto());
                    $movimiento->setImporte($gastoFijo->getImporte() * -1);
                    $movimiento->setBank($gastoFijo->getBank());
                    $this->em->persist($movimiento);
                }
            }
        }

        $this->em->flush();
    }

    /**
     * @param GastoFijo $gastoFijo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function removeGastoFijoEntity(GastoFijo $gastoFijo)
    {
        $today = new \DateTimeImmutable();

        foreach ($gastoFijo->getMovimientos() as $movimiento) {
            if ($movimiento->getFecha() == $today ) {
                $this->em->remove($movimiento);
            }
        }

        $this->em->remove($gastoFijo);
        $this->em->flush();
    }
}
