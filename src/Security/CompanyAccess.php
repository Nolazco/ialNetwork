<?php

namespace App\Security;

use App\Entity\Company;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Decide si un usuario puede ver o tocar los datos de una empresa.
 *
 * La agencia ve todo. Un cliente solo las empresas a las que esta afiliado, y de
 * ahi cuelga todo lo demas: sus expedientes, sus documentos y sus cuentas de
 * gastos. Sin esto, el firewall solo comprobaba que hubiera sesion iniciada, asi
 * que cualquier cuenta podia leer los datos de cualquier empresa cambiando el id
 * o el RFC en la URL.
 */
final class CompanyAccess
{
    public function __construct(private readonly Security $security)
    {
    }

    public function canAccess(?Company $company): bool
    {
        if ($company === null) {
            return false;
        }

        if ($this->security->isGranted('ROLE_EXECUTIVE')) {
            return true;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return false;
        }

        foreach ($company->getAssociateds() as $association) {
            if ($association->getIdClient() === $user) {
                return true;
            }
        }

        return false;
    }
}
