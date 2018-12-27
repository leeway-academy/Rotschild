<?php

namespace App\Repository;

use App\Entity\Movimiento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Doctrine\Common\Collections\Criteria;

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
    public function findPendingDebits()
    {
        return $this->matching(
            Criteria::create()
                ->where( Criteria::expr()->eq('concretado', false) )
                ->andWhere( Criteria::expr()->lt('importe', 0) )
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
        return Criteria::expr()->andX(
            Criteria::expr()->isNull('renglonExtracto' ),
            Criteria::expr()->isNull( 'appliedCheck' )
            );
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
}
