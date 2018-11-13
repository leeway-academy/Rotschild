<?php

namespace App\Repository;

use App\Entity\RenglonExtracto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method RenglonExtracto|null find($id, $lockMode = null, $lockVersion = null)
 * @method RenglonExtracto|null findOneBy(array $criteria, array $orderBy = null)
 * @method RenglonExtracto[]    findAll()
 * @method RenglonExtracto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RenglonExtractoRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, RenglonExtracto::class);
    }

//    /**
//     * @return RenglonExtracto[] Returns an array of RenglonExtracto objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?RenglonExtracto
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
