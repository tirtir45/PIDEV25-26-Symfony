<?php

namespace App\Service;

use App\Entity\Ressources;
use App\Entity\Utilisateurs;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmsService
{
    private ?string $accountSid;
    private ?string $authToken;
    private ?string $fromNumber;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $params,
        private readonly LoggerInterface $logger
    ) {
        $this->accountSid = $this->params->has('twilio_account_sid') ? $this->params->get('twilio_account_sid') : null;
        $this->authToken = $this->params->has('twilio_auth_token') ? $this->params->get('twilio_auth_token') : null;
        $this->fromNumber = $this->params->has('twilio_from_number') ? (string)$this->params->get('twilio_from_number') : null;

        // Ensure fromNumber has '+' prefix if it looks like a phone number but lacks it
        if ($this->fromNumber && !str_starts_with($this->fromNumber, '+') && is_numeric(str_replace(' ', '', $this->fromNumber))) {
            $this->fromNumber = '+' . $this->fromNumber;
        }
    }

    /**
     * Sends a low stock alert SMS to the supplier.
     */
    public function sendLowStockAlert(Ressources $ressource): bool
    {
        $supplier = $ressource->getIdFournisseur();
        if (!$supplier) {
            $this->logger->warning(sprintf('No supplier found for ressource "%s" (ID: %d)', $ressource->getNom(), $ressource->getId()));
            return false;
        }

        $phoneNumber = $this->formatTunisianNumber($supplier->getTelephone());
        if (!$phoneNumber) {
            $this->logger->warning(sprintf('No phone number found for supplier "%s" (ID: %d)', $supplier->getNom(), $supplier->getId()));
            return false;
        }

        $messageBody = sprintf(
            "Alerte Stock Bas ! Votre ressource '%s' n'a plus que %d unités en stock. Pensez à réapprovisionner. - StartupHub",
            $ressource->getNom(),
            $ressource->getQuantite()
        );

        return $this->sendSms($phoneNumber, $messageBody);
    }

    /**
     * Sends a moderation alert SMS when a resource is banned.
     * Returns an array with ['success' => bool, 'message' => string]
     */
    public function sendModerationAlert(Ressources $ressource): array
    {
        $supplier = $ressource->getIdFournisseur();
        if (!$supplier) {
            return ['success' => false, 'message' => 'Aucun fournisseur associé à cette ressource.'];
        }

        $phoneNumber = $this->formatTunisianNumber($supplier->getTelephone());
        if (!$phoneNumber) {
            return ['success' => false, 'message' => "Le fournisseur n'a pas de numéro de téléphone enregistré."];
        }

        $messageBody = sprintf(
            "ALERTE MODÉRATION : Votre ressource '%s' a été bannie par la sécurité de StartupHub car elle a été jugée non conforme ou dangereuse.",
            $ressource->getNom()
        );

        $success = $this->sendTestSms($phoneNumber, $messageBody);
        
        return [
            'success' => $success,
            'message' => $success ? 'SMS envoyé avec succès.' : 'Erreur Twilio lors de l\'envoi de l\'SMS.'
        ];
    }

    /**
     * Sends a generic test SMS.
     */
    public function sendTestSms(string $to, string $message): bool
    {
        // Ensure recipient number has '+' prefix
        if (!str_starts_with($to, '+')) {
            $to = '+' . $to;
        }
        return $this->sendSms($to, $message);
    }

    /**
     * Internal method to send SMS via Twilio API.
     */
    private function sendSms(string $to, string $message): bool
    {
        // Check if we have credentials; if not, log the message (Simulated mode)
        if (!$this->accountSid || !$this->authToken || !$this->fromNumber || $this->accountSid === 'your_sid') {
            $this->logger->info(sprintf('[SMS SIMULATION] To: %s | Message: %s', $to, $message));
            return true;
        }

        try {
            $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $this->accountSid);

            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => [
                    'To' => $to,
                    'From' => $this->fromNumber,
                    'Body' => $message,
                ],
            ]);

            if ($response->getStatusCode() === 201) {
                $this->logger->info(sprintf('SMS sent successfully to %s', $to));
                return true;
            }

            $this->logger->error(sprintf('Failed to send SMS to %s. Status code: %d. Response: %s', $to, $response->getStatusCode(), $response->getContent(false)));
            return false;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Exception while sending SMS to %s: %s', $to, $e->getMessage()));
            return false;
        }
    }

    /**
     * Internal method to format Tunisian numbers (adding +216 if missing).
     */
    private function formatTunisianNumber(?string $phoneNumber): ?string
    {
        if (!$phoneNumber) {
            return null;
        }

        $cleanPhone = str_replace([' ', '-', '.', '(', ')'], '', $phoneNumber);
        if (strlen($cleanPhone) === 8 && is_numeric($cleanPhone)) {
            $this->logger->info("Auto-formatted Tunisian number: +216" . $cleanPhone);
            return '+216' . $cleanPhone;
        }

        return $phoneNumber;
    }
}
