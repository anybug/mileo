<?php

namespace App\Controller\Team;

use App\Entity\ReportLine;
use App\Entity\User;
use App\Entity\Vehicule;
use Symfony\Component\ExpressionLanguage\Expression;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("ROLE_MANAGER") or is_granted("ROLE_PREVIOUS_ADMIN")'))]
class DashboardManagerController extends AbstractDashboardController
{
    #[Route('/manager', name: 'manager_dashboard')]
    public function index(): Response
    {
        return $this->render('Team/Dashboard/index.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="../assets/img/logo.png" />')
            ->setFaviconPath('assets/img/favicons/favicon.ico')
            ->disableDarkMode();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Équipe');
        yield MenuItem::linkToCrud('Flotte de véhicules', 'fa fa-car', Vehicule::class)
            ->setController(TeamVehiculeCrudController::class);
        yield MenuItem::linkToCrud('Members', 'fa fa-users', User::class)
            ->setController(TeamUserCrudController::class);

        yield MenuItem::section('Parameters');
        yield MenuItem::linkToCrud('Profile', 'fa fa-id-card', User::class)
            ->setController(ManagerProfileCrudController::class);
    }
}
