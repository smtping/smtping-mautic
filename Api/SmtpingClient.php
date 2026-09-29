<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Minimal client for the SMTPing API, built on the Guzzle copy that ships with Mautic.
 */
class SmtpingClient
{
    public const BASE_URL = 'https://api.smtping.com/api/v1';
    public const BULK_MAX = 100000;

    private const SAFE = ['valid', 'alias'];
    private const AVOID = ['invalid', 'spamtrap', 'disposable', 'blacklisted', 'complainer', 'spambot', 'inbox_full'];

    private Client $http;

    public function __construct(private string $apiKey, float $timeout = 60.0, private int $maxRetries = 3)
    {
        $this->http = new Client([
            'base_uri'    => self::BASE_URL.'/',
            'timeout'     => $timeout,
            'http_errors' => false,
            'headers'     => [
                'X-API-Key'  => $apiKey,
                'Accept'     => 'application/json',
                'User-Agent' => 'smtping-mautic/0.9.0',
            ],
        ]);
    }

    public static function band(?string $status): string
    {
        if (in_array($status, self::SAFE, true)) {
            return 'safe';
        }
        if (in_array($status, self::AVOID, true)) {
            return 'avoid';
        }

        return 'judgement';
    }

    /** @return array<string, mixed> */
    public function verify(string $email): array
    {
        $r = $this->request('POST', 'verify/single', ['email' => trim($email)]);
        $r['band'] = self::band($r['status'] ?? null);

        return $r;
    }

    /**
     * @param string[] $emails
     *
     * @return array<string, mixed>
     */
    public function createBulk(array $emails): array
    {
        return $this->request('POST', 'verify/bulk', ['emails' => array_values($emails)]);
    }

    /** @return array<string, mixed> */
    public function getBulk(string $jobId): array
    {
        return $this->request('GET', 'verify/bulk/'.rawurlencode($jobId));
    }

    /** @return array<int, array<string, mixed>> */
    public function bulkResults(string $jobId): array
    {
        $data = $this->request('GET', 'verify/bulk/'.rawurlencode($jobId).'/result');
        if (isset($data['results']) && is_array($data['results'])) {
            $data = $data['results'];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /** @return array<string, mixed> */
    public function credits(): array
    {
        return $this->request('GET', 'credits');
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $options = null === $body ? [] : ['json' => $body];
        for ($attempt = 0; ; ++$attempt) {
            try {
                $res = $this->http->request($method, $path, $options);
            } catch (GuzzleException $e) {
                if ($attempt < $this->maxRetries) {
                    usleep($this->backoff($attempt + 1));
                    continue;
                }
                throw new SmtpingException('Network error: '.$e->getMessage(), 0);
            }

            $status = $res->getStatusCode();
            $text   = (string) $res->getBody();
            if ($status >= 200 && $status < 300) {
                $data = json_decode($text, true);

                return is_array($data) ? $data : [];
            }
            if ((429 === $status || $status >= 500) && $attempt < $this->maxRetries) {
                $retry = (int) $res->getHeaderLine('Retry-After');
                usleep($retry > 0 ? $retry * 1000000 : $this->backoff($attempt + 1));
                continue;
            }
            $data    = json_decode($text, true);
            $message = is_array($data) ? ($data['error'] ?? $data['message'] ?? null) : null;
            throw new SmtpingException(is_string($message) ? $message : 'SMTPing API returned HTTP '.$status, $status);
        }
    }

    private function backoff(int $attempt): int
    {
        return (int) (min(1000 * 2 ** ($attempt - 1), 15000) + random_int(0, 250)) * 1000;
    }
}
