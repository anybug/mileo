<?php

namespace App\Repository;

use App\Entity\ReportLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @method ReportLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReportLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReportLine[]    findAll()
 * @method ReportLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReportLineRepository extends ServiceEntityRepository
{
    private $security;

    public function __construct(ManagerRegistry $registry, Security $security)
    {
        $this->security = $security;
        parent::__construct($registry, ReportLine::class);
    }

    public function getLineForUser()
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.report', 're')
            ->andWhere('re.user = (:user)')
            ->setParameter('user', $this->security->getUser())
            ->orderBy('r.travel_date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getLastLineForUser()
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.report', 're')
            ->andWhere('re.user = (:user)')
            ->setParameter('user', $this->security->getUser())
            ->orderBy('r.travel_date', 'DESC')
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult()
        ;
    }

    public function getLineForReports($reports)
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.report', 're')
            ->andWhere('re.user = (:user)')
            ->setParameter('user', $this->security->getUser())
            ->andWhere('r.report IN (:reports)')
            ->setParameter('reports', $reports)
            ->orderBy('r.travel_date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }



    // /**
    //  * @return ReportLine[] Returns an array of ReportLine objects
    //  */
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
    public function findOneBySomeField($value): ?ReportLine
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
