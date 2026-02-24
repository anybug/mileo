<?php

namespace App\EventListener;

use App\Entity\Report;
use App\Entity\ReportLine;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

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
            $scale = $entity->getVehicule()->getScale();

            $entity->setScale($scale);
            $entity->calculateAmount();

            $entity->setCreatedAt(new \DateTimeImmutable());
            $entity->setUpdatedAt(new \DateTimeImmutable());

            $this->recalculateLine($args);
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
            
            if ($em->contains($this->report)) {
                $em->refresh($this->report); 
            }

            $em->flush();
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
