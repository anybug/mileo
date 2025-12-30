<?php

namespace App\Controller\App;

use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Order;
use App\Entity\Power;
use App\Entity\Scale;
use App\Entity\Report;
use App\Entity\Vehicule;
use App\Entity\ReportLine;
use App\Entity\UserAddress;
use App\Form\UserStep2Type;
use App\Form\UserStep3Type;
use App\Entity\Subscription;
use Symfony\UX\Chartjs\Model\Chart;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\Admin\UserCrudController;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Admin\VehiculeCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Symfony\Component\Form\FormFactoryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\Form\Test\FormBuilderInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Symfony\Component\Security\Core\User\UserInterface;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FormFactory;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyAdminFriends\EasyAdminDashboardBundle\Controller\DefaultController as EasyAdminDashboard;


class DashboardAppController extends AbstractDashboardController
{
    private $easyAdminDashboard;
    private $chartBuilder;
    private $entityManager;
    private $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, EasyAdminDashboard $easyAdminDashboard,ChartBuilderInterface $chartBuilder,EntityManagerInterface $entityManager, FormFactoryInterface $formFactory)
    {
        $this->easyAdminDashboard = $easyAdminDashboard;
        $this->chartBuilder = $chartBuilder;
        $this->entityManager = $entityManager;
        $this->formFactory = $formFactory;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->overrideTemplate('layout', 'App/advanced_layout.html.twig')
            ->setFormThemes(['App/form.html.twig', '@EasyAdmin/crud/form_theme.html.twig'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(100000000)
        ;
    }

    public function configureActions(): Actions
    {

        return Actions::new()
            ->add(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon("fa fa-pen")->setLabel("Modify");
            })
            ->add(Crud::PAGE_INDEX, Action::DELETE)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon("fa fa-trash");
            })

            ->add(Crud::PAGE_DETAIL, Action::EDIT)
            ->add(Crud::PAGE_DETAIL, Action::INDEX)
            ->add(Crud::PAGE_DETAIL, Action::DELETE)

            ->add(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN)
            ->add(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)

            ->add(Crud::PAGE_NEW, Action::SAVE_AND_RETURN)
            ->add(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
        ;
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
        ->addWebpackEncoreEntry('app')
        ->addCssFile("assets/css/backend.css")
        ->addJsFile('https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js')
        ;
    }

    #[Route(path: '/dashboard', name: 'app')]
    public function index(): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $step2 = $request->query->get('step2') ?? false;
        $request->query->get('step3') ?? false;
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        dump($user);
        $step = [];

        if (!$user->getSubscription() || !$user->getSubscription()->isValid()) {

            $url = $this->adminUrlGenerator
            ->setController(UserAppCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

            return $this->redirect($url);
        }

        if(!$user->hasCompletedSetup())
        {
            if(!$user->hasCompletedStep2() || $step2){
                $form = $this->createForm(UserStep2Type::class, $user);
                $step = ['title' => "Informations personnelles et juridiques", 'number' => 2];
                $step2 = true;
            }else{
                $form = $this->formFactory->createNamed('Vehicule', UserStep3Type::class);
                $step = ['title' => "Véhicule par défaut", 'number' => 3];
            }
            
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();

                if($data instanceof Vehicule)
                {
                    $data->setUser($user);
                }

                $this->entityManager->persist($data);      
                $this->entityManager->flush();

                if(!$step2){
                    $this->addFlash(
                        'success',
                        '<span class="fs-4">Félicitations! Vous pouvez désormais profiter de Mileo, amusez-vous bien <i class="far fa-smile-wink"></i> </span>'
                    );
                }
                
                return $this->redirectToRoute('app', ['menuIndex' => 0, 'submenuIndex' => '-1']);
            }

            return $this->render('App/Dashboard/wizard.html.twig', [
                'dashboard' => $this->easyAdminDashboard->generateDashboardValues(),
                'layout_template_path' => $this->easyAdminDashboard->getLayoutTemplate(),
                'form' => $form->createView(),
                'step' => $step,
            ]);
        }
   
        $yearSelected = date('Y');

        $lastReport = $this->entityManager->getRepository(Report::class)->getLastReportForUser();
        if($lastReport){
            $yearSelected = $lastReport->getStartDate()->format('Y');
        }
        
        if($request->query->get('yearSelected')){
            $yearDate = \DateTimeImmutable::createFromFormat('Y', $request->query->get('yearSelected'));
            if($yearDate){
                $yearSelected = $yearDate->format('Y');
            }
        }

        $reports = $this->entityManager->getRepository(Report::class)->findByYear($yearSelected);
        $labels = [];
        $data = [];
        foreach ($reports as $report) {
            array_push($labels,$report->getStartDate()->format('m/Y'));
            array_push($data,$report->getTotal());
        }
        
        $chartAnnuel = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        
        $chartAnnuel->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Montant des indemnité en Euro par mois',
                    'backgroundColor' => '#0d6efd',
                    'borderColor' => '#0d6efd',
                    'data' => $data,
                ],
            ],
        ]);
        
        /*$chartAnnuel->setOptions([
            'scales' => [
                'y' => [
                    'suggestedMin' => 0,
                    'suggestedMax' => 100,
                ],
            ],
        ]);*/

        $reports = $this->entityManager->getRepository(Report::class)->getReportsForUser();

        $valueListYears = [];
        foreach ($reports as $report) {
            $valueListYears[] = $report->getStartDate()->format('Y');
            $report_labels[$report->getStartDate()->format('Y')] = $report->getStartDate()->format('Y');
            if(isset($report_data[$report->getStartDate()->format('Y')])){
                $report_data[$report->getStartDate()->format('Y')] += $report->getTotal();
            }else{
                $report_data[$report->getStartDate()->format('Y')] = $report->getTotal();
            }
        }
        $resultListYears = array_unique($valueListYears);

        $labels = [];
        $data = [];
        if(isset($report_labels)){
            foreach($report_labels as $val){
                array_push($labels,$val);
            }
        }
        if(isset($report_data)){
            foreach($report_data as $val){
                array_push($data,$val);
            }
        }

        $chartTotal = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        
        $chartTotal->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Indemnité kilométrique par année',
                    'backgroundColor' => '#0d6efd',
                    'borderColor' => '#0d6efd',
                    'data' => $data,
                ],
            ],
        ]);

        //check if vehicule is set up with latest Scale
        $flash  = false;
        foreach($this->getUser()->getVehicules() as $vehicule)
        {
            $url = $this->container->get(AdminUrlGenerator::class)
            ->setController(VehiculeAppCrudController::class)
            ->setAction(Action::INDEX)
            ->set('menuIndex', 6)
            ->generateUrl()
            ;

            if(!$vehicule->hasLatestScale()){
                $flash  = true;
            }
        }

        if($flash){
            $this->addFlash(
                'info',
                '<span class="fs-4">Certains de vos véhicules ne sont pas configurés avec le dernier barème en date. <a href="'.$url.'" class=""><i class="action-icon fa fa-pen"></i> Mettre à jour mes véhicules</a></span>'
            );
        }

        return $this->render('App/Dashboard/index.html.twig', [
            'dashboard' => $this->easyAdminDashboard->generateDashboardValues(),
            'layout_template_path' => $this->easyAdminDashboard->getLayoutTemplate(),
            'chartAnnuel' => $chartAnnuel,
            'chartTotal' => $chartTotal,
            'years' => $resultListYears,
            'yearSelected' => $yearSelected
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            //->setTitle('Mileo')
            ->setTitle('<img src="../assets/img/logo.png" />')
            ->setFaviconPath('assets/img/favicons/favicon.ico')
            ->disableDarkMode()
            ;
    }

    public function configureMenuItems(): iterable
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user && $user->getSubscription() && $user->getSubscription()->getPlan()) {
            $planName = strtolower(trim((string) $user->getSubscription()->getPlan()->getName()));
        }

        yield MenuItem::linktoDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Travels');
        yield MenuItem::linkToCrud('My travels', 'fa fa-road', ReportLine::class);
        yield MenuItem::linkToCrud('Monthly reports', 'fa fa-road', Report::class);

        yield MenuItem::section('Parameters');
        yield MenuItem::linkToCrud('Profile', 'fa fa-id-card', User::class)->setController(UserAppCrudController::class);
        yield MenuItem::linkToCrud('My vehicules', 'fa fa-car', Vehicule::class);
        yield MenuItem::linkToCrud('My addresses', 'fa fa-map-marker-alt', UserAddress::class);

        if (!$planName === 'free') {
            yield MenuItem::linkToCrud('My invoices', 'fa-solid fa-file-invoice', Order::class);
        }
        
        yield MenuItem::linkToCrud('Scales', 'fa-solid fa-table', Scale::class)->setController(ScaleAppCrudController::class);

    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Usually it's better to call the parent method because that gives you a
        // user menu with some menu items already created ("sign out", "exit impersonation", etc.)
        // if you prefer to create the user menu from scratch, use: return UserMenu::new()->...
        return parent::configureUserMenu($user)
            // use the given $user object to get the user name
            ->setName($user->__toString())
            // use this method if you don't want to display the name of the user
            ->displayUserName(true)

            // use this method if you don't want to display the user image
            //->displayUserAvatar(true)
            // you can also pass an email address to use gravatar's service
            //->setGravatarEmail($user->getFirstname())

            // you can use any type of menu item, except submenus
            ->addMenuItems([
                MenuItem::linkToCrud('Profile', 'fa fa-id-card', User::class),
                //MenuItem::linkToRoute('Settings', 'fa fa-user-cog', '...', ['...' => '...']),
                //MenuItem::section(),
                //MenuItem::linkToLogout('Logout', 'fa fa-sign-out'),
            ]);
    }
}