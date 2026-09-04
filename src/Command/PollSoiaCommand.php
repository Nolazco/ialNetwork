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
 * Pensado para correr por cron cada 5 minutos: es este comando el que
 * decide, expediente por expediente, si ya le toca consultar (30 minutos
 * después de la cita más próxima, y no antes de 5 minutos desde la última
 * consulta), así que no importa que el cron corra más seguido que esa regla.
 *
 * Se rinde tras 100 intentos por expediente (~8 horas de reintentos a razón
 * de uno cada 5 minutos): pasado ese punto ya no vale la pena seguir
 * golpeando el portal solo, y el ejecutivo puede forzar una consulta manual
 * en cualquier momento con el botón "Consultar SOIA" del expediente.
 */
#[AsCommand(
    name: 'app:soia:poll',
    description: 'Consulta el SOIA de los expedientes en Programado que ya llevan tiempo esperando la modulación',
)]
class PollSoiaCommand extends Command
{
    private const WAIT_AFTER_DESPACHO = '+30 minutes';
    // Menor a los 5 minutos del cron a propósito: si el intervalo fuera
    // exactamente igual, una corrida que arranca unos segundos tarde (el
    // propio tiempo que tarda en correr) empuja lastSoiaCheckAt justo pasado
    // el borde de los 5 minutos, y la siguiente corrida (exactamente 5
    // minutos después) cae un poco corta y se salta — en la práctica
    // termina revisando cada ~10 minutos, no cada 5. Confirmado en
    // var/log/soia_poll.log: patrón alternado "Revisados: 1"/"Revisados: 0".
    private const RECHECK_INTERVAL = '+4 minutes';
    private const MAX_AUTO_ATTEMPTS = 100;

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

            if ($import->getSoiaPollAttempts() >= self::MAX_AUTO_ATTEMPTS) {
                continue;
            }

            $lastCheck = $import->getLastSoiaCheckAt();

            if ($lastCheck !== null && $now < $lastCheck->modify(self::RECHECK_INTERVAL)) {
                continue;
            }

            ++$checked;
            $import->incrementSoiaPollAttempts();
            $result = $this->confirmer->attemptConfirm($import);

            if ($import->getStatus() === ImportRequestWorkflow::MODULATED) {
                ++$modulated;
                $io->writeln(sprintf('[%s] Expediente %s modulado (%s).', $now->format('c'), $import->getClientReference(), $result->estado));
            } elseif ($result->isUnderInspection()) {
                $io->writeln(sprintf('[%s] Expediente %s en reconocimiento aduanero.', $now->format('c'), $import->getClientReference()));
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
