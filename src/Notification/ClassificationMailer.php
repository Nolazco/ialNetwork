<?php

namespace App\Notification;

use App\Entity\ClassificationRequest;
use App\Service\UploadPath;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Manda la solicitud al equipo de clasificadores: contestan desde su propio
 * correo, la app no hace seguimiento de la respuesta.
 */
final class ClassificationMailer
{
    /**
     * Equipo fijo que clasifica. Heredado del sistema anterior
     * (clas.php/clasMateria.php); no corresponde a ningún ejecutivo o cliente
     * de esta app, así que no se resuelve de la base de datos.
     *
     * Público: LegacyClassificationMailer lo reutiliza para no duplicar la lista.
     *
     * @var list<string>
     */
    public const CLASSIFIERS = [
        'maria.santiago@vca.mx',
        'mcamacho@valxglobalservices.com',
        'ing.bueno@ialnetwork.com',
        'zyf1967_2025@outlook.com',
    ];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RecipientResolver $recipients,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        private readonly UploadPath $uploadPath,
    ) {
    }

    public function notify(ClassificationRequest $request): void
    {
        $cc = $this->dedupe(array_merge(
            [$request->getRequestedBy()->getEmail()],
            $this->recipients->executiveEmails(),
            array_filter([$request->getCompany()->getClassificationContactEmail()]),
        ));

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject(sprintf('Solicitud de Clasificación de Mercancía // %s // %s', $request->getMerchandiseName(), $request->getCompany()->getName()))
            ->htmlTemplate('emails/classification.html.twig')
            ->context(['request' => $request])
            ->to(...self::CLASSIFIERS)
            ->cc(...$cc);

        foreach ($request->getAttachments() as $attachment) {
            $path = $this->uploadPath->resolve($attachment['ruta']);

            if (is_file($path)) {
                $email->attachFromPath($path, $attachment['nombre']);
            }
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
