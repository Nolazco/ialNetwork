<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getStatus() === 'pending') {
            throw new CustomUserMessageAccountStatusException('Cuenta pendiente de validación.');
        }

        if ($user->getStatus() === 'inactive') {
            throw new CustomUserMessageAccountStatusException('Cuenta inactiva o rechazada.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Nothing to check after authentication.
    }
}
