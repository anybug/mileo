<?php

namespace App\Controller\App;

use App\Entity\Brand;
use App\Entity\Scale;
use App\Entity\Report;
use App\Entity\Vehicule;
use App\Utils\ReportPdf;
use App\Entity\ReportLine;
use App\Form\FindByMonthType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\App\Filter\LineDateFilter;
use Symfony\Component\HttpFoundation\Request;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;

use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository as EasyAdminEntityRep;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;

class ReportLineAppCrudController extends AbstractCrudController
{
    private $entityManager;
    private $adminUrlGenerator;
    private $cloned = null;

    public function __construct(EntityManagerInterface $entityManager,AdminUrlGenerator $adminUrlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return ReportLine::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {

        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $queryBuilder->leftJoin('entity.report', 'r');
        $queryBuilder->andWhere('r.user = (:user)');
        $queryBuilder->setParameter('user', $this->getUser());

        return $queryBuilder;
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

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['travel_date' => 'ASC'])
            ->setPageTitle('index', 'My travels')
            ->setPageTitle('new', 'Saisir un trajet')
            ->setPageTitle('edit', fn (ReportLine $reportLine) => sprintf('Modifier trajet du %s', $reportLine->getTravelDate()->format("d/m/Y")))
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'App/ReportLine/index.html.twig')
            ->overrideTemplate('crud/filters', 'App/ReportLine/filters.html.twig')
            ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
            ->overrideTemplate('crud/new', 'App/advanced_new.html.twig')
            ;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addHtmlContentToBody('<script src="https://maps.googleapis.com/maps/api/js?key=' . $_ENV['GOOGLE_MAPS_API_KEY'] . '&libraries=places"></script>')
        ;
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

        $responseParameters = parent::index($context);
        if ($responseParameters instanceof KeyValueStore) {
            if (count($this->getUser()->getVehicules()) == 0) {
                $responseParameters->set("message",'Veuillez créer votre véhicule avant de commencer à créer vos trajets');
            }
        }


        return $responseParameters;
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);

        $duplicate = Action::new('duplicate', 'Duplicate')
            ->linkToCrudAction('duplicate')
            ->setIcon("fa fa-copy")
            ;

        if (count($this->getUser()->getVehicules()) == 0) {
            $actions->remove(Crud::PAGE_INDEX, Action::NEW);
        }

        return $actions
            ->add(Crud::PAGE_INDEX, $duplicate)
            ->add(Crud::PAGE_EDIT, Action::DELETE)
            ;
    }

    public function duplicate(AdminContext $context)
    {

        $reportLine = $context->getEntity()->getInstance();

        if (!$reportLine) {
            $this->addFlash('error', 'Trajet non trouvé.');
            $url = $this->adminUrlGenerator
                ->setController(ReportLineAppCrudController::class)
                ->setAction('index')
                ->generateUrl()
                ;
           
            return $this->redirect($url);
        }

        $url = $this->adminUrlGenerator
                ->setController(ReportLineAppCrudController::class)
                ->setAction(Action::NEW)
                ->setEntityId($reportLine->getId())
                ->generateUrl()
                ;
           
        return $this->redirect($url);
    }

    public function createEntity(string $entityFqcn)
    {
        $context = $this->container->get(AdminContextProvider::class)->getContext();

        if($context->getRequest()->query->get('entityId')){
            $entityManager = $this->container->get('doctrine')->getManager();
            $id = intval($context->getRequest()->query->get('entityId'));
            $reportLine = $entityManager->getRepository(ReportLine::class)->find($id);

            if (!$reportLine) {
                $this->addFlash('error', 'Trajet non trouvé.');
                $url = $this->adminUrlGenerator
                    ->setController(ReportLineAppCrudController::class)
                    ->setAction('index')
                    ->generateUrl()
                    ;
               
                return $this->redirect($url);
            }

            $duplicatedLine = clone $reportLine;
            $duplicatedLine->resetId();  // Réinitialiser l'ID pour éviter un conflit

            $reportLine = $duplicatedLine;
            
        } else {
            $reportLine = new $entityFqcn();
            $reportLine->setVehicule($this->getUser()->getDefaultVehicule());
            $reportLine->setScale($this->getUser()->getDefaultVehicule()->getScale());
            $reportLine->setTravelDate(new \DateTimeImmutable());

        }

        return $reportLine;
        
    }
    
    public function configureFields(string $pageName): iterable
    {
        $entity = $this->getContext()->getEntity()->getInstance();
        $dateFieldHtmlAttributes = [];
        if($pageName == Crud::PAGE_EDIT && $entity->getId()){
            $firstDayOfMonth = clone $entity->getTravelDate();
            $firstDayOfMonth->modify('first day of this month');
            $lastDayOfMonth = clone $entity->getTravelDate();
            $lastDayOfMonth->modify('last day of this month');
            $dateFieldHtmlAttributes = ['min' => $firstDayOfMonth->format('Y-m-d'), 'max' => $lastDayOfMonth->format('Y-m-d')];
        }

        yield FormField::addPanel();
        yield DateField::new('travel_date','Date')->setColumns('col-sm-6 col-lg-5 col-xxl-2')->setHtmlAttributes($dateFieldHtmlAttributes)->onlyOnForms();
        yield DateField::new('travel_date','Date')->onlyOnIndex();
        yield AssociationField::new('vehicule', 'Véhicule')
            ->setFormTypeOptions([
                'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('v')
                    ->andWhere('v.user = (:user)')
                    ->setParameter('user', $this->getUser());
                },
                'attr' => ['class'=>'report_vehicule']
            ])
            ->setColumns('col-sm-6 col-lg-5 col-xxl-2')
            ;
        yield FormField::addRow();
        yield FormField::addPanel('Travel information')->setIcon('fa fa-car');
        yield ChoiceField::new('favories','adresse favorite')
            ->setFormTypeOptions([
                'attr' => ['class'=>'report_favories'],
                'expanded' => true,
                'mapped' => false,
                'required' => true,
                'choice_attr' => function($choice, $key, $value) {
                    return ['class' => 'report_favories_choice'];
                }
            ])
            ->onlyOnForms()
            ->setColumns('col-sm-12 col-lg-6 col-xxl-5')
            ->setChoices(function (){
            $adresses = $this->getUser()->getUserAddresses();
            if(count($adresses) != 0){
                foreach ($adresses as $adress) {
                    $choices[$adress->__toString()] = $adress->getAddress();
                }
                return $choices;
            } else {
                return ["Vous n'avez pas d'adresse favorite" => ""];
            }
        });
        yield TextField::new('startAdress','Départ')
                ->setFormTypeOptions([
                    'attr' => ['class'=>'autocomplete lines_start'],
                    'label_html' => true,
                    'help' => 'Saisissez une adresse ou <a class="popup-fav-start">selectionnez une de vos <i class="fa fa-map-marker-alt"></i></a>'
                ])
                ->setColumns('col-sm-12 col-lg-6 col-xxl-5')
            ;
        yield TextField::new('endAdress',"Arrivée")
                ->setFormTypeOptions([
                    'attr' => ['class'=>'autocomplete lines_end'],
                    'label_html' => true,
                    'help' => 'Saisissez une adresse ou <a class="popup-fav-end">selectionnez une de vos <i class="fa fa-map-marker-alt"></i></a>'
                ])
                ->setColumns('col-sm-12 col-lg-6 col-xxl-5')
            ;
            
        yield TextareaField::new('comment','Motif du déplacement')
            ->onlyOnIndex()
        ;

        yield HiddenField::new('km','Distance (km)')
            ->setFormTypeOptions(['attr' => array('readonly'=> true, 'class' => 'report_km bg-light not-allowed')])
            ->onlyOnForms()
            //->setColumns('col-sm-4 col-lg-3 col-xxl-2')
            ;
            yield IntegerField::new('km_total','Distance (km)')
            ->setFormTypeOptions(['attr' => array('readonly'=> true, 'class' => 'report_km_total bg-light')])
            ->setColumns('col-sm-4 col-lg-3 col-xxl-2')
            ->hideOnIndex()
            ;
        yield IntegerField::new('km_total','Distance')
            ->onlyOnIndex()
            ->setNumberFormat('%s'.' km')
            ;
        yield FormField::addRow();

        yield BooleanField::new('is_return','Aller retour')
            ->setFormTypeOptions([
            'attr' => ['class'=>'report_is_return']
            ])
            ->onlyOnForms()
            //->renderAsSwitch(false)
            ->setColumns('col-sm-12 col-lg-12 col-xxl-12')
        ;
        yield FormField::addRow();

        yield TextareaField::new('comment','Motif du déplacement')
                ->setFormTypeOptions(['required' => true, 'attr' => ['placeholder' => "Saisissez une courte description qui justifie ce trajet"]])
                ->setColumns('col-12')
                ->onlyOnForms()
        ;
        
        yield FormField::addRow();

        yield FormField::addPanel('Estimation')->setIcon('fa fa-coins');
        /*yield AssociationField::new('scale')
            ->setColumns('col-sm-4 col-lg-3 col-xxl-3')
        ;*/

        yield NumberField::new("amount",'Montant')
            ->setFormTypeOptions(['attr' => ['readonly'=> true,'class'=>'report_amount bg-light', 'help' => "Montant estimé"]])
            ->setColumns('col-sm-4 col-lg-3 col-xxl-2')
            ->onlyOnForms()
        ;
        yield NumberField::new("amount",'Montant')
            ->setNumberFormat('%s'.' €')
            ->onlyOnIndex()
        ;
        
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(LineDateFilter::new('period'))
        ;
    }

    public function generateAmountAction(AdminContext $context)
    {
        $report_id = $context->getRequest()->query->get('report_id') ?? false;
        $report_line_id = $context->getRequest()->query->get('report_line_id') ?? false;
        $vehicule_id = $context->getRequest()->query->get('vehicule') ?? false;
        $distance = $context->getRequest()->query->get('distance') ?? false;
        
        $vehicule = $this->entityManager->getRepository(Vehicule::class)->find($vehicule_id);

        if($report_id){
            $report = $this->entityManager->getRepository(Report::class)->find($report_id);
            foreach ($report->getVehiculesReports() as $vr) {
                if ($vr->getVehicule() == $vehicule) {
                    $scale = $vr->getScale();
                }
            }
        } else if($report_line_id){
            $reportLine = $this->entityManager->getRepository(ReportLine::class)->find($report_line_id);
            $report = $reportLine->getReport();
            foreach ($report->getVehiculesReports() as $vr) {
                if ($vr->getVehicule() == $vehicule) {
                    $scale = $vr->getScale();
                }
            }
        }

        if($vehicule && $distance){

            //vérification si le barême n'est pas déjà attribué pour ce rapport
            if (!isset($scale)) {
                $scale = $vehicule->getScale();
            }

            $reportLine = new ReportLine();
            $reportLine->setScale($scale);
            $reportLine->setKmTotal($distance);    
            $reportLine->calculateAmount();
    
            die(json_encode($reportLine->getAmount()));
            //die(json_encode($scale->__toString()));  <- pour verif 
        }
    }

    public function generatePdfPerMonth(AdminContext $context)
    {
        $period = $context->getRequest()->query->get("filters")["period"]["value"];
        $period = explode('/',$period);
        $month = $period[0];
        $year = $period[1];
        $report = $this->entityManager->getRepository(Report::class)->findByYearAndMonth($year,$month);
        $pdf = new ReportPdf();
        $pdf->generatePdf([$report],$period,'month');
    }

    public function generateFooterLine(KeyValueStore $responseParameters, AdminContext $context) 
    {
        $paginator = $responseParameters->get('paginator');
        $lines = $paginator->getResults();
        if (count($lines) != 0) {
            $report = $lines[0]->getReport();
    
            $totals = ['km' => 0, 'amount' => 0];
            $vehiculesTotals = [];
    
            $totals['km'] = $report->getKm();
            $totals['amount'] = $report->getTotal();
    
            foreach ($report->getVehiculesReports() as $line) 
            {
                $vid = $line->getVehicule()->getId();
                if(isset($vehiculesTotals[$vid])){
                    $vehiculesTotals[$vid]['km'] += $line->getKm();
                    $vehiculesTotals[$vid]['amount'] += $line->getTotal();
                }else{
                    $vehiculesTotals[$vid]['Vehicule'] = $line->getVehicule();
                    //$vehiculesTotals[$vid]['Scale'] = $line->getScale();
                    $vehiculesTotals[$vid]['Vr'] = $line;
                    $vehiculesTotals[$vid]['km'] = $line->getKm();
                    $vehiculesTotals[$vid]['amount'] = $line->getTotal();
                }
            }
            
            $parameters = [
                'totals' => $totals,
                'vehiculesTotals' => $vehiculesTotals
            ];
    
            $responseParameters->setAll($parameters);
        }

        return $responseParameters;
    }

}
