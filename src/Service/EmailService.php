<?php
// src/Service/EmailService.php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Service\Attribute\Required;

class EmailService
{
    private MailerInterface $mailer;
    private string $senderEmail;
    private string $senderName;

    // Le constructeur reçoit le Mailer de Symfony et les variables d'environnement
    public function __construct(
        MailerInterface $mailer,
        string $senderEmail,
        string $senderName
    ) {
        $this->mailer = $mailer;
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
    }
    
    /**
     * Envoie un email simple.
     *
     * @param string $recipient L'adresse email du destinataire.
     * @param string $subject Le sujet de l'email.
     * @param string $content Le contenu de l'email (peut être du texte simple ou du HTML).
     * @param bool $isHtml Indique si le contenu est du HTML.
     */
    public function sendEmail(string $recipient, string $subject, string $content, bool $isHtml = false): void
    {
        // 1. Création de l'objet Email
        $email = (new Email())
            ->from(new \Symfony\Component\Mime\Address($this->senderEmail, $this->senderName))
            ->to($recipient)
            ->subject($subject);

        // 2. Ajout du contenu (texte ou HTML)
        if ($isHtml) {
            $email->html($content);
        } else {
            $email->text($content);
        }

        // 3. Envoi de l'email via le service Mailer de Symfony
        try {
            $this->mailer->send($email);
            // Optionnel : logger le succès
            // echo "✅ Email envoyé avec succès via Brevo à : " . $recipient;
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            // Optionnel : logger l'erreur
            // echo "❌ Erreur d'envoi Brevo : " . $e->getMessage();
            
            // Il est préférable de laisser l'exception se propager ou de la logger
            // pour un traitement d'erreur plus robuste.
            throw $e;
        }
    }
}