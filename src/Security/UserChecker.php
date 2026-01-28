<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

class UserChecker implements UserCheckerInterface
{
    private $userManager;

    public function __construct(EntityManagerInterface $userManager){
        $this->userManager = $userManager;
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException(
                'Compte désactivé !'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        $this->checkPreAuth($user);
    }
}