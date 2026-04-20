<?php

namespace App\Service;

use App\Entity\Evenements;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeService
{
    public function __construct(
        private readonly string $stripeSecretKey,
        private readonly string $stripePublicKey,
        private readonly string $appBaseUrl
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    public function getPublicKey(): string { return $this->stripePublicKey; }

    public function createCheckoutSession(Evenements $evenement, int $reservationId, string $successUrl, string $cancelUrl): Session
    {
        return Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => [
                        'name'        => $evenement->getTitre(),
                        'description' => sprintf('Reservation #%d — %s — %s',
                            $reservationId, $evenement->getLieu(),
                            $evenement->getDate_evenement()?->format('d/m/Y') ?? ''),
                    ],
                    'unit_amount' => max(50, (int) round(($evenement->getPrix() ?? 0) * 100)),
                ],
                'quantity' => 1,
            ]],
            'mode'        => 'payment',
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'metadata'    => [
                'reservation_id' => (string) $reservationId,
                'evenement_id'   => (string) $evenement->getId_evenement(),
            ],
        ]);
    }

    public function retrieveSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId);
    }
}
