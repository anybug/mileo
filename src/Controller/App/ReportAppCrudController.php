<?php

namespace App\Controller\App;

use App\Controller\App\Filter\ReportYearFilter;
use App\Controller\App\Filter\ReportYearFilterType;
use App\Entity\Brand;
use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\Scale;
use App\Entity\Vehicule;
use App\Entity\VehiculesReport;
use App\Form\FindByYearType;
use App\Form\ReportTotalScaleType;
use App\Form\ReportLineType;
use App\Utils\ReportPdf;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository as EasyAdminEntityRep;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use Symfony\Component\HttpFoundation\Request;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use FTP\Connection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Test\FormBuilderInterface;

class ReportAppCrudController extends AbstractCrudController
{
    private $entityManager;
    private $adminUrlGenerator;
    private $formChangeScale;

    public function __construct(EntityManagerInterface $entityManager,AdminUrlGenerator $adminUrlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addHtmlContentToBody('<script src="https://maps.googleapis.com/maps/api/js?key=' . $_ENV['GOOGLE_MAPS_API_KEY'] . '&libraries=places"></script>')
        ;
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $context = $this->getContext();

        $newResponseParameters = parent::configureResponseParameters($responseParameters);

        $pageName = $newResponseParameters->get('pageName');
        if($pageName == Crud::PAGE_INDEX){
            $newResponseParameters = $this->generateFooterLine($newResponseParameters, $context);
        }

        return $newResponseParameters;
    }

    public function index(AdminContext $context)
    {
        if (!$this->getUser()->getSubscription()->isValid()) {

            $url = $this->adminUrlGenerator
            ->setController(UserAppCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

            return $this->redirect($url);
        }

        if(!$this->getUser()->hasCompletedSetup())
        {
            return $this->redirectToRoute('app', ['menuIndex' => 0, 'submenuIndex' => '-1']);
        }

        return parent::index($context);
    }

    public function createEntity(string $entityFqcn)
    {
        $entity = new $entityFqcn();
        $entity->setUser($this->getUser());
        return $entity;
    }
    
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $queryBuilder->andWhere('entity.user = (:user)')
                     ->setParameter('user', $this->getUser());

        //dd($queryBuilder->getQuery()->getSql());

        return $queryBuilder;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
        ->setDefaultSort(['start_date' => 'ASC'])
        ->setPageTitle('index', 'Reports')
        ->setPageTitle('edit', fn (Report $report) => sprintf('Modifier le rapport de %s', $report->getPeriod()))
        ->setPageTitle('new', 'Please select report period')
        ->setFormOptions(['validation_groups' => ['new','default']], ['validation_groups' => ['default','edit']])
        ->overrideTemplate('crud/index', 'App/Report/index.html.twig')
        ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
        //->overrideTemplate('crud/new', 'App/advanced_new.html.twig')
        ->overrideTemplate('crud/filters', 'App/Report/filters.html.twig')
        ->addFormTheme('App/Report/form_theme.html.twig')
        ;
    }
    
    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);

        $generatePdf = Action::new('generatePdf')
            ->setIcon("fa fa-download")
            ->setLabel("Télécharger en pdf")
            //->setCssClass('btn btn-primary')
            ->linkToCrudAction('generatePdf');

        return $actions
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_RETURN)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->add(Crud::PAGE_NEW, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_CONTINUE, function (Action $action) {
                return $action->setIcon("fa-solid fa-arrow-right")->setLabel("Next")->setCssClass('btn btn-primary suivant');
            })
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setCssClass('btn btn-primary new-report-action');
            })
            ->add(Crud::PAGE_INDEX, $generatePdf)
            ;
    }

    public function generatePdf(AdminContext $context)
    {
        $report = $context->getEntity()->getInstance();
        $pdf = new ReportPdf();
        $period = [$report->getStartDate()->format('F'),$report->getStartDate()->format('Y')];
        return $pdf->generatePdf([$report],$period,'month');
    }
    
    public function generatePdfPerYear(AdminContext $context)
    {

        $period = $context->getRequest()->query->get("filters")["Period"]["value"];
        $period = explode(" -> ",$period);
        $reports = $this->entityManager->getRepository(Report::class)->findByPeriod($period[0],$period[1]);
        $pdf = new ReportPdf();
        return $pdf->generatePdf($reports,$period,'year');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addRow();
        yield DateField::new('start_date','Period')
        ->onlyOnIndex()
        ->setFormat('MMMM y')
        ;
        
        yield DateField::new('start_date','Date de début')->setFormTypeOptions(['attr' => ['class' => 'report_start_date']])->onlyOnForms()->hideWhenCreating();
        
        yield DateField::new('end_date','Date de fin')->setFormTypeOptions(['attr' => ['class' => 'report_end_date']])->onlyOnForms()->hideWhenCreating();
        //yield DateField::new('validate_date','Date de validation');
        yield DateField::new('Year','Année')->renderAsChoice()->onlyOnForms()->hideWhenUpdating()->setFormTypeOptions(["required" => true,'years' => range(date('Y')-4, date('Y')+1),]);
        yield ChoiceField::new('Period','Mois')->setChoices(function (){
            return ['Janvier' => 'January','Février' => 'February','Mars' => 'March','Avril' => 'April','Mai' => "May",'Juin' => 'June','Juillet' => 'July','Août' => 'August','Septembre' => 'September','Octobre' => "October",'Novembre' => 'November','Décembre' => "December"];
        })->onlyOnForms()->hideWhenUpdating()->setFormTypeOptions(["required" => true]);
        yield CollectionField::new('lines','Trajet')
            ->setEntryType(ReportLineType::class)
            ->allowDelete(true)
            ->hideWhenCreating()
            ->setFormTypeOptions(["attr" => ["class" => "lines"]])
            ->hideOnIndex();
        yield CollectionField::new('lines', 'Trajet(s)')->setTemplatePath('App/Report/lines.html.twig')->OnlyOnIndex();       
        //yield CollectionField::new('vehiculesReports', 'Details')->setTemplatePath('App/Report/vehiculesReports.html.twig')->OnlyOnIndex();  
        
        yield FormField::addRow();
        yield IntegerField::new('km','Distance totale (km)')
            ->setFormTypeOptions(["attr" => ["readonly" => true,"class" => "km bg-light fw-bold"]])
            ->hideWhenCreating()
            ->hideOnIndex()
            ->setColumns('col-3');
        yield IntegerField::new('km','Distance')
            ->setNumberFormat('%s'.' km')
            ->onlyOnIndex()
        ;

        yield NumberField::new('total','Montant Total (€)')
            ->setFormTypeOptions(["attr" => ["readonly" => true,"class" => "total bg-light fw-bold"]])
            //->setHelp('Détails du calcul')
            ->hideWhenCreating()
            ->hideOnIndex()
            ->setNumberFormat('%s'.' €')
            ;
        yield NumberField::new('total','Montant')
            ->setNumberFormat('%s'.' €')    
            ->onlyOnIndex()
            ;

    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ReportYearFilter::new('Period'))
        ;
    }


    public function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        if(isset($context->getRequest()->request->all()['ea'])){
            
            return parent::getRedirectResponseAfterSave($context, $action);

        }else{
            $context->getRequest()->setMethod('PATCH');
    
            $url = $this->container->get(AdminUrlGenerator::class)
                ->setAction(Action::EDIT)
                ->setEntityId($context->getEntity()->getPrimaryKeyValue())
                ->generateUrl();
    
            die($url);
        }
    }

    public function generateFooterLine(KeyValueStore $responseParameters, AdminContext $context) 
    {
        $paginator = $responseParameters->get('paginator');
        $reports = $paginator->getResults();
        $totals = ['km' => 0, 'amount' => 0];
        $vehiculesTotals = [];
        
        foreach ($reports as $report) 
        {
            $totals['km'] += $report->getVehiculesReportsTotalKm();
            $totals['amount'] += $report->getVehiculesReportsTotalAmount();
            foreach ($report->getVehiculesReports() as $vr) 
            {
                $vid = $vr->getVehicule()->getId();
                if(isset($vehiculesTotals[$vid])){
                    $vehiculesTotals[$vid]['km'] += $vr->getKm();
                    $vehiculesTotals[$vid]['amount'] += $vr->getTotal();
                        
                }else{
                    $vehiculesTotals[$vid]['Vehicule'] = $vr->getVehicule();
                    $vehiculesTotals[$vid]['Scale'] = $vr->getScale();
                    $vehiculesTotals[$vid]['Vr'] = $vr;
                    $vehiculesTotals[$vid]['km'] = $vr->getKm();
                    $vehiculesTotals[$vid]['amount'] = $vr->getTotal();
                }
            }
        }
        // echo $vehiculesTotals["34"]['Scale'];
        foreach ($vehiculesTotals as $vid => $v) 
        {
            $vehiculesReport = $v['Vr'];
            $vehiculesReport->setScale($vehiculesTotals[$vid]['Scale']);
            $vehiculesReport->setVehicule($vehiculesTotals[$vid]['Vehicule']);
            $final_choices = $this->getChoicesForVehiculeReport($vehiculesReport);

            $referrer = $this->container->get(AdminUrlGenerator::class)
                ->setAction(Action::INDEX)
                ->set('filters', $context->getRequest()->query->get("filters"))
                ->generateUrl()
            ;

            $form = $this->createForm(ReportTotalScaleType::class, $vehiculesReport, [
                'choices' => $final_choices,
                'action' => $this->container->get(AdminUrlGenerator::class)
                        ->setAction('scaleChangeForYear')
                        ->set('vrid', $vehiculesReport->getId())
                        ->setReferrer($referrer)
                        ->generateUrl()
            ]);

            $this->formChangeScale[] = $form;

            $vehiculesTotals[$vid]['form'] = $form->createView();   
            
            $valueKmMax = 0;
            if($vehiculesTotals[$vid]['Scale']->getKmMax() > ''){
                $valueKmMax = $vehiculesTotals[$vid]['Scale']->getKmMax();
                $valueKmMin = $vehiculesTotals[$vid]['Scale']->getKmMin();

                if($vehiculesTotals[$vid]['km'] >= $valueKmMax){
                    //show warning alert option choisie plus/non valide
                    $vehiculesTotals[$vid]['warning'] = 'La distance totale dépasse celle du barème sélectionné';
                }

                if($vehiculesTotals[$vid]['km'] <= $valueKmMin) {
                    //show warning alerte pas assez de km parcouru
                    $vehiculesTotals[$vid]['info'] = 'La distance totale est actuellement en-dessous du seuil du barème sélectionné';
                }
            }
        }
        $parameters = [
            'totals' => $totals,
            'vehiculesTotals' => $vehiculesTotals,
        ];

        $responseParameters->setAll($parameters);

        return $responseParameters;
    }

    public function scaleChangeForYear(AdminContext $context,ManagerRegistry $doctrine) 
    {

        $entityManager = $doctrine->getManager();

        $period = $context->getRequest()->query->get("filters")["Period"]["value"] ?? false;
        $vrid = $context->getRequest()->query->get("vrid") ?? false;

        $vehiculesReport = $this->entityManager->getRepository(VehiculesReport::class)->find($vrid);
        $period = explode(" -> ",$period);
        $start = $period[0];
        $end = $period[1];

        $reports = $this->entityManager->getRepository(Report::class)->findByPeriod($start,$end);

        $final_choices = $this->getChoicesForVehiculeReport($vehiculesReport);

        $form = $this->createForm(ReportTotalScaleType::class, $vehiculesReport, ['choices' => $final_choices]);

        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {

            $vehiculesReport = $form->getData();

            $scale = $vehiculesReport->getScale();
            $vehicule = $vehiculesReport->getVehicule();
            
            foreach ($reports as $report) {
                
                $report->setScale($scale);
                
                foreach ($report->getVehiculesReports() as $vr) {
                    if ($vr->getVehicule() == $vehicule) {
                        $report->setUpdatedAt(new \DateTime());
                        $vr->setScale($scale);
                        $entityManager->persist($vr);                        
                        //$this->entityManager->persist($report);                        
                    }
                }
            }
            $entityManager->flush();
            if (null !== $referrer = $context->getReferrer()) {
                return $this->redirect($referrer);
            }else{
                $url = $this->container->get(AdminUrlGenerator::class)->setAction(Action::INDEX)->generateUrl();
                return $this->redirect($url);
            }
        }
        
    }

    public function getChoicesForVehiculeReport(VehiculesReport $vehiculesReport)
    {
        foreach($vehiculesReport->getVehicule()->getPower()->getScales() as $s)
        {
            //Attention: même si le barème est ancien, il faut le laisser pour éviter de le changer en cours d'année fiscale si souhaité
            if($vehiculesReport->getScale()->getYear() == $s->getYear()){
                $powerString = $s->getPower()->__toString().' ('.$s->getYear().')';
                $choices[(string) $powerString][(string) $s->__toString()] = $s;
            }

            //si le barème sort en cours d'année, on peut l'appliquer pour toute l'année fiscale
            if($vehiculesReport->getVehicule()->getScale()->getYear() == $s->getYear()){
                $powerString = $s->getPower()->__toString().' ('.$s->getYear().')';
                $choices[(string) $powerString][(string) $s->__toString()] = $s;
            }
        }

        ksort($choices);

        return $choices;
    }

}
