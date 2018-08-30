<?php

namespace App\Repository;

use App\Entity\SaldoBancario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method SaldoBancario|null find($id, $lockMode = null, $lockVersion = null)
 * @method SaldoBancario|null findOneBy(array $criteria, array $orderBy = null)
 * @method SaldoBancario[]    findAll()
 * @method SaldoBancario[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SaldoHistoricoRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, SaldoBancario::class);
    }

//    /**
//     * @return SaldoBancario[] Returns an array of SaldoBancario objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?SaldoBancario
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
