<?php

namespace App\Controller\App;

use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Order;
use App\Form\OrderType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository as EasyAdminEntityRep;

class UserAppCrudController extends AbstractCrudController
{
    private $passwordEncoder;
    private $entityManager;

    public function __construct(UserPasswordHasherInterface $passwordHasher,EntityManagerInterface $entityManager)
    {
        $this->passwordHasher = $passwordHasher;
        $this->entityManager = $entityManager;
    }
    
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->andWhere('entity.id = (:user)');
        $qb->setParameter('user', $this->getUser()->getId());

        return $qb;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
        ->setSearchFields(null)
        ->overrideTemplate('crud/index', 'App/Profile/index.html.twig')
        ->setPageTitle(Crud::PAGE_EDIT, 'Modifier mon profil')
        ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
        ->overrideTemplate('crud/new', 'App/advanced_new.html.twig')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->disable(Action::NEW)
            ->disable(Action::DELETE)
            ;
    }

    public function edit(AdminContext $context)
    {
        $user = $context->getEntity()->getInstance();
        $currentUser = $this->getUser();

        if ($user !== $currentUser) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->encodePassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }
    
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->encodePassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }
    
    private function encodePassword(User $user)
    {
        if ($user->getPlainPassword() !== null) {
            $hash = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
            $user->setPassword($hash);
        }
    }

    
    public function configureFields(string $pageName): iterable
    {
        if(!$this->getUser()->getGoogleId())
        {
            yield FormField::addPanel('Identifiants')->setIcon('fa fa fa-user')->setCssClass('col-sm-12 col-lg-6 col-xxl-6');
            yield Field::new('email', 'E-mail address')->setColumns('col-12');
            yield Field::new('plainPassword')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'required' =>  false,
                    'options' => [
                        'attr' => ['autocomplete' => 'off']
                    ],
                    'type' => PasswordType::class,
                    'first_options' => ['label' => 'Password'],
                    'second_options' => ['label' => 'Password (confirmation)'],
                    'invalid_message' => 'Les mots de passe ne correspondent pas.'
                ])->setColumns('col-12');
        }

        yield FormField::addPanel('Informations personnelles')->setIcon('fa fa fa-id-card')->setCssClass('col-sm-12 col-lg-6 col-xxl-6');
        yield Field::new('first_name')
            ->setFormTypeOptions([
                'required' => true,
                ])
            ->setColumns('col-12');
        yield Field::new('last_name')
            ->setFormTypeOptions([
                'required' => true,
            ])
            ->setColumns('col-12');
        yield Field::new('company')
            ->setColumns('col-12');
        yield ChoiceField::new('balanceStartPeriod')
            ->setColumns('col-12')
            ->setChoices(function (){
                return ['Janvier' => 'January','Février' => 'February','Mars' => 'March','Avril' => 'April','Mai' => "May",'Juin' => 'June','Juillet' => 'July','Août' => 'August','Septembre' => 'September','Octobre' => "October",'Novembre' => 'November','Décembre' => "December"];})
                ;
    }
    
    public function subscriptionForm(Request $request, EntityManagerInterface $manager)
    {
        $order = new Order;
        $plan = $this->entityManager->getRepository(Plan::class)->findOneBy(['id' => 3]);
        $order->setPlan($plan);

        //order name autocomplete
        $order->setBillingName($this->getUser()->getCompany() ?? $this->getUser()->__toString()); 
        
        //TODO: autocomplete billingAddress, billingPostcode, billingCity

        $form = $this->createForm(OrderType::class, $order);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $order->setStatus('pending');
            $order->setUser($this->getUser());
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            return $this->redirectToRoute('payum_prepare_payment', ['order_id' => $order->getId()]);
        }

        return $this->renderForm('App/Profile/order.html.twig', [
            'form' => $form,
            'plan' => $plan
        ]);
    }
}
