<?php

namespace Hosting\SharedLicense;

class Error extends \Exception
{
}

class Api
{
    protected $token = "";
    protected $baseUrl = "https://sharedlicense.com/client/modules/addons/LicReseller/api";
    protected $timeout = 20;
    protected $userAgent = "SharedLicense HostBill Module/1.0";

    public function __construct($token, $baseUrl = "", $timeout = 20)
    {
        $this->token = trim((string) $token);
        if ($baseUrl) {
            $this->baseUrl = rtrim(trim((string) $baseUrl), "/");
        }
        $this->timeout = max(5, (int) $timeout);
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

        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => $this->userAgent,
        ];

        if ($method !== 'GET') {
            $body = !empty($payload) ? json_encode($payload) : '{}';
            if ($body === false) {
                throw new Error('Failed to encode API request payload', 1);
            }
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
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

        return $this->parseResponse($raw, $httpCode);
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