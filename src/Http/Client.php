<?php

namespace LsCaptcha\Http;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Minimal cURL client, no external dependency (avoids collisions with the core
 * vendor whose Symfony/Guzzle versions differ between PS 1.7.8 and PS 9).
 */
class Client
{
    /** @var int */
    private $timeout;

    public function __construct(int $timeout = 5)
    {
        $this->timeout = $timeout > 0 ? $timeout : 5;
    }

    /**
     * @param array<string,mixed> $fields
     * @param string[]            $headers
     */
    public function postForm(string $url, array $fields, array $headers = []): Response
    {
        return $this->request(
            $url,
            http_build_query($fields),
            array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers)
        );
    }

    /**
     * @param array<string,mixed> $body
     * @param string[]            $headers
     */
    public function postJson(string $url, array $body, array $headers = []): Response
    {
        $json = json_encode($body);

        return $this->request(
            $url,
            $json === false ? '' : $json,
            array_merge(['Content-Type: application/json'], $headers)
        );
    }

    /**
     * @param string[] $headers
     */
    private function request(string $url, string $body, array $headers): Response
    {
        $response = new Response();

        $ch = curl_init($url);
        if ($ch === false) {
            $response->networkError = true;

            return $response;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);

        $raw = curl_exec($ch);

        if ($raw === false || curl_errno($ch) !== 0) {
            $response->networkError = true;
            curl_close($ch);

            return $response;
        }

        $response->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response->raw = (string) $raw;
        curl_close($ch);

        $decoded = json_decode((string) $raw, true);
        $response->data = is_array($decoded) ? $decoded : null;

        return $response;
    }
}
