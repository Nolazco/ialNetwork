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
 * Pensado para correr por cron cada 2 minutos, revisando como máximo
 * BATCH_SIZE expedientes por corrida: el servidor del SOIA es lento, y
 * bombardearlo con todos los expedientes elegibles de un jalón (antes podían
 * ser decenas en una sola corrida) es justo lo que lo pone más lento todavía.
 * Repartir la carga en corridas pequeñas y frecuentes evita el pico sin
 * dejar de consultar a todos con el tiempo (ver ORDER BY lastSoiaCheckAt en
 * execute(), que atiende primero a quien lleva más tiempo sin revisarse).
 *
 * Es este comando el que decide, expediente por expediente, si ya le toca
 * consultar (30 minutos después de la cita más próxima, y no antes de
 * RECHECK_INTERVAL desde la última consulta), así que no importa que el cron
 * corra más seguido que esa regla.
 *
 * Se rinde tras MAX_AUTO_ATTEMPTS intentos por expediente (aprox. 48 horas de
 * reintentos a razón de un intento cada ~2 minutos): pasado ese punto ya no
 * vale la pena seguir golpeando el portal solo, y el ejecutivo puede forzar
 * una consulta manual en cualquier momento con el botón "Consultar SOIA" del
 * expediente. Si en la práctica hay más de BATCH_SIZE expedientes elegibles
 * a la vez, el reparto por corridas puede estirar esa ventana un poco más de
 * 48 horas para los últimos en turno — es un costo aceptable a cambio de no
 * saturar el SOIA. El presupuesto de intentos se reinicia cada vez que se
 * fija o corrige la fecha/hora de un despacho (ver
 * ImportRequest::resetSoiaPolling()), para que siempre cuente desde la cita
 * vigente y no desde una que ya se corrigió.
 */
#[AsCommand(
    name: 'app:soia:poll',
    description: 'Consulta el SOIA de los expedientes en Programado que ya llevan tiempo esperando la modulación',
)]
class PollSoiaCommand extends Command
{
    private const WAIT_AFTER_DESPACHO = '+30 minutes';
    // Menor a los 2 minutos del cron a propósito: si el intervalo fuera
    // exactamente igual, una corrida que arranca unos segundos tarde (el
    // propio tiempo que tarda en correr, o un SOIA lento) empuja
    // lastSoiaCheckAt justo pasado el borde de los 2 minutos, y la siguiente
    // corrida cae un poco corta y se salta — en la práctica terminaría
    // revisando cada ~4 minutos, no cada 2 (mismo problema que ya se vio con
    // el cron de 5 minutos, ver var/log/soia_poll.log de esa época).
    private const RECHECK_INTERVAL = '+100 seconds';
    private const MAX_AUTO_ATTEMPTS = 1440;

    /** Cuántos expedientes se consultan como máximo en una sola corrida, para no bombardear el SOIA de un jalón. */
    private const BATCH_SIZE = 10;

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

        // lastSoiaCheckAt ASC deja primero a quien nunca se ha revisado (NULL
        // ordena antes que cualquier fecha) y luego a quien lleva más tiempo
        // esperando su turno, para que el tope de BATCH_SIZE por corrida
        // reparta parejo si hay más expedientes elegibles que cupo.
        $candidates = $this->entityManager->getRepository(ImportRequest::class)
            ->findBy(['status' => ImportRequestWorkflow::SCHEDULED], ['lastSoiaCheckAt' => 'ASC']);

        $checked = 0;
        $modulated = 0;

        foreach ($candidates as $import) {
            if ($checked >= self::BATCH_SIZE) {
                break;
            }

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
            // throttledWhatsApp=true: corre en background, asi que no hay
            // problema en que las pausas entre destinatarios alarguen la
            // corrida (ver WhatsAppSender::send()).
            $result = $this->confirmer->attemptConfirm($import, throttledWhatsApp: true);

            if ($import->getStatus() === ImportRequestWorkflow::MODULATED) {
                ++$modulated;
                $io->writeln(sprintf('[%s] Expediente %s modulado (%s).', $now->format('c'), $import->getClientReference(), $result->estado));
            } elseif ($result->isUnderInspection()) {
                $io->writeln(sprintf('[%s] Expediente %s en reconocimiento aduanero.', $now->format('c'), $import->getClientReference()));
            }

            if ($checked < self::BATCH_SIZE) {
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
