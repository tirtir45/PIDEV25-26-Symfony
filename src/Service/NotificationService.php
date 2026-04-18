<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reclamations;
use App\Entity\Utilisateurs;
use App\Repository\UtilisateursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface  $em,
        private readonly MailerInterface         $mailer,
        private readonly UrlGeneratorInterface   $router,
        private readonly UtilisateursRepository  $userRepo,
        private readonly string                  $mailerFrom = 'noreply@starthub.com',
        private readonly string                  $mailerToOverride = ''
    ) {}

    /* ── Nouvelle réclamation ── */
    public function onNewReclamation(Reclamations $r): void
    {
        $user = $r->getUtilisateur();

        // Notif en base pour l'utilisateur
        $this->save($user, 'info',
            'Réclamation enregistrée',
            "Votre réclamation \"{$r->getSujet()}\" a bien été reçue. Notre équipe vous répondra rapidement.",
            $this->router->generate('reclamation_show', ['id' => $r->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
        );

        // Email à l'utilisateur
        $this->sendMail(
            $user->getEmail(),
            '✅ Réclamation enregistrée — StartHub',
            $this->templateConfirmation($r)
        );

        // Notif en base pour tous les admins
        foreach ($this->getAdmins() as $admin) {
            $this->save($admin, 'warning',
                'Nouvelle réclamation',
                "L'utilisateur {$user->getNom()} a soumis une réclamation : \"{$r->getSujet()}\".",
                $this->router->generate('admin_reclamation_show', ['id' => $r->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            );
        }
    }

    /* ── Réponse admin ── */
    public function onAdminReply(Reclamations $r, string $messageContent): void
    {
        $user = $r->getUtilisateur();

        $this->save($user, 'success',
            'Nouvelle réponse à votre réclamation',
            "L'équipe StartHub a répondu à votre réclamation \"{$r->getSujet()}\".",
            $this->router->generate('reclamation_show', ['id' => $r->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
        );

        $this->sendMail(
            $user->getEmail(),
            '💬 Nouvelle réponse — StartHub',
            $this->templateReply($r, $messageContent)
        );
    }

    /* ── Changement de statut ── */
    public function onStatusChange(Reclamations $r, string $oldStatut): void
    {
        $user = $r->getUtilisateur();
        $newStatut = $r->getStatut();

        $labels = [
            'EN_ATTENTE' => 'En attente',
            'EN_COURS'   => 'En cours de traitement',
            'RESOLU'     => 'Résolue ✅',
            'REJETE'     => 'Rejetée ❌',
        ];

        $types = [
            'EN_ATTENTE' => 'info',
            'EN_COURS'   => 'info',
            'RESOLU'     => 'success',
            'REJETE'     => 'error',
        ];

        $label = $labels[$newStatut] ?? $newStatut;
        $type  = $types[$newStatut] ?? 'info';

        $this->save($user, $type,
            'Statut mis à jour',
            "Votre réclamation \"{$r->getSujet()}\" est maintenant : {$label}.",
            $this->router->generate('reclamation_show', ['id' => $r->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
        );

        $this->sendMail(
            $user->getEmail(),
            "🔔 Statut mis à jour — {$label} — StartHub",
            $this->templateStatusChange($r, $label)
        );
    }

    /* ── Compte activé/désactivé ── */
    public function sendAccountStatusEmail(Utilisateurs $user, bool $isActive): void
    {
        $label = $isActive ? 'réactivé ✅' : 'suspendu ⚠️';
        $color = $isActive ? '#16a34a' : '#dc2626';
        $msg   = $isActive
            ? 'Votre compte a été réactivé par l\'administrateur. Vous pouvez à nouveau vous connecter à StartHub.'
            : 'Votre compte a été suspendu par l\'administrateur. Si vous pensez qu\'il s\'agit d\'une erreur, contactez le support.';

        $this->sendMail(
            $user->getEmail(),
            "Compte {$label} — StartHub",
            $this->emailLayout(
                "Compte {$label}",
                "Bonjour <strong>{$user->getNom()}</strong>,",
                $msg,
                'Accéder à StartHub',
                'http://127.0.0.1:8000',
                $color
            )
        );
    }

    /* ── Compte supprimé ── */
    public function sendAccountDeletedEmail(Utilisateurs $user): void
    {
        $this->sendMail(
            $user->getEmail(),
            'Compte supprimé — StartHub',
            $this->emailLayout(
                'Compte supprimé',
                "Bonjour <strong>{$user->getNom()}</strong>,",
                'Votre compte StartHub a été supprimé par l\'administrateur. Toutes vos données ont été effacées. Si vous pensez qu\'il s\'agit d\'une erreur, contactez-nous.',
                'Contacter le support',
                'http://127.0.0.1:8000',
                '#dc2626'
            )
        );
    }

    /* ── Compter les non lues ── */
    public function countUnread(Utilisateurs $user): int
    {
        return $this->em->getRepository(Notification::class)
            ->count(['utilisateur' => $user, 'lu' => false]);
    }

    /* ── Récupérer les notifications ── */
    public function getForUser(Utilisateurs $user, int $limit = 10): array
    {
        return $this->em->getRepository(Notification::class)
            ->findBy(['utilisateur' => $user], ['createdAt' => 'DESC'], $limit);
    }

    /* ── Marquer comme lues ── */
    public function markAllRead(Utilisateurs $user): void
    {
        $notifs = $this->em->getRepository(Notification::class)
            ->findBy(['utilisateur' => $user, 'lu' => false]);
        foreach ($notifs as $n) {
            $n->setLu(true);
        }
        $this->em->flush();
    }

    /* ── Helpers privés ── */
    private function save(Utilisateurs $user, string $type, string $titre, string $message, ?string $lien = null): void
    {
        $n = new Notification();
        $n->setUtilisateur($user);
        $n->setType($type);
        $n->setTitre($titre);
        $n->setMessage($message);
        $n->setLien($lien);
        $this->em->persist($n);
        $this->em->flush();
    }

    private function sendMail(string $to, string $subject, string $html): void
    {
        // En dev, rediriger vers l'adresse de test si définie
        $recipient = !empty($this->mailerToOverride) ? $this->mailerToOverride : $to;

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($recipient)
            ->subject($subject)
            ->html($html);
        $this->mailer->send($email);
    }

    private function getAdmins(): array
    {
        return $this->userRepo->createQueryBuilder('u')
            ->join('u.role', 'r')
            ->where('r.nomRole = :role')
            ->setParameter('role', 'Administrateur')
            ->getQuery()->getResult();
    }

    /* ── Templates email ── */
    private function templateConfirmation(Reclamations $r): string
    {
        return $this->emailLayout(
            'Réclamation enregistrée',
            "Bonjour <strong>{$r->getUtilisateur()->getNom()}</strong>,",
            "Votre réclamation <strong>\"{$r->getSujet()}\"</strong> a bien été enregistrée. Notre équipe l'examinera dans les plus brefs délais.",
            "Voir ma réclamation",
            $this->router->generate('reclamation_show', ['id' => $r->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            '#7C5CFC'
        );
    }

    private function templateReply(Reclamations $r, string $msg): string
    {
        return $this->emailLayout(
            'Nouvelle réponse',
            "Bonjour <strong>{$r->getUtilisateur()->getNom()}</strong>,",
            "L'équipe StartHub a répondu à votre réclamation <strong>\"{$r->getSujet()}\"</strong> :<br><br><em style='color:#374151;'>\"" . htmlspecialchars($msg) . "\"</em>",
            "Voir la discussion",
            $this->router->generate('reclamation_show', ['id' => $r->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            '#7C5CFC'
        );
    }

    private function templateStatusChange(Reclamations $r, string $label): string
    {
        $colors = ['Résolue ✅' => '#16a34a', 'Rejetée ❌' => '#dc2626'];
        $color  = $colors[$label] ?? '#7C5CFC';

        return $this->emailLayout(
            'Statut mis à jour',
            "Bonjour <strong>{$r->getUtilisateur()->getNom()}</strong>,",
            "Le statut de votre réclamation <strong>\"{$r->getSujet()}\"</strong> a été mis à jour : <span style='color:{$color};font-weight:700;'>{$label}</span>.",
            "Voir ma réclamation",
            $this->router->generate('reclamation_show', ['id' => $r->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            $color
        );
    }

    private function emailLayout(string $title, string $greeting, string $body, string $btnText, string $btnUrl, string $color): string
    {
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:40px 20px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
  <tr><td style="background:{$color};padding:28px 32px;">
    <h1 style="margin:0;color:#fff;font-size:22px;font-weight:700;font-family:Arial,sans-serif;">StartHub</h1>
    <p style="margin:6px 0 0;color:rgba(255,255,255,.9);font-size:14px;">{$title}</p>
  </td></tr>
  <tr><td style="padding:32px;background:#fff;">
    <p style="margin:0 0 16px;font-size:15px;color:#1a1a1a;font-family:Arial,sans-serif;">{$greeting}</p>
    <p style="margin:0 0 28px;font-size:14px;color:#444;line-height:1.7;font-family:Arial,sans-serif;">{$body}</p>
    <a href="{$btnUrl}" style="display:inline-block;background:{$color};color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:700;font-family:Arial,sans-serif;">{$btnText}</a>
  </td></tr>
  <tr><td style="padding:16px 32px;border-top:1px solid #eee;text-align:center;background:#fff;">
    <p style="margin:0;font-size:12px;color:#aaa;font-family:Arial,sans-serif;">© 2026 StartHub · Tous droits réservés</p>
  </td></tr>
</table></td></tr></table>
</body></html>
HTML;
    }
}
