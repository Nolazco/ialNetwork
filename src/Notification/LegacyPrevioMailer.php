<?php

namespace App\Notification;

use App\Entity\LegacyPrevioReport;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Mismas direcciones fijas que PrevioReportMailer, pero sin expediente real
 * detrás: el reporte llegó por el puente público /legacy/reportes.
 */
final class LegacyPrevioMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
    ) {
    }

    public function notify(LegacyPrevioReport $report): void
    {
        $to = $this->dedupe(array_merge(
            [$report->getCorreo()],
            PrevioReportMailer::FIXED_TO,
        ));

        $cc = $this->dedupe(array_merge(
            $this->recipients->executiveEmails(),
            PrevioReportMailer::FIXED_CC,
        ));

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Reporte de previo %s', $report->getReferencia()))
            ->htmlTemplate('emails/legacyPrevio.html.twig')
            ->context(['report' => $report])
            ->to(...$to);

        if ($cc !== []) {
            $email->cc(...$cc);
        }

        if ($report->getPdfRoute() && is_file($report->getPdfRoute())) {
            $email->attachFromPath(
                $report->getPdfRoute(),
                sprintf('reporte-previo-%s.pdf', $report->getReferencia())
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
