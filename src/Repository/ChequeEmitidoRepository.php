<?php

namespace App\Repository;

use App\Entity\ChequeEmitido;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ChequeEmitido|null find($id, $lockMode = null, $lockVersion = null)
 * @method ChequeEmitido|null findOneBy(array $criteria, array $orderBy = null)
 * @method ChequeEmitido[]    findAll()
 * @method ChequeEmitido[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChequeEmitidoRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ChequeEmitido::class);
    }

//    /**
//     * @return ChequeEmitido[] Returns an array of ChequeEmitido objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ChequeEmitido
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
