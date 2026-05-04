<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class RhEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName
    ) {}

    public function sendEmail(string $recipient, string $subject, string $content, bool $isHtml = false): void
    {
        $email = (new Email())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->to($recipient)
            ->subject($subject);

        if ($isHtml) {
            $email->html($content);
        } else {
            $email->text($content);
        }

        $this->mailer->send($email);
    }
}
