<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use App\Form\ReCaptchaType;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Gregwar\CaptchaBundle\Type\CaptchaType;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['required' => true, 'label' => false, 'attr' => ['placeholder' => 'Nom *']])
            ->add('email', EmailType::class, ['required' => true, 'label' => false, 'attr' => ['placeholder' => 'Adresse e-mail *'], 'invalid_message' => "Merci de saisir une adresse mail valide"])
            ->add('phone', TelType::class, ['required' => true, 'label' => false, 'attr' => ['placeholder' => 'Téléphone *'], 'invalid_message' => "Merci de saisir un numéro valide"])
            ->add('company', TextType::class, ['required' => false, 'label' => false, 'attr' => ['placeholder' => 'Entreprise']])
            ->add('message', TextareaType::class, ['required' => true, 'label' => false, 'attr' => ['placeholder' => 'Votre message *', 'rows' => 6]])
            ->add('captcha', CaptchaType::class, ['required' => true, 'label' => false, 'help' => "Antirobot: merci de saisir les caractères ci-dessus", 'invalid_message' => 'Le code visuel saisi ne correspond pas', 'attr' => ['class' => 'mt-1']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
