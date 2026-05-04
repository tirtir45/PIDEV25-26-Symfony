<?php

namespace App\Service;

use App\Entity\Reservations;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class ReservationMailerService
{
    public function __construct(
        private readonly MailerInterface   $mailer,
        private readonly FacturePdfService $facturePdf,
        private readonly string            $mailerFromEmail,
        private readonly string            $mailerFromName
    ) {}

    public function sendConfirmation(Reservations $reservation): void
    {
        $evenement   = $reservation->getEvenement();
        $utilisateur = $reservation->getUtilisateur();
        if (!$evenement || !$utilisateur) return;

        $pdfBytes  = $this->facturePdf->generatePdf($reservation);
        $filename  = sprintf('billet_starthub_%d.pdf', $reservation->getIdReservation());
        $prixLabel = $evenement->getPrix() > 0
            ? number_format($evenement->getPrix(), 2) . ' DT'
            : 'Gratuit';

        $body = "<div style='font-family:Arial,sans-serif;max-width:580px;margin:0 auto;'>"
            . "<div style='background:linear-gradient(135deg,#6a0dad,#a155b9);padding:28px 32px;border-radius:12px 12px 0 0;'>"
            . "<h1 style='color:white;margin:0;font-size:22px;'>StartHub</h1>"
            . "<p style='color:rgba(255,255,255,.8);margin:4px 0 0;font-size:13px;'>Confirmation de réservation</p></div>"
            . "<div style='background:#fff;padding:28px 32px;border:1px solid #ede9fe;border-radius:0 0 12px 12px;'>"
            . "<p style='font-size:15px;'>Bonjour <strong>" . htmlspecialchars($utilisateur->getNom() ?? $utilisateur->getEmail()) . "</strong>,</p>"
            . "<p>Votre réservation pour <strong>" . htmlspecialchars($evenement->getTitre()) . "</strong> est confirmée !</p>"
            . "<table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;'>"
            . "<tr style='background:#f9f5ff;'><td style='padding:9px 14px;color:#6a0dad;font-weight:bold;'>Événement</td><td style='padding:9px 14px;'>" . htmlspecialchars($evenement->getTitre()) . "</td></tr>"
            . "<tr><td style='padding:9px 14px;color:#6a0dad;font-weight:bold;'>Date</td><td style='padding:9px 14px;'>" . ($evenement->getDate_evenement()?->format('d/m/Y') ?? '—') . "</td></tr>"
            . "<tr style='background:#f9f5ff;'><td style='padding:9px 14px;color:#6a0dad;font-weight:bold;'>Lieu</td><td style='padding:9px 14px;'>" . htmlspecialchars($evenement->getLieu()) . "</td></tr>"
            . "<tr><td style='padding:9px 14px;color:#6a0dad;font-weight:bold;'>Prix</td><td style='padding:9px 14px;font-weight:bold;'>" . $prixLabel . "</td></tr>"
            . "<tr style='background:#f9f5ff;'><td style='padding:9px 14px;color:#6a0dad;font-weight:bold;'>Réf.</td><td style='padding:9px 14px;'>#" . $reservation->getIdReservation() . "</td></tr></table>"
            . "<p style='background:#fdf4ff;border:1px dashed #c084fc;border-radius:8px;padding:12px;font-size:12px;color:#7c3aed;text-align:center;'>"
            . "Votre billet PDF avec QR code est en pièce jointe.<br>Présentez-le à l'entrée de l'événement.</p>"
            . "<p style='margin-top:16px;font-size:11px;color:#9ca3af;text-align:center;'>StartHub — Plateforme Entrepreneuriale</p></div></div>";

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->mailerFromName, $this->mailerFromEmail))
            ->to($utilisateur->getEmail())
            ->subject(sprintf('[StartHub] Confirmation — %s', $evenement->getTitre()))
            ->html($body)
            ->addPart(new DataPart($pdfBytes, $filename, 'application/pdf'));

        $this->mailer->send($email);
    }
}
