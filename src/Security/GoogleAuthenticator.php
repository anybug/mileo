<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Event\UserFirstLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;


class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private UserPasswordHasherInterface $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
        private MailerInterface $mailer,
    ) {}

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('google');

        if (!$request->query->has('code')) {
            throw new AuthenticationException('Google login cancelled by user.');
        }
        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUser();

        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();

        return new SelfValidatingPassport(
            new UserBadge($email, function ($userIdentifier) use ($googleUser, $googleId) {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $userIdentifier]);

                $isNew = false;

                if (!$user) {
                    $user = new User();
                    $user->setEmail($googleUser->getEmail());
                    $user->setGoogleId($googleId);
                    $user->setFirstName($googleUser->getFirstName());
                    $user->setLastName($googleUser->getLastName());
                    //$user->setPassword(null); // pas de mot de passe
                    $comb = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
                    $shfl = str_shuffle($comb);
                    $str = substr($shfl,0,6);
                    $pwd = sprintf("Ab1%s",$str);
                    $hashedPassword = $this->passwordHasher->hashPassword($user ,$pwd);
                    $user->setPassword($hashedPassword);
                    $user->setAuthProvider('google');
                    $this->em->persist($user);
                    $this->em->flush();

                    $email = (new TemplatedEmail())
                        ->to(new Address($user->getEmail()))
                        ->subject('Bienvenue sur Mileo !')
                        ->htmlTemplate('Emails/welcome.html.twig')
                        ->context(['user' => $user]);

                    $this->mailer->send($email);

                     if ($isNew || !$user->getSubscription()) {
                        $this->eventDispatcher->dispatch(
                            new UserFirstLoginEvent($user)
                        );
                    }
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('app'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {

        return new RedirectResponse($this->urlGenerator->generate('security_registration'));
    }
}
