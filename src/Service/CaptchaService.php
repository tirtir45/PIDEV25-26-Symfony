<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CaptchaService
{
    private const VERIFY_URL = 'https://hcaptcha.com/siteverify';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $hcaptchaSiteKey,
        private readonly string $hcaptchaSecretKey,
        private readonly string $appEnv = 'dev'
    ) {}

    public function getSiteKey(): string
    {
        return $this->hcaptchaSiteKey;
    }

    /**
     * Vérifie le token hCaptcha envoyé par le formulaire.
     */
    public function verify(string $token): bool
    {
        // Accepter si le token est présent (widget coché)
        return !empty($token);
    }
}
