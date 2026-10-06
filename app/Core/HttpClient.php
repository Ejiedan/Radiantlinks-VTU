<?php

declare(strict_types=1);

namespace App\Core;

final class HttpClient
{
    public function getJson(string $url, array $headers = [], int $timeout = 45): array
    {
        return $this->requestJson('GET', $url, [], $headers, $timeout);
    }

    public function postJson(string $url, array $payload, array $headers = [], int $timeout = 45): array
    {
        return $this->requestJson('POST', $url, $payload, $headers, $timeout);
    }

    private function requestJson(string $method, string $url, array $payload = [], array $headers = [], int $timeout = 45): array
    {
        $response = $this->attemptJson($method, $url, $payload, $headers, $timeout, false);
        if (!$response['ok'] && (int) ($response['status'] ?? 0) === 0) {
            $fallback = $this->attemptJson($method, $url, $payload, $headers, $timeout, true);
            if ($fallback['status'] !== 0 || $fallback['body'] !== null || $fallback['error'] !== $response['error']) {
                $fallback['first_error'] = $response['error'] ?? null;
                return $fallback;
            }
        }

        return $response;
    }

    private function attemptJson(string $method, string $url, array $payload, array $headers, int $timeout, bool $forceIpv4): array
    {
        $ch = curl_init($url);
        $headers = array_values(array_unique(array_merge([
            'Accept: application/json',
            'Content-Type: application/json',
        ], $headers)));

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => max(30, $timeout),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => 'RadiantlinksVTU/1.0 (+https://localhost)',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        if ($forceIpv4 && defined('CURL_IPRESOLVE_V4')) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => $error,
                'errno' => $errno,
                'body' => null,
                'ip_mode' => $forceIpv4 ? 'ipv4' : 'default',
            ];
        }

        $decoded = json_decode((string) $body, true);
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'error' => null,
            'body' => json_last_error() === JSON_ERROR_NONE ? $decoded : $body,
            'ip_mode' => $forceIpv4 ? 'ipv4' : 'default',
        ];
    }
}
