<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ZoomService
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $zoomAccountId,
        private readonly string $zoomClientId,
        private readonly string $zoomClientSecret
    ) {}

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $encodedAuth = base64_encode($this->zoomClientId . ':' . $this->zoomClientSecret);
        $url         = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . $this->zoomAccountId;

        $response = $this->httpClient->request('POST', $url, [
            'headers' => ['Authorization' => 'Basic ' . $encodedAuth],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Erreur authentification Zoom: ' . $response->getContent(false));
        }

        $this->accessToken = $response->toArray()['access_token'];
        return $this->accessToken;
    }

    public function createMeeting(string $topic, string $startTime): string
    {
        $token    = $this->getAccessToken();
        $response = $this->httpClient->request('POST', 'https://api.zoom.us/v2/users/me/meetings', [
            'auth_bearer' => $token,
            'json' => [
                'topic'      => $topic,
                'type'       => 2,
                'start_time' => $startTime,
                'duration'   => 45,
                'timezone'   => 'Africa/Tunis',
                'settings'   => [
                    'host_video'        => true,
                    'participant_video' => true,
                    'join_before_host'  => false,
                    'mute_upon_entry'   => false,
                ],
            ],
        ]);

        if (201 !== $response->getStatusCode()) {
            throw new \RuntimeException('Erreur création meeting Zoom: ' . $response->getContent(false));
        }

        return $response->toArray()['join_url'];
    }
}
