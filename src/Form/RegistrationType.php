<?php

namespace App\Form;

use App\Entity\User;
use App\Form\ReCaptchaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\StringType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Gregwar\CaptchaBundle\Type\CaptchaType;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class,['required' => true, 'label' => false, 'attr' => ["placeholder" => 'Email address']])
            ->add('password', PasswordType::class,['required' => true, 'label' => false, 'attr' => ["placeholder" => 'Password'], 'help' => "Doit contenir au moins 7 caractères dont une majuscule, une minuscule et un chiffre"])
            ->add('confirm_password', PasswordType::class,['required' => true, 'label' => false, 'attr' => ["placeholder" => 'Confirm password']])
            //->add('first_name', TextType::class,['attr' => ["class" => "form-control"]])
            //->add('last_name', TextType::class,['attr' => ["class" => "form-control"]])
            //->add('company', TextType::class,['required' => false, 'label' => false, 'attr' => ["placeholder" => 'Company']])
            ->add('captcha', CaptchaType::class, ['required' => true, 'label' => false, 'help' => "Antirobot: merci de saisir les caractères ci-dessus", 'invalid_message' => 'Le code visuel saisi ne correspond pas', 'attr' => ['class' => 'mt-1']]);
            /*->add('captcha', ReCaptchaType::class, [
                'type' => 'checkbox' // (invisible, checkbox)
             ]);*/
           
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
