<?php

namespace App\Repository;

use App\Entity\Movimiento;
use App\Entity\Witness;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Validator\Constraints\Collection;

/**
 * @method Movimiento|null find($id, $lockMode = null, $lockVersion = null)
 * @method Movimiento|null findOneBy(array $criteria, array $orderBy = null)
 * @method Movimiento[]    findAll()
 * @method Movimiento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MovimientoRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Movimiento::class);
    }

    /**
     * @return Collection
     */
    public function findNonCheckProjectedDebits()
    {
        return $this->matching(
            Criteria::create()
                ->where( $this->getProjectedCriteria() )
                ->andWhere( $this->getDebitCriteria() )
                ->andWhere( $this->getNonCheckCriteria() )
        );
    }

    /**
     * @return Collection
     */
    public function findProjectedDebits()
    {
        return $this->matching(
            Criteria::create()
                ->where( $this->getProjectedCriteria() )
                ->andWhere( $this->getDebitCriteria() )
        );
    }

    /**
     * @return Collection
     */
    public function findProjectedCredits()
    {
        return $this->matching(
            Criteria::create()
                ->where( $this->getProjectedCriteria() )
                ->andWhere( $this->getCreditCriteria() )
        );
    }

    public function getProjectedCriteria()
    {
        return Criteria::expr()
            ->andX( Criteria::expr()->isNull('witnessClass' ), Criteria::expr()->isNull('witnessId' ) );
    }

    public function getCreditCriteria()
    {
        return Criteria::expr()->gt( 'importe', 0 );
    }

    public function getDebitCriteria()
    {
        return Criteria::expr()->lt( 'importe', 0 );
    }

    /**
     * @return \Doctrine\Common\Collections\Expr\Comparison
     */
    public function getFixedExpenseCriteria()
    {
        return Criteria::expr()->neq( 'clonDe', null );
    }

    /**
     * @return \Doctrine\Common\Collections\Expr\Comparison
     */
    public function getNonCheckCriteria()
    {
        return Criteria::expr()->isNull( 'parentIssuedCheck' );
    }

    /**
     * @param Witness $w
     */
    public function findByWitness( Witness $w )
    {
        return $this->findBy(
            [
                'witnessClass' => get_class($w),
                'witnessId' => $w->getId(),
            ]
        );
    }
}