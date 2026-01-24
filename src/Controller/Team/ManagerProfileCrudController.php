<?php

namespace App\Controller\Team;

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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER')]
class ManagerProfileCrudController extends AbstractCrudController
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

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->andWhere('entity.id = :user')
           ->setParameter('user', $this->getUser()->getId());

        return $qb;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(null)
            ->overrideTemplate('crud/index', 'Team/Profile/index.html.twig')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier mon profil')
            ->overrideTemplate('crud/edit', 'Team/advanced_edit.html.twig')
            ->overrideTemplate('crud/new', 'Team/advanced_new.html.twig');
    }

    public function configureActions(Actions $actions): Actions
{
    return $actions
        ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
        ->disable(Action::NEW)
        ->disable(Action::DELETE)

        ->add(Crud::PAGE_EDIT, Action::INDEX)
        ->update(Crud::PAGE_EDIT, Action::INDEX, function (Action $action) {
            return $action
                ->setLabel('Retour au profil')
                ->setIcon('fa fa-arrow-left')
                ->setCssClass('btn btn-secondary');
        });
}

    public function edit(AdminContext $context)
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();
        $currentUser = $this->getUser();

        if ($user !== $currentUser) {
            throw new AccessDeniedHttpException();
        }

        return parent::edit($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->encodePassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** TODO: update members profile: copy company and balance period */
        $this->encodePassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function encodePassword(User $user): void
    {
        if ($user->getPlainPassword() != null) {
            $hash = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
            $user->setPassword($hash);
        }
    }

    public function configureFields(string $pageName): iterable
    {
        if (!$this->getUser()->getGoogleId()) {
            yield FormField::addPanel('Identifiants')
                ->setIcon('fa fa fa-user')
                ->setCssClass('col-sm-12 col-lg-6 col-xxl-6');

            yield Field::new('email', 'E-mail address')->setColumns('col-12');

            yield Field::new('plainPassword')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'required' => false,
                    'options' => ['attr' => ['autocomplete' => 'off']],
                    'type' => PasswordType::class,
                    'first_options' => ['label' => 'Password'],
                    'second_options' => ['label' => 'Password (confirmation)'],
                    'invalid_message' => 'Les mots de passe ne correspondent pas.',
                ])
                ->setColumns('col-12');
        }

        yield FormField::addPanel('Informations personnelles')
            ->setIcon('fa fa fa-id-card')
            ->setCssClass('col-sm-12 col-lg-6 col-xxl-6');

        yield Field::new('first_name')->setFormTypeOptions(['required' => true])->setColumns('col-12');
        yield Field::new('last_name')->setFormTypeOptions(['required' => true])->setColumns('col-12');
        yield Field::new('company')->setColumns('col-12');

        yield ChoiceField::new('balanceStartPeriod')
            ->setColumns('col-12')
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
    }
}
