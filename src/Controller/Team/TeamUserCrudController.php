<?php

namespace App\Controller\Team;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER')]
class TeamUserCrudController extends AbstractCrudController
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Membre')
            ->setEntityLabelInPlural('Membres')
            ->setPageTitle(Crud::PAGE_INDEX, 'Membres collaborateurs de l\'équipe <br /><span class="fs-6 fw-normal">Gestion de l\'effectif de votre équipe: chacun des membres peut se connecter à la plateforme indépendamment afin d\'effectuer sa saisie en toute autonomie. <br />Vous pouvez aussi vous connecter à leur compte à des fins de saisie ou de vérification.</span>')
            ->setDefaultSort(['last_name' => 'ASC', 'first_name' => 'ASC'])
            ->setSearchFields(['first_name', 'last_name', 'email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $impersonate = Action::new('impersonate', 'Se connecter', 'fa-solid fa-person-walking-arrow-right')
            ->linkToUrl(function (User $user) {
                return $this->generateUrl('app', [
                    '_switch_user' => $user->getEmail(),
                ]);
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $impersonate)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->displayIf(function ($entity) {
                    return $entity != $this->getUser();
                });
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->displayIf(function ($entity) {
                    return $entity != $this->getUser();
                });
            })
            ;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        /** @var User $me */
        $me = $this->getUser();

        $qb->andWhere('entity.managedBy = :me')
            ->orWhere('entity = :me')
            ->setParameter('me', $me);

        return $qb;
    }

    public function createEntity(string $entityFqcn)
    {
        /** @var User $me */
        $me = $this->getUser();

        $member = new $entityFqcn;
        $member->setCompany($me->getCompany());
        $member->setBalanceStartPeriod($me->getBalanceStartPeriod());

        return $member;
    }

    public function edit(AdminContext $context)
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        /** @var User $me */
        $me = $this->getUser();

        if ($user->getManagedBy()?->getId() !== $me->getId()) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public function delete(AdminContext $context)
    {
        /** @var User $target */
        $target = $context->getEntity()->getInstance();

        /** @var User $me */
        $me = $this->getUser();

        if ($target->getManagedBy()?->getId() !== $me->getId()) {
            throw new AccessDeniedHttpException();
        }

        if ($target->getId() === $me->getId()) {
            throw new AccessDeniedHttpException();
        }

        return parent::delete($context);
    }

   public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            /** @var User $me */
            $me = $this->getUser();

            $entityInstance->setManagedBy($me);
            $entityInstance->setRoles(['ROLE_USER']);

            $this->copySubscriptionFromManager($entityInstance, $me);

            $this->encodePassword($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }


    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->encodePassword($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function encodePassword(User $user): void
    {
        if ($user->getPlainPassword() != null) {
            $hash = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
            $user->setPassword($hash);
        }
    }

    private function copySubscriptionFromManager(User $member, User $manager): void
    {
        $managerSub = $manager->getSubscription();
        if (!$managerSub) {
            throw new \LogicException('Le manager n’a pas de subscription : impossible de créer un membre.');
        }

        if ($member->getSubscription()) {
            return;
        }

        $sub = new Subscription();

        $sub->setPlan($managerSub->getPlan());
        $sub->setSubscriptionStart(clone $managerSub->getSubscriptionStart());
        $sub->setSubscriptionEnd(clone $managerSub->getSubscriptionEnd());

        $sub->setUser($member);

        $member->setSubscription($sub);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX || $pageName === Crud::PAGE_DETAIL) {
            yield Field::new('first_name', 'Prénom');
            yield Field::new('last_name', 'Nom');
            yield EmailField::new('email', 'E-mail');
            yield DateTimeField::new('last_login', 'Dernière connexion');
            yield CollectionField::new('reports', 'Nb reports');
            return;
        }

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield FormField::addColumn(6);
            yield FormField::addFieldset('Informations personnelles')->setIcon('fa fa fa-id-card');
            yield Field::new('first_name')->setFormTypeOptions(['required' => true])->setColumns(6);
            yield Field::new('last_name')->setFormTypeOptions(['required' => true])->setColumns(6);
            yield Field::new('company');
            yield ChoiceField::new('balanceStartPeriod')
                ->setColumns('col-12')
                //->setHelp('Modifier votre période fiscale modifie également celle de vos collaborateurs')
                ->setChoices(fn () => [
                    'Janvier' => 'January',
                    'Février' => 'February',
                    'Mars' => 'March',
                    'Avril' => 'April',
                    'Mai' => 'May',
                    'Juin' => 'June',
                    'Juillet' => 'July',
                    'Août' => 'August',
                    'Septembre' => 'September',
                    'Octobre' => 'October',
                    'Novembre' => 'November',
                    'Décembre' => 'December',
                ]);
            
            yield FormField::addColumn(6);
            yield FormField::addFieldset('Profil')->setIcon('fa fa fa-user');
            yield Field::new('email', 'Adresse e-mail')->setHelp('L\'adresse e-mail est utilisée comme nom d\'utilisateur pour se connecter à la plateforme');
            yield Field::new('plainPassword')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'required' => true,
                    'type' => PasswordType::class,
                    'first_options' => ['label' => 'Password'],
                    'second_options' => ['label' => 'Password (confirmation)'],
                    'invalid_message' => 'Les mots de passe ne correspondent pas.',
            ]);
            yield BooleanField::new('is_active', 'Profil activé')->setHelp("Si désactivé, l'utilisateur ne peut pas se connecter à la plateforme");

            return;
        }

    }
}
