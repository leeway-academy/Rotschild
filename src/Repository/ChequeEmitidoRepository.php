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

    /**
     * @return mixed
     */
    public function findNonProcessed()
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.processed = :val')
            ->setParameter('val', false )
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return mixed
     */
    public function findProcessed()
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.processed = :val')
            ->setParameter('val', true )
            ->getQuery()
            ->getResult()
            ;
    }
}
