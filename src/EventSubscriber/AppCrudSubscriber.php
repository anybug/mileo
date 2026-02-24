<?php

namespace App\EventSubscriber;

use App\Controller\App\ReportAppCrudController;
use App\Controller\App\ReportLineAppCrudController;
use App\Entity\Report;
use App\Entity\ReportLine;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
        $filterParams = false;

        if ($entity instanceof ReportLine) {
            //pas si Ajax car par compatilble avec la modale de adresses favorites
            if(!$context->getRequest()->isXmlHttpRequest()){
                if(!$context->getRequest()->query->has("filters") || ($context->getRequest()->query->has("filters") && !$this->guessLinesFromPeriod($context->getRequest()->query->all()['filters'])))
                {
                    $reportline = $this->entityManager->getRepository(ReportLine::class)->getLastLineForUser();
                    if ($reportline) {
                        $filterParams = $reportline->getTravelDate()->format('F')."/".$reportline->getTravelDate()->format('Y');
                    }

                    $url = $this->adminUrlGenerator
                        ->setController(ReportLineAppCrudController::class)
                        ->setAction(Action::INDEX)
                        ->set("filters[period][value]", $filterParams)
                        ->generateUrl()
                    ;

                    if($filterParams){
                        $response = new RedirectResponse($url);
                        $event->setResponse($response);
                    }
                }
            }
        }

        if ($entity instanceof Report) {
            if(!$context->getRequest()->query->has("filters") || ($context->getRequest()->query->has("filters") && !$this->guessReportsFromPeriod($context->getRequest()->query->all()['filters'])))
            {
                $report = $this->entityManager->getRepository(Report::class)->getLastReportForUser();
                $now = new \DateTime();
                if($report) {
                    $period = $this->security->getUser()->generateBalancePeriodByReport($report);
                    $filterParams = $this->security->getUser()->getFormattedBalancePeriod($period);
                }/*else{
                    $period = $this->security->getUser()->getCurrentFiscalPeriod();
                }*/
                
                
                $url = $this->adminUrlGenerator
                    ->setController(ReportAppCrudController::class)
                    ->setAction(Action::INDEX)
                    ->set("filters[Period][value]",$filterParams)
                    ->generateUrl()
                ;
                
                if($filterParams){
                    $response = new RedirectResponse($url);
                    $event->setResponse($response);
                }
            }   
        }
        
        return;
    }

    private function guessLinesFromPeriod($filters)
    {
        //vérification si la période contient des trajets
        $period = $filters['period'];
        [$year,$month] = explode('/', $period['value']);

        $report = $this->entityManager->getRepository(Report::class)->findByYearAndMonth($year, $month);
        if(!$report){
            return false;
        }

        return true;
    }

    private function guessReportsFromPeriod($filters)
    {
        //vérification si la période contient des trajets
        $period = $filters['Period'];
        [$start,$end] = explode(' -> ', $period['value']);

        $reports = $this->entityManager->getRepository(Report::class)->findByPeriod($start,$end);

        if(count($reports)<1){
            return false;
        }

        return true;
    }
}
