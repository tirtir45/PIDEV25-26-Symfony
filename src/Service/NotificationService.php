<?php

namespace App\Service;

use App\Entity\Notifications;
use App\Entity\Utilisateurs;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function notifier(Utilisateurs $utilisateur, string $type, string $message, ?string $lien = null): void
    {
        $notif = new Notifications();
        $notif->setUtilisateur($utilisateur);
        $notif->setType($type);
        $notif->setMessage($message);
        $notif->setLien($lien);

        $this->em->persist($notif);
        $this->em->flush();
    }

    public function countNonLues(Utilisateurs $utilisateur): int
    {
        return $this->em->getRepository(Notifications::class)->countNonLues($utilisateur);
    }

    public function marquerToutesLues(Utilisateurs $utilisateur): void
    {
        $this->em->getRepository(Notifications::class)->marquerToutesLues($utilisateur);
    }
}
