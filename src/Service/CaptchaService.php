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
        // En dev ou avec clé de test — bypass captcha
        if ($this->appEnv === 'dev' || $this->hcaptchaSecretKey === '0x0000000000000000000000000000000000000000') {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => [
                    'secret'   => $this->hcaptchaSecretKey,
                    'response' => $token,
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray();
            return $data['success'] ?? false;

        } catch (\Throwable) {
            return !empty($token);
        }
    }
}
