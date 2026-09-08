<?php

namespace Level6\TurnstileBundle\Service;

/**
 * Server-side verification of Cloudflare Turnstile tokens (cf-turnstile-response).
 *
 * Implements the canonical siteverify call: browser -> backend -> siteverify.
 * The secret only comes from bundle configuration; when it is empty,
 * verification is considered disabled for that environment so that
 * unprovisioned environments are never locked out.
 *
 * The HTTP transport is intentionally dependency-free (plain curl with a
 * stream-context fallback) so the bundle works on every Symfony release from
 * 4.0 onward, which predates the symfony/http-client component (introduced in
 * Symfony 4.3).
 */
class TurnstileVerifier
{
    private const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private $turnstileSecret;
    private $allowedHostnames;

    public function __construct(string $turnstileSecret, array $allowedHostnames = [])
    {
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
            $result = $this->siteverify($body);
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

    /**
     * POSTs to Cloudflare and decodes the JSON response.
     */
    private function siteverify(array $body): array
    {
        $raw = function_exists('curl_init')
            ? $this->postWithCurl($body)
            : $this->postWithStreams($body);

        $result = json_decode($raw, true);
        if (!is_array($result)) {
            throw new \RuntimeException('Invalid siteverify response.');
        }

        return $result;
    }

    private function postWithCurl(array $body): string
    {
        $handle = curl_init(self::SITEVERIFY_URL);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($handle);

        if (false === $response) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new \RuntimeException('siteverify request failed: '.$error);
        }

        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if (200 !== $status) {
            throw new \RuntimeException('siteverify returned HTTP '.$status);
        }

        return $response;
    }

    private function postWithStreams(array $body): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($body),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents(self::SITEVERIFY_URL, false, $context);

        if (false === $response) {
            throw new \RuntimeException('siteverify request failed.');
        }

        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                    $status = (int) $matches[1];
                    break;
                }
            }
        }

        if (200 !== $status) {
            throw new \RuntimeException('siteverify returned HTTP '.$status);
        }

        return $response;
    }
}