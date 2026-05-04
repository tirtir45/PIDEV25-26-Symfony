<?php

namespace App\Service;

use App\Entity\Password_reset_tokens;
use App\Entity\Utilisateurs;
use App\Repository\Password_reset_tokensRepository;
use Doctrine\ORM\EntityManagerInterface;

class PasswordResetService
{
    private const OTP_TTL      = 600;
    private const MAX_REQUESTS = 5;

    public function __construct(
        private readonly EntityManagerInterface           $em,
        private readonly Password_reset_tokensRepository $tokenRepo,
        private readonly SmsService                      $sms
    ) {}

    /**
     * Génère un OTP, le stocke en DB et retourne le code en clair pour l'afficher.
     * Retourne null si trop de demandes.
     */
    public function generateOtp(Utilisateurs $user): ?string
    {
        $since    = new \DateTime('-1 hour');
        $existing = $this->tokenRepo->createQueryBuilder('t')
            ->where('t.id_utilisateur = :u')
            ->andWhere('t.date_creation >= :since')
            ->setParameter('u', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        if (count($existing) >= self::MAX_REQUESTS) {
            return null;
        }

        foreach ($existing as $old) {
            $old->setUtilise(true);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now  = new \DateTime();

        $token = new Password_reset_tokens();
        $token->setId_utilisateur($user);
        $token->setToken(password_hash($code, PASSWORD_BCRYPT));
        $token->setDate_creation($now);
        $token->setDate_expiration((clone $now)->modify('+' . self::OTP_TTL . ' seconds'));
        $token->setUtilise(false);

        $this->em->persist($token);
        $this->em->flush();

        // Send OTP via Telegram
        $this->sms->sendTelegramOtp(sprintf("🔐 Votre code de réinitialisation StartHub est : %s\n\nIl expire dans 10 minutes.", $code));
        return true;

        return false;
    }

    public function verifyOtp(Utilisateurs $user, string $inputCode): string
    {
        $token = $this->tokenRepo->createQueryBuilder('t')
            ->where('t.id_utilisateur = :u')
            ->andWhere('t.utilise = false')
            ->andWhere('t.date_expiration > :now')
            ->setParameter('u', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('t.date_creation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$token) return 'expired';

        if (!password_verify($inputCode, $token->getToken())) return 'invalid';

        $token->setUtilise(true);
        $this->em->flush();

        return 'valid';
    }

    public function getActiveToken(Utilisateurs $user): ?Password_reset_tokens
    {
        return $this->tokenRepo->createQueryBuilder('t')
            ->where('t.id_utilisateur = :u')
            ->andWhere('t.utilise = false')
            ->andWhere('t.date_expiration > :now')
            ->setParameter('u', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('t.date_creation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTtl(): int { return self::OTP_TTL; }
}
