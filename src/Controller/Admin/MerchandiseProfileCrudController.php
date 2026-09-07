<?php

namespace App\Controller\Admin;

use App\Entity\MerchandiseProfile;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class MerchandiseProfileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MerchandiseProfile::class;
    }
}
