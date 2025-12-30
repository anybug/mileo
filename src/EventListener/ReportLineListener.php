<?php

namespace App\EventListener;

use App\Entity\Report;
use App\Entity\ReportLine;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class ReportLineListener
{
    private $security;
    private $report;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $entity = $args->getObject();

        if($entity instanceof ReportLine)
        {
            $user = $this->security->getUser();
            $reports = $em->getRepository(Report::class)->findByUser(['user' => $user]);
            $date = $entity->getTravelDate();

            $report = null;
            foreach ($reports as $r) {
                if ($r->getStartDate() <= $date && $r->getEndDate() >= $date) {
                    $report = $r;
                }
            }

            if($report == null){
                $report = new Report;
                $report->setUser($user);
                $startMonth =  \DateTime::createFromFormat("Y-m-d",$date->format('Y-m-d'));
                $startMonth->modify('first day of this month');
                $endMonth =  \DateTime::createFromFormat("Y-m-d",$date->format('Y-m-d'));
                $endMonth->modify('last day of this month');
                $report->setStartDate($startMonth);
                $report->setEndDate($endMonth);
                $report->setCreatedAt(new \DateTimeImmutable());
                //$em->persist($report);
            }

            $report->addLine($entity);
            $report->setUpdatedAt(new \DateTimeImmutable());

            /*if($entity->getReport()){
                $scale = $entity->getReport()->isVehiculeInVehiculeReport($entity->getVehicule())->getScale();
            }else{
                $scale = $entity->getVehicule()->getScale();
            }*/

            $scale = $entity->getVehicule()->getScale();

            $entity->setScale($scale);
            $entity->calculateAmount();

            $entity->setCreatedAt(new \DateTimeImmutable());
            $entity->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if($entity instanceof ReportLine)
        {
            $this->recalculateLine($args);
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if($entity instanceof ReportLine)
        {
            $entity->setUpdatedAt(new \DateTimeImmutable());

            /*if($entity->getReport()){
                $scale = $entity->getReport()->isVehiculeInVehiculeReport($entity->getVehicule())->getScale();
            }else{
                $scale = $entity->getVehicule()->getScale();
            }*/

            $scale = $entity->getVehicule()->getScale();
            $entity->setScale($scale);

            $entity->calculateAmount();
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if($entity instanceof ReportLine)
        {
            $this->recalculateLine($args);
        }
    }

    public function recalculateLine($args)
    {
        $em = $args->getObjectManager();
        $entity = $args->getObject();

        if($entity instanceof ReportLine)
        {
            $this->report = $entity->getReport();
            $this->report->setUpdatedAt(new \DateTimeImmutable());
           
            $em->persist($this->report);
            $em->flush();
            
            if ($em->contains($this->report)) {
                $em->refresh($this->report); 
            }
        }
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if($entity instanceof ReportLine)
        {
            $this->report = $entity->getReport();
            $this->report->removeLine($entity);
            $this->report->setUpdatedAt(new \DateTimeImmutable());
        }
    }

}
