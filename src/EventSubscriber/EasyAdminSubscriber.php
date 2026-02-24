<?php
namespace App\EventSubscriber;

use App\Controller\App\ReportLineAppCrudController;
use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\VehiculesReport;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EasyAdminSubscriber implements EventSubscriberInterface
{
    private $em;
    private $adminUrlGenerator;

    public function __construct(EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getSubscribedEvents()
    {
        return [
            //BeforeCrudActionEvent::class => ['checkOrderInvoice'],
            AfterEntityPersistedEvent::class => ['afterPersistReport'],
            AfterEntityUpdatedEvent::class   => ['afterUpdateReport'],
            AfterEntityDeletedEvent::class   => ['afterDeleteReport'],
        ];
    }

    public function afterPersistReport(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof Report) {
            $this->recalculateReport($entity);
        }

        if ($entity instanceof ReportLine) {
            $this->recalculateLine($entity);
        }
    }

    public function afterUpdateReport(AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof Report) {
            $this->recalculateReport($entity);
        }

        if ($entity instanceof ReportLine) {
            $this->recalculateLine($entity);
        }
    }

    public function afterDeleteReport(AfterEntityDeletedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof ReportLine) {
            //si le rapport mensuel associé est maintenant vide, on le supprime
            $report = $entity->getReport();
            if ($report->getLines()->isEmpty()) {
                $this->em->remove($report);
                $this->em->flush();
            }
            else{
                $this->recalculateReport($report);
            }
        }
        
    }

    public function recalculateLine($entity)
    {
        $scale = $entity->getVehicule()->getScale();
        $entity->setScale($scale);
        $entity->calculateAmount();
        $this->em->flush();

        $this->recalculateReport($entity->getReport());
        
    }

    private function recalculateReport(Report $entity): void
    {
        /*$lines = $entity->getLines();
        if ($this->em->contains($lines)) {
            $this->em->refresh($lines); //remet les collections à jour depuis la DB, plus utile mais on le garde sous le coude
        }*/

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

            $this->em->flush();
            
        }
        
    }


}