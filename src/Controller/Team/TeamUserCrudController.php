<?php

namespace App\Controller\Team;

use App\Entity\User;
use App\Entity\Subscription;
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
            ->setPageTitle(Crud::PAGE_INDEX, 'Membres de mon équipe')
            ->setDefaultSort(['last_name' => 'ASC', 'first_name' => 'ASC'])
            ->setSearchFields(['first_name', 'last_name', 'email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $impersonate = Action::new('impersonate', 'Se connecter', 'fa fa-user-secret')
            ->linkToUrl(function (User $user) {
                return $this->generateUrl('app', [
                    '_switch_user' => $user->getEmail(),
                ]);
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $impersonate)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_EDIT, Action::INDEX);
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
            ->setParameter('me', $me);

        return $qb;
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
            $entityInstance->setCompany($me->getCompany());
            $entityInstance->setBalanceStartPeriod($me->getBalanceStartPeriod());

            if (!$entityInstance->getBalanceStartPeriod()) {
                $entityInstance->setBalanceStartPeriod($me->getBalanceStartPeriod());
            }

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
        if ($pageName === Crud::PAGE_INDEX) {
            yield Field::new('first_name', 'Prénom');
            yield Field::new('last_name', 'Nom');
            yield EmailField::new('email', 'E-mail');
            return;
        }

        if ($pageName === Crud::PAGE_NEW) {
            yield FormField::addPanel('Identifiants')->setIcon('fa fa fa-user');
            yield Field::new('email', 'E-mail address');

            yield Field::new('plainPassword')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'required' => true,
                    'type' => PasswordType::class,
                    'first_options' => ['label' => 'Password'],
                    'second_options' => ['label' => 'Password (confirmation)'],
                    'invalid_message' => 'Les mots de passe ne correspondent pas.',
                ]);

            yield FormField::addPanel('Informations personnelles')->setIcon('fa fa fa-id-card');
            yield Field::new('first_name')->setFormTypeOptions(['required' => true]);
            yield Field::new('last_name')->setFormTypeOptions(['required' => true]);

            return;
        }

        if ($pageName === Crud::PAGE_EDIT) {
            yield FormField::addPanel('Identifiants')->setIcon('fa fa fa-user');
            yield Field::new('email', 'E-mail address');

            yield Field::new('plainPassword')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'required' => false,
                    'type' => PasswordType::class,
                    'first_options' => ['label' => 'Password'],
                    'second_options' => ['label' => 'Password (confirmation)'],
                    'invalid_message' => 'Les mots de passe ne correspondent pas.',
                ])
                ->setHelp('Laisse vide si tu ne veux pas changer le mot de passe.');

            yield FormField::addPanel('Informations personnelles')->setIcon('fa fa fa-id-card');
            yield Field::new('first_name')->setFormTypeOptions(['required' => true]);
            yield Field::new('last_name')->setFormTypeOptions(['required' => true]);

            return;
        }
    }
}
