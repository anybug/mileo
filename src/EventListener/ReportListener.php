<?php

namespace App\EventListener;

use App\Entity\Report;
use App\Entity\Scale;
use App\Entity\VehiculesReport;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReportListener
{
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if($entity instanceof Report)
        {
            $entity->setCreatedAt(new \DateTimeImmutable());
            $entity->setUpdatedAt(new \DateTimeImmutable());
            $entity->calculateKm();
            $entity->calculateTotal();
        }
    }
    
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if($entity instanceof Report)
        {
            $this->VehiculeReportModifier($args);
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if($entity instanceof Report)
        {
            $entity->setUpdatedAt(new \DateTimeImmutable());
            $entity->calculateKm();
            $entity->calculateTotal();
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if($entity instanceof Report)
        {
            $this->VehiculeReportModifier($args);
        }
    }
    
    public function VehiculeReportModifier($args)
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();
        $scales = [];
        foreach($entity->getVehiculesReports() as $vr)
        {
            $scales[$vr->getId()]['vehicule'] = $vr->getVehicule();
            $scales[$vr->getId()]['scale'] = $vr->getScale();
            $entity->removeVehiculesReport($vr);
        }
        foreach($entity->getVehicules() as $vehicule)
        {
            $reportVehicule = new VehiculesReport;
            $entity->addVehiculesReport($reportVehicule);
            $reportVehicule->setVehicule($vehicule);
            if ($scales != []) {
                foreach ($scales as $scale) {
                    if ($reportVehicule->getVehicule() == $scale['vehicule']) {
                        $reportVehicule->setScale($scale['scale']);
                    }
                }
                if (!$reportVehicule->getScale()) {
                    $reportVehicule->setScale($vehicule->getScale());
                }
            } else {
                $reportVehicule->setScale($vehicule->getScale());
            }
        
            $reportVehicule->calculateTotal();

            $entity->calculateKm();
            $entity->calculateTotal();

            //$em->persist($entity);
            
        }

        $em->flush();

    }
}
