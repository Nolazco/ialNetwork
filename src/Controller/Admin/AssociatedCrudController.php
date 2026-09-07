<?php

namespace App\Controller\Admin;

use App\Entity\Associated;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AssociatedCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Associated::class;
    }

    /**
     * Quitar una afiliación es cosa del administrador (ver
     * DashboardAffiliations::delete(), el mismo candado ahí) — sin esto
     * cualquier ejecutivo podría borrarla desde aquí, ya que el resto de
     * /admin ya le es accesible (ROLE_EXECUTIVE, ver security.yaml).
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
