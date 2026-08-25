<?php

namespace App\DataFixtures;

use App\Entity\Associated;
use App\Entity\Company;
use App\Entity\ContainerYard;
use App\Entity\Provider;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Catalogos minimos para que el flujo se pueda recorrer de punta a punta.
 *
 * Sin al menos un recinto y un proveedor, el formulario de alta de pedimentos
 * queda con los selectores vacios; sin una empresa asociada, el cliente no tiene
 * sobre que dar de alta.
 */
class CatalogFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $yard = new ContainerYard();
        $yard->setName('SSA Manzanillo');
        $yard->setCr('39');

        $manager->persist($yard);

        foreach ([
            ['Proveedor Uno', 'PUN010101AB1', 'Av. Siempre Viva 100, Shanghai'],
            ['Proveedor Dos', 'PDO020202CD2', 'Calle Segunda 200, Busan'],
        ] as [$name, $taxId, $address]) {
            $provider = new Provider();
            $provider->setName($name);
            $provider->setTaxId($taxId);
            $provider->setAddress($address);

            $manager->persist($provider);
        }

        $company = new Company();
        $company->setName('Importadora QA');
        $company->setRfc('IQA010101QA1');
        $company->setAddress('Blvd. Costero 500, Manzanillo, Colima');

        $manager->persist($company);

        // El cliente solo ve las empresas con las que esta asociado.
        $association = new Associated();
        $association->setIdClient($this->getReference(UserFixtures::CLIENT, User::class));
        $association->setIdCompany($company);

        $manager->persist($association);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
