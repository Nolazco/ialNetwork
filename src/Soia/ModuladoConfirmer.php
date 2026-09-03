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

        if ($result->isResolved() && $this->workflow->canTransitionTo($import, ImportRequestWorkflow::MODULATED)) {
            $import->setModuladoAt($result->fecha ?? new \DateTimeImmutable());
            $import->setStatus(ImportRequestWorkflow::MODULATED);
            $this->entityManager->flush();

            $this->mailer->notify($import);

            return $result;
        }

        $this->entityManager->flush();

        return $result;
    }
}
