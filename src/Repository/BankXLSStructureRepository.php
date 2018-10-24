<?php

namespace App\Repository;

use App\Entity\BankXLSStructure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method BankXLSStructure|null find($id, $lockMode = null, $lockVersion = null)
 * @method BankXLSStructure|null findOneBy(array $criteria, array $orderBy = null)
 * @method BankXLSStructure[]    findAll()
 * @method BankXLSStructure[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BankXLSStructureRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, BankXLSStructure::class);
    }

//    /**
//     * @return XLSBankStructure[] Returns an array of XLSBankStructure objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('x')
            ->andWhere('x.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('x.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?XLSBankStructure
    {
        return $this->createQueryBuilder('x')
            ->andWhere('x.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
