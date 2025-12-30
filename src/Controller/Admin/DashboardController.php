<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\Vehicule;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyAdminFriends\EasyAdminDashboardBundle\Controller\DefaultController as EasyAdminDashboard;

class DashboardController extends AbstractDashboardController
{
    private $easyAdminDashboard;

    public function __construct(EasyAdminDashboard $easyAdminDashboard)
    {
        $this->easyAdminDashboard = $easyAdminDashboard;
    }

    #[Route(path: '/admin', name: 'admin')]
    public function index(): Response
    {
        //return parent::index();
        return $this->render('@EasyAdminDashboard/Default/index.html.twig', [
            'dashboard' => $this->easyAdminDashboard->generateDashboardValues(),
            'layout_template_path' => $this->easyAdminDashboard->getLayoutTemplate()
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Mileo');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoDashboard('Dashboard', 'fa fa-home');
        
        yield MenuItem::section('Management');
        yield MenuItem::linkToCrud('Users', 'fa fa-user', User::class)->setController(UserCrudController::class);
        yield MenuItem::linkToCrud('Orders', 'fas fa-layer-group', Order::class)->setController(OrderCrudController::class);
        yield MenuItem::linkToCrud('Subscriptions', 'fas fa-file-invoice-dollar', Subscription::class)->setController(SubscriptionCrudController::class);
        yield MenuItem::linkToCrud('Vehicules', 'fa fa-car', Vehicule::class)->setController(VehiculeCrudController::class);
        yield MenuItem::linkToCrud('Plans', 'fas fa-book', Plan::class)->setController(PlanCrudController::class);
        
    }
}