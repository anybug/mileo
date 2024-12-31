<?php

namespace App\Security;

use App\Controller\App\DashboardAppController;
use App\Controller\App\UserAppCrudController;
use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserProviderInterface;


/**
 * Created by IntelliJ IDEA.
 * User: mert
 * Date: 12/18/17
 * Time: 12:00 PM
 */
class GoogleAuthenticator extends SocialAuthenticator
{
    private $clientRegistry;
    private $em;
    private $router;
    private $login;
    private $security;
    private $session;
    private $adminUrlGenerator;
    private $flashBag;
    private $entityManager;
    private $passwordHasher;
    
    public function __construct(UserPasswordHasherInterface $passwordHasher,EntityManagerInterface $entityManager,FlashBagInterface $flashBag,AdminUrlGenerator $adminUrlGenerator,SessionInterface $session,ClientRegistry $clientRegistry, EntityManagerInterface $em, RouterInterface $router,LoginFormAuthenticator $login, Security $security)
    {
        $this->clientRegistry = $clientRegistry;
        $this->em = $em;
        $this->router = $router;
        $this->login = $login;
        $this->security = $security;
        $this->session = $session;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->flashBag = $flashBag;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    public function supports(Request $request)
    {
        return $request->getPathInfo() == '/connect/google/check' && $request->isMethod('GET');
    }

    public function getCredentials(Request $request)
    {
        return $this->fetchAccessToken($this->getGoogleClient());
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        /** @var GoogleUser $googleUser */
        $googleUser = $this->getGoogleClient()
            ->fetchUserFromToken($credentials);

        $google_id = $googleUser->getId();
        
        if ($this->security->getUser() == null) {
            $user = $this->em->getRepository(User::class)
                ->findOneBy(['google_id' => $google_id]);
            
            if (!$user) {
                $sub = new Subscription;
                $plan = $this->entityManager->getRepository(Plan::class)->findOneBy(['price_per_year' => 0]);

                $now = new DateTime('now');
                $sub->setPlan($plan);
                $sub->setSubscriptionStart(new DateTime('now'));
                $sub->setSubscriptionEnd($now->modify("+ 6 month"));

                $user = new User();
                $user->setEmail($googleUser->getEmail());
                $user->setUsername($googleUser->getEmail());
                $user->setFirstName($googleUser->getFirstName());
                $user->setLastName($googleUser->getLastName());
                $user->setGoogleId($googleUser->getId());
                $user->setRoles(["ROLE_USER"]);
                $comb = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
                $shfl = str_shuffle($comb);
                $str = substr($shfl,0,6);
                $pwd = sprintf("Ab1%s",$str);
                $hashedPassword = $this->passwordHasher->hashPassword($user ,$pwd);
                $user->setPassword($hashedPassword);
                $user->setSubscription($sub);
                $this->em->persist($user);
                $this->em->flush();

            }
        }
        else {
            $alreadyExist = $this->em->getRepository(User::class)
                ->findOneBy(['google_id' => $google_id]);
            if($alreadyExist) {
                $this->session->set('error','Ce compte google est déjà attribué à un compte Mileo');
                return null;
            }
            $user = $this->security->getUser();
            $user->setGoogleId($googleUser->getId());
            $this->em->persist($user);
            $this->em->flush();
        }
        return $user;
    }

    /**
     * @return \KnpU\OAuth2ClientBundle\Client\OAuth2Client
     */
    private function getGoogleClient()
    {
        return $this->clientRegistry
            ->getClient('google');
    }

    /**
     * Returns a response that directs the user to authenticate.
     *
     * This is called when an anonymous request accesses a resource that
     * requires authentication. The job of this method is to return some
     * response that "helps" the user start into the authentication process.
     *
     * Examples:
     *  A) For a form login, you might redirect to the login page
     *      return new RedirectResponse('/login');
     *  B) For an API token authentication system, you return a 401 response
     *      return new Response('Auth header required', 401);
     *
     * @param Request $request The request that resulted in an AuthenticationException
     * @param \Symfony\Component\Security\Core\Exception\AuthenticationException $authException The exception that started the authentication process
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function start(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $authException = null)
    {
        return new RedirectResponse('/login');
    }
    
    /**
     * Called when authentication executed, but failed (e.g. wrong username password).
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the login page or a 403 response.
     *
     * If you return null, the request will continue, but the user will
     * not be authenticated. This is probably not what you want to do.
     *
     * @param Request $request
     * @param \Symfony\Component\Security\Core\Exception\AuthenticationException $exception
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception)
    {
        $session = $this->session;
        if($session->get('error')){
            $session->getFlashBag()->add(
                'warning',
                $session->get('error')
            );

            $url = $this->adminUrlGenerator
            ->setDashboard(DashboardAppController::class)
            ->setController(UserAppCrudController::class)
            ->setAction('index')
            ->generateUrl();
            return new RedirectResponse($url);
        }
        return new RedirectResponse('/login');
    }

    /**
     * Called when authentication executed and was successful!
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the last page they visited.
     *
     * If you return null, the current request will continue, and the user
     * will be authenticated. This makes sense, for example, with an API.
     *
     * @param Request $request
     * @param \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token
     * @param string $providerKey The provider (i.e. firewall) key
     *
     * @return void
     */
    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, $providerKey)
    {
        return $this->login->onAuthenticationSuccess($request, $token, $providerKey);
    }
}