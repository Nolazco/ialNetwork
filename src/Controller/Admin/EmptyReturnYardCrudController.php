<?php

namespace App\Controller\Admin;

use App\Entity\EmptyReturnYard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class EmptyReturnYardCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EmptyReturnYard::class;
    }
}
