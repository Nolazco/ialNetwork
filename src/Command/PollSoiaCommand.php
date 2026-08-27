<?php

namespace App\Command;

use App\Entity\ImportRequest;
use App\Soia\ModuladoConfirmer;
use App\Workflow\ImportRequestWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Consulta el SOIA por los expedientes que ya deberían estar modulados.
 *
 * Pensado para correr por cron cada pocos minutos: es este comando el que
 * decide, expediente por expediente, si ya le toca consultar (una hora
 * después de la cita más próxima, y no antes de 20 minutos desde la última
 * consulta), así que no importa que el cron corra más seguido que esa regla.
 */
#[AsCommand(
    name: 'app:soia:poll',
    description: 'Consulta el SOIA de los expedientes en Programado que ya llevan tiempo esperando la modulación',
)]
class PollSoiaCommand extends Command
{
    private const WAIT_AFTER_DESPACHO = '+1 hour';
    private const RECHECK_INTERVAL = '+20 minutes';

    /** Pausa entre consultas de la misma corrida, para no golpear el portal de un jalón. */
    private const PAUSE_BETWEEN_CHECKS_SECONDS = 1;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ModuladoConfirmer $confirmer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        $candidates = $this->entityManager->getRepository(ImportRequest::class)
            ->findBy(['status' => ImportRequestWorkflow::SCHEDULED]);

        $checked = 0;
        $modulated = 0;

        foreach ($candidates as $import) {
            $despachoAt = $this->earliestDespacho($import);

            if ($despachoAt === null) {
                continue;
            }

            if ($now < $despachoAt->modify(self::WAIT_AFTER_DESPACHO)) {
                continue;
            }

            $lastCheck = $import->getLastSoiaCheckAt();

            if ($lastCheck !== null && $now < $lastCheck->modify(self::RECHECK_INTERVAL)) {
                continue;
            }

            ++$checked;
            $result = $this->confirmer->attemptConfirm($import);

            if ($import->getStatus() === ImportRequestWorkflow::MODULATED) {
                ++$modulated;
                $io->writeln(sprintf('[%s] Expediente %s modulado (%s).', $now->format('c'), $import->getClientReference(), $result->estado));
            }

            if ($checked < count($candidates)) {
                sleep(self::PAUSE_BETWEEN_CHECKS_SECONDS);
            }
        }

        $io->writeln(sprintf('[%s] Revisados: %d. Modulados: %d.', $now->format('c'), $checked, $modulated));

        return Command::SUCCESS;
    }

    /**
     * Fecha+hora del despacho más próximo asignado al expediente, o null si
     * todavía no tiene ninguno (no debería pasar: llegar a Programado ya
     * exige al menos un despacho, salvo que se haya satisfecho el gate con el
     * comprobante de cita en vez de con el aviso al transporte).
     */
    private function earliestDespacho(ImportRequest $import): ?\DateTimeImmutable
    {
        $earliest = null;

        foreach ($import->getDeliveries() as $delivery) {
            $at = $delivery->getDate()->setTime(
                (int) $delivery->getHour()->format('H'),
                (int) $delivery->getHour()->format('i'),
            );

            if ($earliest === null || $at < $earliest) {
                $earliest = $at;
            }
        }

        return $earliest;
    }
}
