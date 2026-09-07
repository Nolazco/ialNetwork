<?php

namespace App\Soia;

use App\Entity\ImportRequest;
use App\Notification\ModuladoMailer;
use App\Workflow\AduanaCatalog;
use App\Workflow\ImportRequestWorkflow;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Unico punto que decide "¿ya se puede pasar a Modulado?" y lo ejecuta.
 *
 * Lo usan tanto el boton manual ("Consultar SOIA" en el expediente) como el
 * poller automatico (PollSoiaCommand), para no duplicar la logica de avance +
 * correo en dos lados.
 */
final class ModuladoConfirmer
{
    public function __construct(
        private readonly SoiaClient $client,
        private readonly ImportRequestWorkflow $workflow,
        private readonly ModuladoMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly AduanaCatalog $aduanaCatalog,
    ) {
    }

    public function attemptConfirm(ImportRequest $import): SoiaResult
    {
        $result = $this->client->consultar((string) $import->getImportNumber(), $this->aduanaCatalog->soiaCode($import->getAduana()));
        $import->setLastSoiaCheckAt(new \DateTimeImmutable());

        // El semaforo fiscal selecciono el pedimento para revision: no es un
        // resultado final (isResolved() sigue en false), asi que el poller
        // debe seguir intentando despues. Solo se avisa la primera vez que
        // se detecta -- mientras el SOIA siga reportando lo mismo en cada
        // poll, reconocimientoAt ya no es null y no se repite el correo.
        if ($result->isUnderInspection()) {
            if ($import->getReconocimientoAt() === null) {
                $import->setReconocimientoAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->mailer->notifyReconocimiento($import);
            } else {
                $this->entityManager->flush();
            }

            return $result;
        }

        if ($result->isResolved() && $this->workflow->canTransitionTo($import, ImportRequestWorkflow::MODULATED)) {
            $import->setModuladoAt($result->fecha ?? new \DateTimeImmutable());
            $import->setStatus(ImportRequestWorkflow::MODULATED);
            $this->entityManager->flush();

            // isResolved() ya garantiza que $result->estado viene lleno.
            $this->mailer->notify($import, $result->estado);

            return $result;
        }

        $this->entityManager->flush();

        return $result;
    }
}
