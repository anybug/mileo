<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

use App\Form\ResetPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use App\Entity\Order;
use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Purchase;
use App\Form\ReCaptchaType;
use App\Entity\Subscription;
use App\Form\RegistrationType;
use App\Security\LoginFormAuthenticator;
use App\Security\LoginFormAuthenticatorPayment;

class SecurityController extends AbstractController
{

    /**
     * Registration
     */
    public function registration(SessionInterface $session, Request $request, EntityManagerInterface $manager, UserPasswordHasherInterface $passwordHasher, LoginFormAuthenticator $login,GuardAuthenticatorHandler $guard) 
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app');
        }
        
        $user = new User();
        $user->setLastLogin(new \DateTime());
        
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);
  
        if($form->isSubmitted() && $form->isValid()) {
            //Manage free subscription upon first registration
            $subscription = new Subscription();
            $plan = $manager->getRepository(Plan::class)->findOneBy(['price_per_year' => 0]);
            $subscription->setPlan($plan);
            $subscription->setUser($user);
            $subscription->setSubscriptionStart(new \DateTime());
            $subscription->setSubscriptionEnd(new \DateTime('+'.$plan->getPlanPeriod().' month'));


            //Manage a free order object
            $order = new Order();
            $order->setUser($user);
            $order->setPlan($plan);
            $order->setCreatedAt(new \DateTime());
            $order->setUpdatedAt(new \DateTime());
            $order->setProductName($plan->getName());
            $order->setProductDescription($plan->getPlanDescription());
            $order->setVatAmount($plan->getTotalCost());
            $order->setTotalHt($plan->getTotalCost());
            $order->setSubscriptionEnd($subscription->getSubscriptionEnd());
            $order->setStatus("new");

            $manager->persist($order);

            //Manage user object before registration
            $user->setSubscription($subscription);
            $user->setUsername($user->getEmail());
            $user->setRoles(["ROLE_USER"]);
            $hash = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hash);
            $manager->persist($user);
            $manager->flush();
            //Redirect to home and autologin after registration
            $session->set('registration', true);
            //$this->addFlash('success', "Votre compte avec l'abonnement ". $session->get('subscriptionPlan')->getName() ." a bien été créé");

            return $guard->authenticateUserAndHandleSuccess($user, $request, $login, 'main');

            //return $this->redirectToRoute('home');
        }    


        return $this->render('Front/register.html.twig', [
            'form' => $form->createView(),
        ]);

    }

    /**
    * Login
    */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('Front/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/reset-password', name:'forgotten_password')]
    public function forgottenPassword(Request $request,UserRepository $usersRepository,TokenGeneratorInterface $tokenGenerator,EntityManagerInterface $entityManager,MailerInterface $mail): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            //On va chercher l'utilisateur par son email
            $user = $usersRepository->findOneBy(array('email' => $form->get('email')->getData()));

            // On vérifie si on a un utilisateur
            if($user){
                // On génère un token de réinitialisation
                $token = $tokenGenerator->generateToken();
                $user->setResetToken($token);
                $entityManager->persist($user);
                $entityManager->flush();

                // On génère un lien de réinitialisation du mot de passe
                $url = $this->generateUrl('reset_pass', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                
                $email = (new TemplatedEmail())
                    ->to(new Address($user->getEmail()))
                    ->subject('Réinitialisation de mot de passe')
                    ->htmlTemplate('security/email.html.twig')
                    ->context([
                        'user' => $user,
                        'url' => $url
                    ]);
            
                $mail->send($email);

                $this->addFlash('success', 'Un email avec les instructions pour réinitialiser votre mot de passe vient de vous être envoyé.');
                return $this->redirectToRoute('security_login');
            }
            // $user est null
            $this->addFlash('danger', 'Adresse email non reconnue');
            
            return $this->redirectToRoute('security_login');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'requestPassForm' => $form->createView()
        ]);
    }

    #[Route('/reset-password/{token}', name:'reset_pass')]
    public function resetPass(
        string $token,
        Request $request,
        UserRepository $usersRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        // On vérifie si on a ce token dans la base
        $user = $usersRepository->findOneBy(array('resetToken' => $token));
        
        // On vérifie si l'utilisateur existe

        if($user){
            $form = $this->createForm(ResetPasswordFormType::class);

            $form->handleRequest($request);

            if($form->isSubmitted() && $form->isValid()){
                // On efface le token
                $user->setResetToken('');
                
                
        // On enregistre le nouveau mot de passe en le hashant
                $user->setPassword(
                    $passwordHasher->hashPassword(
                        $user,
                        $form->get('password')->getData()
                    )
                );
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe changé avec succès');
                return $this->redirectToRoute('security_login');
            }

            return $this->render('security/reset_password.html.twig', [
                'passForm' => $form->createView()
            ]);
        }
        
        // Si le token est invalide on redirige vers le login
        $this->addFlash('danger', 'Lien invalide - Invalid token');
        return $this->redirectToRoute('security_login');
    }
}    