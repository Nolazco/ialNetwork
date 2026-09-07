<?php

namespace App\DataFixtures;

use App\Entity\FreightHauler;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Cuentas de prueba, una por rol.
 *
 * DoctrineFixturesBundle solo esta registrado en dev y test
 * (config/bundles.php), asi que estas credenciales no pueden cargarse en
 * produccion. Las cuentas reales se crean con el comando app:user:create, que
 * pide la contraseña por consola y no la deja escrita en ningun archivo.
 */
class UserFixtures extends Fixture
{
    /**
     * Contraseña compartida por todas las cuentas @qa.com. Solo para desarrollo.
     */
    public const QA_PASSWORD = 'Qa123456!';

    public const CLIENT = 'user-cliente';
    public const FREIGHT_HAULER = 'user-transportista';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $accounts = [
            ['admin@qa.com', 'Admin', 'QA', 'ROLE_ADMIN', null],
            ['ejecutivo@qa.com', 'Ejecutivo', 'QA', 'ROLE_EXECUTIVE', null],
            ['cliente@qa.com', 'Cliente', 'QA', 'ROLE_CLIENT', self::CLIENT],
            ['transportista@qa.com', 'Transportista', 'QA', 'ROLE_FH', self::FREIGHT_HAULER],
        ];

        foreach ($accounts as [$email, $name, $lastName, $role, $reference]) {
            $user = new User();
            $user->setEmail($email);
            $user->setName($name);
            $user->setLastName($lastName);
            $user->setRoles([$role]);
            $user->setStatus('active');
            $user->setPassword($this->passwordHasher->hashPassword($user, self::QA_PASSWORD));

            $manager->persist($user);

            if ($reference !== null) {
                $this->addReference($reference, $user);
            }
        }

        // Delivery y EmptyReturn apuntan a un FreightHauler, no al User, asi que
        // la cuenta de transportista necesita el suyo para poder probar el aviso
        // de transporte y la devolucion de vacio.
        $hauler = new FreightHauler();
        $hauler->setIdUser($this->getReference(self::FREIGHT_HAULER, User::class));
        $hauler->setCompanyName('Transportes QA');
        $hauler->setCaat('QA01');
        $hauler->setRfc('TQA010101QA1');

        $manager->persist($hauler);

        $manager->flush();
    }
}
