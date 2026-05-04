<?php

namespace App\Service;

use App\Entity\Ressources;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmsService
{
    private ?string $accountSid;
    private ?string $authToken;
    private ?string $fromNumber;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $params,
        private readonly LoggerInterface $logger,
        private readonly TexterInterface $texter, // This is the "Bundle" sender for Vonage
        private readonly ChatterInterface $chatter // Telegram Bundle
    ) {
        // Load manual Twilio config
        $this->accountSid = $this->params->has('twilio_account_sid') ? $this->params->get('twilio_account_sid') : null;
        $this->authToken  = $this->params->has('twilio_auth_token') ? $this->params->get('twilio_auth_token') : null;
        $this->fromNumber = $this->params->has('twilio_from_number') ? (string) $this->params->get('twilio_from_number') : null;

        if ($this->fromNumber && !str_starts_with($this->fromNumber, '+') && is_numeric(str_replace(' ', '', $this->fromNumber))) {
            $this->fromNumber = '+' . $this->fromNumber;
        }
    }

    /* ─── PROVIDER 1: TWILIO (MANUAL API) ─── */
    
    public function sendLowStockAlert(Ressources $ressource): bool
    {
        $supplier = $ressource->getIdFournisseur();
        if (!$supplier) return false;

        $phoneNumber = $this->formatTunisianNumber($supplier->getTelephone());
        if (!$phoneNumber) return false;

        return $this->sendManualTwilioSms($phoneNumber, sprintf(
            "Alerte Stock Bas ! Votre ressource '%s' n'a plus que %d unités. - StartupHub",
            $ressource->getNom(), $ressource->getQuantite()
        ));
    }

    public function sendModerationAlert(Ressources $ressource): array
    {
        $supplier = $ressource->getIdFournisseur();
        if (!$supplier) return ['success' => false, 'message' => 'Aucun fournisseur associé.'];

        $phoneNumber = $this->formatTunisianNumber($supplier->getTelephone());
        if (!$phoneNumber) return ['success' => false, 'message' => "Pas de numéro enregistré."];

        $success = $this->sendManualTwilioSms($phoneNumber, sprintf(
            "ALERTE MODÉRATION : Votre ressource '%s' a été bannie par StartupHub.",
            $ressource->getNom()
        ));

        return ['success' => $success, 'message' => $success ? 'SMS envoyé via Twilio API.' : 'Erreur envoi Twilio API.'];
    }

    private function sendManualTwilioSms(string $to, string $message): bool
    {
        if (!$this->accountSid || !$this->authToken || !$this->fromNumber) {
            $this->logger->info(sprintf('[Manual Twilio Simulation] To: %s | Message: %s', $to, $message));
            return true;
        }

        try {
            $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $this->accountSid);
            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body'       => ['To' => $to, 'From' => $this->fromNumber, 'Body' => $message],
            ]);

            return $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            $this->logger->error('Twilio Manual API Error: ' . $e->getMessage());
            return false;
        }
    }

    /* ─── PROVIDER 2: VONAGE (BUNDLE) ─── */

    /**
     * Specifically for Password Reset using the Symfony Notifier Bundle
     */
    public function sendVonageSms(string $to, string $message): bool
    {
        if (!str_starts_with($to, '+')) $to = '+' . $to;

        try {
            $sms = new SmsMessage($to, $message);
            $sms->transport('vonage'); // Force the Bundle to use Vonage transport
            $this->texter->send($sms);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Vonage Bundle Error: ' . $e->getMessage());
            return false;
        }
    }

    /* ─── PROVIDER 3: TELEGRAM (BUNDLE) ─── */
    
    public function sendTelegramOtp(string $messageContent): bool
    {
        try {
            $chatMessage = new ChatMessage($messageContent);
            $chatMessage->transport('telegram');
            $this->chatter->send($chatMessage);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Telegram Bundle Error: ' . $e->getMessage());
            return false;
        }
    }

    private function formatTunisianNumber(?string $phoneNumber): ?string
    {
        if (!$phoneNumber) return null;
        $clean = str_replace([' ', '-', '.', '(', ')'], '', $phoneNumber);
        if (strlen($clean) === 8 && is_numeric($clean)) return '+216' . $clean;
        return $phoneNumber;
    }
}
