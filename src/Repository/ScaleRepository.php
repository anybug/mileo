<?php

namespace App\Repository;

use App\Entity\Scale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Scale|null find($id, $lockMode = null, $lockVersion = null)
 * @method Scale|null findOneBy(array $criteria, array $orderBy = null)
 * @method Scale[]    findAll()
 * @method Scale[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ScaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scale::class);
    }

    // /**
    //  * @return Scale[] Returns an array of Scale objects
    //  */
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
    public function findOneBySomeField($value): ?Scale
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function getByKmAndPower($km, $power_id, $year)    
    {
        $qb = $this->createQueryBuilder('s')      
                    ->where('s.power= :power_id')
                    ->andWhere('s.km_min <= :km')
                    ->andWhere('s.km_max >= :km')
                    ->andWhere('s.year = :year')

                    ->setParameter('power_id', $power_id)
                    ->setParameter('km', $km)
                    ->setParameter('year', $year);

                    //->setMaxResults( 1 );

        return $qb->getQuery()->getOneOrNullResult();
    }
}
