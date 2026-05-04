<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'otp_codes')]
class OtpCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $telephone;

    #[ORM\Column(length: 6)]
    private string $code;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $used = false;

    public function __construct(string $telephone, string $code, int $minutesValid = 10)
    {
        $this->telephone = $telephone;
        $this->code      = $code;
        $this->createdAt = new \DateTime();
        $this->expiresAt = new \DateTime("+{$minutesValid} minutes");
    }

    public function getId(): ?int { return $this->id; }
    public function getTelephone(): string { return $this->telephone; }
    public function getCode(): string { return $this->code; }
    public function getExpiresAt(): \DateTimeInterface { return $this->expiresAt; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getAttempts(): int { return $this->attempts; }
    public function incrementAttempts(): void { $this->attempts++; }
    public function isUsed(): bool { return $this->used; }
    public function setUsed(bool $u): void { $this->used = $u; }

    public function isExpired(): bool
    {
        return new \DateTime() > $this->expiresAt;
    }

    public function isValid(string $code): bool
    {
        return !$this->used && !$this->isExpired() && $this->code === $code && $this->attempts < 5;
    }
}
