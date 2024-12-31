<?php

namespace App\Command;

use App\Controller\App\DashboardAppController;
use App\Controller\App\UserAppCrudController;
use App\Repository\CommentRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class WarningSubscriptionEndCommand extends Command
{
    private $userRepository;
    private $mailer;
    private $adminUrlGenerator;

    protected static $defaultName = 'app:warning:subscription';

    public function __construct(UserRepository $userRepository, MailerInterface $mailer, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->adminUrlGenerator = $adminUrlGenerator;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Deletes rejected and spam comments from the database')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            if ($user->getSubscription()) {
                if ($user->getSubscription()->isWarningMail()) {
                    $url = $this->adminUrlGenerator
                        ->setDashboard(DashboardAppController::class)
                        ->setController(UserAppCrudController::class)
                        ->setAction('subscriptionForm')
                        ->generateUrl();

                    $url = str_replace("http","https",$url);
                    $url = str_replace("localhost",$_ENV["DOMAIN_NAME"],$url);

                    $email = (new TemplatedEmail())
                    ->to(new Address($user->getEmail()))
                    ->subject('Votre abonnement Mileo arrive à expiration')
                    ->htmlTemplate('Emails/subscriptionWarning.html.twig')
                    ->context([
                        'user' => $user,
                        'url' => $url
                    ]);

                    $this->mailer->send($email);

                    $io->info("fin d'abonnement dans 1 mois pour ".$user);
                }
            }
        }
        $io->success("fin de tache");
        return 0;
    }
}