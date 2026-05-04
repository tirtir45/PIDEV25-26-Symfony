<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Valide une photo de profil via le microservice Python FastAPI.
 * Utilise cURL natif pour l'envoi multipart/form-data.
 */
class PhotoValidationService
{
    private const ENDPOINT = '/validate-photo';
    private const TIMEOUT  = 20;

    public function __construct(
        private readonly string $photoApiUrl = 'http://127.0.0.1:8001',
        private readonly string $photoApiKey = 'starthub-photo-secret',
    ) {}

    /**
     * @return array{
     *   success: bool, valid: bool, code: string,
     *   message: string, faces_detected: int,
     *   confidence: float, checks: array, reason: string
     * }
     */
    public function validate(UploadedFile $file): array
    {
        try {
            $boundary    = '----FormBoundary' . bin2hex(random_bytes(8));
            $fileContent = file_get_contents($file->getPathname());
            $mimeType    = $file->getMimeType() ?? 'image/jpeg';
            $filename    = $file->getClientOriginalName() ?: 'photo.jpg';

            $body = "--{$boundary}\r\n"
                  . "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n"
                  . "Content-Type: {$mimeType}\r\n\r\n"
                  . $fileContent . "\r\n"
                  . "--{$boundary}--\r\n";

            $ch = curl_init($this->photoApiUrl . self::ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => [
                    "Content-Type: multipart/form-data; boundary={$boundary}",
                    "X-API-Key: {$this->photoApiKey}",
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response === false || $httpCode !== 200) {
                return $this->serviceUnavailable();
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                return $this->serviceUnavailable();
            }

            return [
                'success'        => (bool)   ($data['success']        ?? false),
                'valid'          => (bool)   ($data['valid']          ?? false),
                'code'           => (string) ($data['code']           ?? 'ERROR'),
                'message'        => (string) ($data['message']        ?? 'Erreur de validation.'),
                'faces_detected' => (int)    ($data['faces_detected'] ?? 0),
                'confidence'     => (float)  ($data['confidence']     ?? 0.0),
                'checks'         => (array)  ($data['checks']         ?? []),
                'reason'         => (string) ($data['reason']         ?? ''),
            ];

        } catch (\Throwable) {
            return $this->serviceUnavailable();
        }
    }

    private function serviceUnavailable(): array
    {
        return [
            'success'        => false,
            'valid'          => false,
            'code'           => 'ERROR',
            'message'        => '⚠️ Service de validation indisponible. Réessayez dans quelques instants.',
            'faces_detected' => 0,
            'confidence'     => 0.0,
            'checks'         => [],
            'reason'         => 'Microservice hors ligne',
        ];
    }
}
