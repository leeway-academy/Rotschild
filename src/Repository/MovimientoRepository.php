<?php

namespace App\Repository;

use App\Entity\Movimiento;
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
    public function findPendingDebits()
    {
        return $this->matching(
            Criteria::create()
                ->where( Criteria::expr()->eq('concretado', false) )
                ->andWhere( Criteria::expr()->lt('importe', 0) )
        );
    }

    /**
     * @return \Doctrine\Common\Collections\Expr\Comparison
     */
    public function getFixedExpenseCriteria()
    {
        return Criteria::expr()->neq( 'clonDe', null );
    }
}
