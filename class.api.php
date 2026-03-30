<?php

/**
 * SharedLicense HostBill Module
 *
 * Copyright (C) 2026 Nguyen Thanh An by Pho Tue SoftWare Solutions JSC
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Hosting\SharedLicense;

class Error extends \Exception
{
}

class Api
{
    protected $token = "";
    protected $baseUrl = "https://sharedlicense.com/client/modules/addons/LicReseller/api";
    protected $timeout = 20;
    protected $connectTimeout = 10;
    protected $userAgent = "SharedLicense HostBill Module/1.0";
    protected $maxRetries = 2;
    protected $retryDelayMs = 250;

    public function __construct($token, $baseUrl = "", $timeout = 20, $connectTimeout = 10, $maxRetries = 2)
    {
        $this->token = trim((string) $token);
        if ($baseUrl) {
            $this->baseUrl = rtrim(trim((string) $baseUrl), "/");
        }
        $this->timeout = max(5, (int) $timeout);
        $this->connectTimeout = max(2, (int) $connectTimeout);
        $this->userAgent = 'SharedLicense HostBill Module/1.0.1';
        $this->maxRetries = max(0, (int) $maxRetries);
    }

    public function account()
    {
        return $this->request('GET', '/account');
    }

    public function products($productId = null)
    {
        $query = [];
        if ($productId !== null && $productId !== '') {
            $query['id'] = $productId;
        }
        return $this->request('GET', '/products', $query);
    }

    public function orderProduct($productId, array $payload)
    {
        return $this->request('POST', '/products/' . rawurlencode((string) $productId) . '/order', [], $payload);
    }

    public function getLicenses()
    {
        return $this->request('GET', '/licenses');
    }

    public function getLicenseDetails($licenseId)
    {
        return $this->request('GET', '/licenses/' . rawurlencode((string) $licenseId));
    }

    public function renewLicense($licenseId)
    {
        return $this->request('POST', '/licenses/' . rawurlencode((string) $licenseId) . '/renew');
    }

    public function suspendLicense($licenseId, $reason = '')
    {
        $payload = [];
        if (trim((string) $reason) !== '') {
            $payload['reason'] = trim((string) $reason);
        }
        return $this->request('POST', '/licenses/' . rawurlencode((string) $licenseId) . '/suspend', [], $payload);
    }

    public function unsuspendLicense($licenseId)
    {
        return $this->request('POST', '/licenses/' . rawurlencode((string) $licenseId) . '/unsuspend');
    }

    public function cancelLicense($licenseId)
    {
        return $this->request('POST', '/licenses/' . rawurlencode((string) $licenseId) . '/cancel');
    }

    public function reissueLicense($licenseId)
    {
        return $this->request('POST', '/licenses/' . rawurlencode((string) $licenseId) . '/reissue');
    }

    public function changeIp($licenseId, $ip)
    {
        return $this->request('POST', '/licenses/' . rawurlencode((string) $licenseId) . '/change-ip', [], ['ip' => (string) $ip]);
    }

    public function enableAutoRenewal($licenseId)
    {
        return $this->request('POST', '/licenses/' . rawurlencode((string) $licenseId) . '/enable-auto-renewal');
    }

    public function disableAutoRenewal($licenseId)
    {
        return $this->request('POST', '/licenses/' . rawurlencode((string) $licenseId) . '/disable-auto-renewal');
    }

    public function findLicense($productId, array $customfields)
    {
        return $this->request('POST', '/licenses/find', [], [
            'pid' => $productId,
            'customfields' => (object) $customfields,
        ]);
    }

    protected function request($method, $path, array $query = [], array $payload = [])
    {
        if ($this->token === '') {
            throw new Error('API token is empty', 1);
        }

        $method = strtoupper(trim((string) $method));
        $url = $this->buildUrl($path, $query);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];

        $attempt = 0;
        $maxAttempts = $this->maxRetries + 1;

        do {
            $attempt++;

            $curl = curl_init();
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];

            if ($method !== 'GET') {
                $body = !empty($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : '{}';
                if ($body === false) {
                    throw new Error('Failed to encode API request payload', 1);
                }
                $reqHeaders = $headers;
                $reqHeaders[] = 'Content-Type: application/json';
                $options[CURLOPT_HTTPHEADER] = $reqHeaders;
                $options[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($curl, $options);

        if (class_exists('HBDebug') && is_callable(['HBDebug', 'debug'])) {
            call_user_func(['HBDebug', 'debug'], 'HB >> SharedLicense', [
                'method' => $method,
                'url' => $url,
                'query' => $query,
                'payload' => $payload,
                'headers' => ['Authorization' => 'Bearer ***'],
            ]);
        }

            $raw = curl_exec($curl);
            $errno = curl_errno($curl);
            $error = curl_error($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($raw === false || $errno) {
                if ($this->shouldRetry($method, $errno, $httpCode, $attempt, $maxAttempts)) {
                    $this->sleepBeforeRetry($attempt);
                    continue;
                }
                throw new Error($error ? $error : 'Unknown cURL error', $errno ? $errno : 1);
            }

        if (class_exists('HBDebug') && is_callable(['HBDebug', 'debug'])) {
            call_user_func(['HBDebug', 'debug'], 'HB << SharedLicense', [
                'method' => $method,
                'url' => $url,
                'http_code' => $httpCode,
                'response' => $raw,
            ]);
        }

            try {
                return $this->parseResponse($raw, $httpCode);
            } catch (Error $ex) {
                if ($this->shouldRetry($method, 0, $httpCode, $attempt, $maxAttempts)) {
                    $this->sleepBeforeRetry($attempt);
                    continue;
                }
                throw $ex;
            }
        } while ($attempt < $maxAttempts);

        throw new Error('Remote API request failed after retries', 1);
    }

    protected function shouldRetry($method, $curlErrno, $httpCode, $attempt, $maxAttempts)
    {
        if (strtoupper((string) $method) !== 'GET') {
            return false;
        }

        if ($attempt >= $maxAttempts) {
            return false;
        }

        // Transient network errors.
        if (in_array((int) $curlErrno, [6, 7, 28, 35, 52, 56], true)) {
            return true;
        }

        // Retry on rate limits and transient server errors.
        if ($httpCode === 429 || ($httpCode >= 500 && $httpCode <= 599)) {
            return true;
        }

        return false;
    }

    protected function sleepBeforeRetry($attempt)
    {
        $base = max(50, (int) $this->retryDelayMs);
        // Exponential backoff: 250ms, 500ms, 1000ms...
        $delay = (int) ($base * pow(2, max(0, $attempt - 1)));
        usleep($delay * 1000);
    }

    protected function buildUrl($path, array $query = [])
    {
        $url = $this->baseUrl . '/' . ltrim((string) $path, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    protected function parseResponse($raw, $httpCode)
    {
        $trimmed = trim((string) $raw);
        if ($trimmed === '') {
            throw new Error('Empty response returned by remote API', 1);
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (stripos($trimmed, '<html') !== false) {
                throw new Error($this->extractText($trimmed), 1);
            }
            throw new Error($trimmed, 1);
        }

        if ($httpCode >= 400) {
            $message = $this->extractErrorMessage($decoded);
            throw new Error($message ? $message : ('Remote API returned HTTP ' . $httpCode), 1);
        }

        if (is_array($decoded) && array_key_exists('success', $decoded) && !$decoded['success']) {
            throw new Error($this->extractErrorMessage($decoded), 1);
        }

        return $decoded;
    }

    protected function extractErrorMessage($decoded)
    {
        if (is_string($decoded)) {
            return trim($decoded);
        }
        if (!is_array($decoded)) {
            return 'Remote API error';
        }

        foreach (['message', 'error', 'errors', 'detail'] as $key) {
            if (!isset($decoded[$key])) {
                continue;
            }
            if (is_string($decoded[$key])) {
                return trim($decoded[$key]);
            }
            if (is_array($decoded[$key])) {
                return trim(implode('; ', array_map('strval', $decoded[$key])));
            }
        }

        return 'Remote API error';
    }

    protected function extractText($html)
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));
        return $text ? $text : 'Unexpected HTML response returned by remote API';
    }
}

?>