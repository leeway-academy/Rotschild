<?php

namespace App\Repository;

use App\Entity\GastoFijo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method GastoFijo|null find($id, $lockMode = null, $lockVersion = null)
 * @method GastoFijo|null findOneBy(array $criteria, array $orderBy = null)
 * @method GastoFijo[]    findAll()
 * @method GastoFijo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GastoFijoRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, GastoFijo::class);
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
