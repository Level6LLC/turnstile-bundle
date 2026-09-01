<?php

namespace Level6\TurnstileBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Server-side verification of Cloudflare Turnstile tokens (cf-turnstile-response).
 *
 * Implements the canonical siteverify call: browser -> backend -> siteverify.
 * The secret only comes from bundle configuration; when it is empty,
 * verification is considered disabled for that environment so that
 * unprovisioned environments are never locked out.
 */
class TurnstileVerifier
{
    private const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private $httpClient;
    private $turnstileSecret;
    private $allowedHostnames;

    public function __construct(HttpClientInterface $httpClient, string $turnstileSecret, array $allowedHostnames = [])
    {
        $this->httpClient = $httpClient;
        $this->turnstileSecret = $turnstileSecret;
        $this->allowedHostnames = $allowedHostnames;
    }

    /**
     * Verifies the token submitted for the given protected surface action.
     */
    public function verify(?string $token, string $expectedAction, ?string $remoteIp = null): bool
    {
        if ('' === trim($this->turnstileSecret)) {
            // Turnstile not configured for this environment: don't lock users out.
            return true;
        }

        if (null === $token || '' === trim($token) || strlen($token) > 2048) {
            return false;
        }

        $body = [
            'secret' => $this->turnstileSecret,
            'response' => $token,
        ];

        if (null !== $remoteIp && '' !== trim($remoteIp)) {
            $body['remoteip'] = $remoteIp;
        }

        try {
            $response = $this->httpClient->request('POST', self::SITEVERIFY_URL, [
                'body' => $body,
                'timeout' => 10,
            ]);

            if (200 !== $response->getStatusCode()) {
                return false;
            }

            $result = $response->toArray(false);
        } catch (\Throwable $e) {
            return false;
        }

        if (true !== ($result['success'] ?? false)) {
            return false;
        }

        if (($result['action'] ?? null) !== $expectedAction) {
            return false;
        }

        if ([] !== $this->allowedHostnames && !in_array($result['hostname'] ?? null, $this->allowedHostnames, true)) {
            return false;
        }

        return true;
    }
}