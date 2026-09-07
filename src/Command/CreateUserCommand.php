<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Da de alta una cuenta activa desde consola.
 *
 * El registro publico solo crea clientes en estado "pending", asi que no habia
 * forma de crear el primer administrador sin tocar la base a mano. La contraseña
 * se pide de forma oculta para que no quede en el historial de la terminal ni en
 * ningun archivo del repositorio.
 */
#[AsCommand(
    name: 'app:user:create',
    description: 'Crea una cuenta de usuario activa con el rol indicado',
)]
class CreateUserCommand extends Command
{
    private const ROLES = ['ROLE_ADMIN', 'ROLE_EXECUTIVE', 'ROLE_CLIENT', 'ROLE_FH'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Correo electronico')
            ->addArgument('role', InputArgument::REQUIRED, 'Uno de: '.implode(', ', self::ROLES))
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nombre', '')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Apellidos', '')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Contraseña (si se omite, se pide de forma oculta)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $role = strtoupper($input->getArgument('role'));

        if (!str_starts_with($role, 'ROLE_')) {
            $role = 'ROLE_'.$role;
        }

        if (!in_array($role, self::ROLES, true)) {
            $io->error(sprintf('Rol invalido "%s". Usa uno de: %s', $role, implode(', ', self::ROLES)));

            return Command::FAILURE;
        }

        if ($this->users->findOneBy(['email' => $email])) {
            $io->error(sprintf('Ya existe una cuenta con el correo "%s".', $email));

            return Command::FAILURE;
        }

        $password = $input->getOption('password');

        if (!$password) {
            $question = new Question('Contraseña: ');
            $question->setHidden(true);
            // Sin terminal interactiva (por ejemplo "docker compose exec -T") no
            // se puede ocultar la entrada; en ese caso se lee de stdin, que
            // permite alimentarla por tuberia sin dejarla en el historial.
            $question->setHiddenFallback(true);

            $password = $io->askQuestion($question);
        }

        if (!$password) {
            $io->error('La contraseña no puede quedar vacia.');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($input->getOption('name'));
        $user->setLastName($input->getOption('last-name'));
        $user->setRoles([$role]);
        $user->setStatus('active');
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Cuenta "%s" creada con el rol %s y estatus active.', $email, $role));

        return Command::SUCCESS;
    }
}
