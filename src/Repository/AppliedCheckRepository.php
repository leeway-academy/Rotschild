<?php

namespace App\Repository;

use App\Entity\AppliedCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Validator\Constraints\Collection;

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

    public function findUnmatched() : Collection
    {

    }
}
