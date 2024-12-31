<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('billingName', null, ['label' => 'Nom ou organisme','required' => true,'attr' => ['class' => 'form-control-lg']])
            ->add('billingAddress', null, ['label' => 'Adresse','required' => true,'attr' => ['class' => 'form-control-lg']])
            ->add('billingPostcode', IntegerType::class, ['label' => 'Code postal','required' => true,'attr' => ['class' => 'form-control-lg']])
            ->add('billingCity', null, ['label' => 'Ville','required' => true,'attr' => ['class' => 'form-control-lg']])
            ->add('submit',SubmitType::class,['label' => 'Passer au paiement','attr' => ['class' => 'btn-primary p-2']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
