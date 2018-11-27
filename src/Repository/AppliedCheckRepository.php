<?php

namespace App\Repository;

use App\Entity\AppliedCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method AppliedCheck|null find($id, $lockMode = null, $lockVersion = null)
 * @method AppliedCheck|null findOneBy(array $criteria, array $orderBy = null)
 * @method AppliedCheck[]    findAll()
 * @method AppliedCheck[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppliedCheckRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, AppliedCheck::class);
    }

//    /**
//     * @return AppliedCheck[] Returns an array of AppliedCheck objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?AppliedCheck
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
