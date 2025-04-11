<?php

namespace App\EventSubscriber;

use App\Controller\App\ReportAppCrudController;
use App\Controller\App\ReportLineAppCrudController;
use App\Entity\Report;
use App\Entity\ReportLine;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security;

class AppCrudSubscriber implements EventSubscriberInterface
{

    private $adminUrlGenerator;
    private $entityManager;
    private $security;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager, Security $security)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public static function getSubscribedEvents()
    {
        return [
            BeforeCrudActionEvent::class => ['redirectToIndex']
        ];
    }

    public function redirectToIndex(BeforeCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        $entityFqcn = $context->getEntity()->getFqcn();
        $entity = new $entityFqcn();

        if ($entity instanceof ReportLine) {
            if($context->getRequest()->query->get("filters") == null){
                
                $reportline = $this->entityManager->getRepository(ReportLine::class)->getLastLineForUser();
                if ($reportline) {
                    $dataParams = $reportline->getTravelDate()->format('F')."/".$reportline->getTravelDate()->format('Y');
                } else {
                    $now = new \DateTime();
                    $dataParams = $now->format('F')."/".$now->format('Y');
                }
                $url = $this->adminUrlGenerator
                ->setController(ReportLineAppCrudController::class)
                ->setAction('index')
                ->set("filters[period][value]",$dataParams)
                ->generateUrl()
                ;
                
                $response = new RedirectResponse($url);
                $event->setResponse($response);
            }
            
        }

        if ($entity instanceof Report) {
            if(!$context->getRequest()->query->has("filters")){

                $report = $this->entityManager->getRepository(Report::class)->getLastReportForUser();
                $now = new \DateTime();
                if($report) {
                    $period = $this->security->getUser()->generateBalancePeriodByReport($report);
                }else{
                    $period = $this->security->getUser()->getCurrentFiscalPeriod();
                }
                
                $choice = $this->security->getUser()->getFormattedBalancePeriod($period);
                
                $url = $this->adminUrlGenerator
                ->setController(ReportAppCrudController::class)
                ->setAction('index')
                ->set("filters[Period][value]",$choice)
                ->generateUrl()
                ;
                
                $response = new RedirectResponse($url);
                $event->setResponse($response);
            }
            
        }

        
        return;
    }
}