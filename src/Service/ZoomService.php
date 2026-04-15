<?php
// src/Service/ZoomService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\ClientException;

class ZoomService
{
    private HttpClientInterface $client;
    private string $zoomAccountId;
    private string $zoomClientId;
    private string $zoomClientSecret;
    private ?string $accessToken = null; // Pour mettre en cache le token

    public function __construct(
        HttpClientInterface $client,
        string $zoomAccountId,
        string $zoomClientId,
        string $zoomClientSecret
    ) {
        $this->client = $client;
        $this->zoomAccountId = $zoomAccountId;
        $this->zoomClientId = $zoomClientId;
        $this->zoomClientSecret = $zoomClientSecret;
    }

    /**
     * Etape 1 : Obtenir un Token d'accès (Valable 1 heure)
     * Utilise OAuth 2.0 Server-to-Server.
     *
     * @return string Le token d'accès
     * @throws \Exception Si l'authentification échoue
     */
    private function getAccessToken(): string
    {
        // Si on a déjà un token valide pour cette instance, on le réutilise
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $authString = $this->zoomClientId . ':' . $this->zoomClientSecret;
        $encodedAuth = base64_encode($authString);
        
        $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . $this->zoomAccountId;

        $response = $this->client->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Basic ' . $encodedAuth,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Erreur lors de l\'authentification Zoom (Code ' . $response->getStatusCode() . '): ' . $response->getContent(false));
        }
        
        $data = $response->toArray(); // Décode automatiquement le JSON
        $this->accessToken = $data['access_token'];

        return $this->accessToken;
    }

    /**
     * Etape 2 : Créer la réunion
     *
     * @param string $topic Le sujet de la réunion (ex: "Entretien avec Jean")
     * @param string $startTime La date de début au format ISO (ex: "2023-12-01T14:00:00")
     * @return string L'URL pour rejoindre la réunion (join_url)
     * @throws \Exception Si la création de la réunion échoue
     */
    public function createMeeting(string $topic, string $startTime): string
    {
        // 1. Récupérer le token
        $token = $this->getAccessToken();

        // 2. Construire le corps de la requête (un simple tableau PHP)
        $body = [
            'topic' => $topic,
            'type' => 2, // 2 = Réunion planifiée
            'start_time' => $startTime, // ex: "2024-05-21T15:00:00Z" (format ISO 8601, 'Z' pour UTC)
            'duration' => 45, // Durée en minutes
            'timezone' => 'Africa/Tunis', // Adaptez selon votre fuseau horaire
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => false,
                'mute_upon_entry' => false,
            ],
        ];

        // 3. Envoyer la requête
        $response = $this->client->request('POST', 'https://api.zoom.us/v2/users/me/meetings', [
            'auth_bearer' => $token, // Raccourci pour 'Authorization: Bearer VOTRE_TOKEN'
            'json' => $body, // HttpClient s'occupe de mettre le header 'Content-Type: application/json' et d'encoder le body
        ]);

        // 4. Analyser la réponse
        if (201 !== $response->getStatusCode()) { // 201 = Created
            throw new \RuntimeException('Erreur lors de la création du meeting (Code ' . $response->getStatusCode() . '): ' . $response->getContent(false));
        }

        $responseData = $response->toArray();

        // On retourne le lien pour le candidat.
        // Si vous voulez le lien pour lancer la réunion en tant qu'admin, c'est "start_url"
        return $responseData['join_url'];
    }
}