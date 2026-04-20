<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class EmailService
{
    private const FROM_EMAIL = 'batatamokliya2019@gmail.com';
    private const FROM_NAME = 'StartupHub Team';

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    /**
     * Sends an email with optional PDF attachments.
     */
    public function sendOrderConfirmation(string $toEmail, string $userName, array $attachments = []): void
    {
        $email = (new Email())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($toEmail)
            ->subject('Confirmation de votre commande - StartupHub')
            ->html(sprintf(
                '<h1>Bonjour %s,</h1><p>Merci pour votre commande sur StartupHub. Vous trouverez ci-joint votre reçu et vos contrats correspondants.</p><p>Cordialement,<br>L\'équipe StartupHub</p>',
                htmlspecialchars($userName)
            ));

        foreach ($attachments as $fileName => $content) {
            $email->attach($content, $fileName, 'application/pdf');
        }

        $this->mailer->send($email);
    }
}
