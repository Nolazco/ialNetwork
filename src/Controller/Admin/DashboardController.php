<?php

namespace App\Controller\Admin;

use App\Entity\Associated;
use App\Entity\Client;
use App\Entity\Company;
use App\Entity\CompanyDocument;
use App\Entity\Container;
use App\Entity\ContainerYard;
use App\Entity\Delivery;
use App\Entity\EmptyReturn;
use App\Entity\Executive;
use App\Entity\FreightHauler;
use App\Entity\ImportDocument;
use App\Entity\ImportRequest;
use App\Entity\InternInvoice;
use App\Entity\Operation;
use App\Entity\Provider;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        //return parent::index();

        return $this->render('easyAdmin/index.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('IalNetwork');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Usuarios', 'fas fa-user', User::class);
        yield MenuItem::linkToCrud('Clientes-Empresas', 'fas fa-user', Associated::class);
        yield MenuItem::linkToCrud('Clientes', 'fas fa-user', Client::class);
        yield MenuItem::linkToCrud('Empresas', 'fas fa-user', Company::class);
        yield MenuItem::linkToCrud('Documentos empresas', 'fas fa-user', CompanyDocument::class);
        yield MenuItem::linkToCrud('Contenedores', 'fas fa-user', Container::class);
        yield MenuItem::linkToCrud('Patios de contenedores', 'fas fa-user', ContainerYard::class);
        yield MenuItem::linkToCrud('Despachos', 'fas fa-user', Delivery::class);
        yield MenuItem::linkToCrud('Vacios', 'fas fa-user', EmptyReturn::class);
        yield MenuItem::linkToCrud('Ejecutivos', 'fas fa-user', Executive::class);
        yield MenuItem::linkToCrud('Transportistas', 'fas fa-user', FreightHauler::class);
        yield MenuItem::linkToCrud('Documentos de importacion', 'fas fa-user', ImportDocument::class);
        yield MenuItem::linkToCrud('Pedimentos', 'fas fa-user', ImportRequest::class);
        yield MenuItem::linkToCrud('Facturas', 'fas fa-user', InternInvoice::class);
        yield MenuItem::linkToCrud('Maniobras', 'fas fa-user', Operation::class);
        yield MenuItem::linkToCrud('Proveedores', 'fas fa-user', Provider::class);
    }
}
