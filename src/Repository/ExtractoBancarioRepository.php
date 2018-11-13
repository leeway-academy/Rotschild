<?php

namespace App\Repository;

use App\Entity\Banco;
use App\Entity\ExtractoBancario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ExtractoBancario|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExtractoBancario|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExtractoBancario[]    findAll()
 * @method ExtractoBancario[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExtractoBancarioRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ExtractoBancario::class);
    }

//    /**
//     * @return ExtractoBancario[] Returns an array of ExtractoBancario objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ExtractoBancario
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
