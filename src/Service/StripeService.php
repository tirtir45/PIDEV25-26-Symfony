<?php
namespace App\Service;
use App\Entity\Evenement;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeService
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $publicKey,
        private readonly string $baseUrl
    ) { Stripe::setApiKey($this->secretKey); }

    public function getPublicKey(): string { return $this->publicKey; }

    public function createCheckoutSession(Evenement $evenement, int $reservationId, string $successUrl, string $cancelUrl): Session
    {
        return Session::create([
            "payment_method_types" => ["card"],
            "line_items" => [[
                "price_data" => [
                    "currency"     => "eur",
                    "product_data" => [
                        "name"        => $evenement->getTitre(),
                        "description" => sprintf("Reservation #%d — %s — %s",
                            $reservationId, $evenement->getLieu(),
                            $evenement->getDateEvenement()?->format("d/m/Y") ?? ""),
                    ],
                    // Convert TND → EUR (1 DT ≈ 0.292 EUR), Stripe wants cents
                    "unit_amount" => (int) round($evenement->getPrix() * 0.292 * 100),
                ],
                "quantity" => 1,
            ]],
            "mode"        => "payment",
            "success_url" => $successUrl,
            "cancel_url"  => $cancelUrl,
            "metadata"    => [
                "reservation_id" => (string)$reservationId,
                "evenement_id"   => (string)$evenement->getIdEvenement(),
            ],
        ]);
    }

    public function retrieveSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId);
    }
}
