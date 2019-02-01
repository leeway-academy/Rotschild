<?php

namespace App\Repository;

use App\Entity\FixedExpense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method FixedExpense|null find($id, $lockMode = null, $lockVersion = null)
 * @method FixedExpense|null findOneBy(array $criteria, array $orderBy = null)
 * @method FixedExpense[]    findAll()
 * @method FixedExpense[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FixedExpenseRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, FixedExpense::class);
    }

//    /**
//     * @return GastoFijo[] Returns an array of GastoFijo objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('g.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?GastoFijo
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
