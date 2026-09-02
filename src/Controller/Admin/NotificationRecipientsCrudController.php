<?php

namespace App\Controller\Admin;

use App\Entity\NotificationRecipients;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Las 6 filas (una por cada Mailer::TO_KEY/CC_KEY) las siembra la migración;
 * aquí solo se edita la lista de correos de cada una — key/label quedan de
 * solo lectura porque el código las busca por ese nombre exacto, y "Nuevo"/
 * "Eliminar" se deshabilitan para que no se creen filas huérfanas ni se
 * borre una que algún Mailer sigue buscando.
 */
class NotificationRecipientsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationRecipients::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Correos de notificación')
            ->setEntityLabelInPlural('Correos de notificaciones');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('key', 'Clave')->setFormTypeOption('disabled', true),
            TextField::new('label', 'Descripción')->setFormTypeOption('disabled', true),
            BooleanField::new('required', 'Obligatoria')->renderAsSwitch(false)->hideOnForm(),
            ArrayField::new('emails', 'Correos'),
        ];
    }
}
