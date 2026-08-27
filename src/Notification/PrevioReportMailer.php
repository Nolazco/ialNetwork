<?php

namespace App\Notification;

use App\Entity\ImportRequest;
use App\Entity\PrevioReport;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Manda el reporte de previo generado: al cliente real afiliado a la empresa
 * y a los ejecutivos (igual que ModuladoMailer), más un puñado de
 * direcciones fijas heredadas del sistema anterior (previos.html/prev.php)
 * que se decidió conservar explícitamente en vez de reemplazar por completo
 * con la resolución dinámica.
 */
final class PrevioReportMailer
{
    /**
     * Direcciones fijas del sistema anterior. No se resuelven de la base de
     * datos porque no corresponden a ningún cliente o ejecutivo de esta app.
     *
     * Públicas: LegacyPrevioMailer las reutiliza para no duplicar las listas.
     *
     * @var list<string>
     */
    public const FIXED_TO = ['maria.santiago@vca.mx', 'mcamacho@valxglobalservices.com'];

    /**
     * @var list<string>
     */
    public const FIXED_CC = ['carlos.nolazco@vca.mx', 'adair.fernandez@vca.mx', 'aux.trafico2@vca.mx'];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolveTo(ImportRequest $import): array
    {
        return $this->dedupe(array_merge(
            $this->recipients->clientEmails($import),
            self::FIXED_TO,
        ));
    }

    /**
     * @return list<string>
     */
    public function resolveCc(): array
    {
        return $this->dedupe(array_merge(
            $this->recipients->executiveEmails(),
            self::FIXED_CC,
        ));
    }

    public function notify(PrevioReport $previo): void
    {
        $import = $previo->getReference();
        $to = $this->resolveTo($import);
        $cc = $this->resolveCc();

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Reporte de previo %s', $import->getClientReference()))
            ->htmlTemplate('emails/previo.html.twig')
            ->context(['import' => $import, 'previo' => $previo])
            ->to(...$to);

        if ($cc !== []) {
            $email->cc(...$cc);
        }

        if ($previo->getPdfRoute() && is_file($previo->getPdfRoute())) {
            $email->attachFromPath(
                $previo->getPdfRoute(),
                sprintf('reporte-previo-%s.pdf', $import->getClientReference())
            );
        }

        $this->mailer->send($email);
    }

    /**
     * @param list<string> $emails
     *
     * @return list<string>
     */
    private function dedupe(array $emails): array
    {
        return array_keys(array_flip($emails));
    }
}
