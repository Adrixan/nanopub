<?php

declare(strict_types=1);

/**
 * NanoPub HTTP Utilities
 * 
 * HTTP client utilities for federation and API requests.
 */

/**
 * Make an HTTP GET request.
 * 
 * @param string $url URL to request
 * @param array<string, string> $headers Request headers
 * @return array{status: int, headers: array<string, string>, body: string} Response
 */
function http_get(string $url, array $headers = []): array
{
    return http_request('GET', $url, [], $headers);
}

/**
 * Make an HTTP POST request with form data.
 * 
 * @param string $url URL to request
 * @param array<string, mixed> $data POST data
 * @param array<string, string> $headers Request headers
 * @return array{status: int, headers: array<string, string>, body: string} Response
 */
function http_post(string $url, array $data, array $headers = []): array
{
    return http_request('POST', $url, $data, $headers, 'form');
}

/**
 * Make an HTTP POST request with JSON body.
 * 
 * @param string $url URL to request
 * @param array<string, mixed> $data JSON data
 * @param array<string, string> $headers Request headers
 * @return array{status: int, headers: array<string, string>, body: string} Response
 */
function http_post_json(string $url, array $data, array $headers = []): array
{
    return http_request('POST', $url, $data, $headers, 'json');
}

/**
 * Internal HTTP request handler.
 * 
 * @param string $method HTTP method
 * @param string $url URL to request
 * @param array<string, mixed> $data Request body data
 * @param array<string, string> $headers Request headers
 * @param string $bodyType Body type: 'form', 'json', or 'none'
 * @return array{status: int, headers: array<string, string>, body: string} Response
 */
function http_request(string $method, string $url, array $data = [], array $headers = [], string $bodyType = 'none'): array
{
    $ch = curl_init();
    
    if ($ch === false) {
        return ['status' => 0, 'headers' => [], 'body' => 'Failed to initialize cURL'];
    }
    
    // Set basic options
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'NanoPub/1.0 (ActivityPub)',
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) {
                return $len;
            }
            $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
            return $len;
        },
    ]);
    
    // Set method
    $method = strtoupper($method);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }
    
    // Set body
    if ($method !== 'GET' && !empty($data)) {
        if ($bodyType === 'json') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers['Content-Type'] = 'application/json';
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    
    // Set headers
    if (!empty($headers)) {
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    }
    
    $responseHeaders = [];
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($body === false) {
        return [
            'status' => 0,
            'headers' => [],
            'body' => $error ?: 'Unknown cURL error',
        ];
    }
    
    return [
        'status' => (int) $status,
        'headers' => $responseHeaders,
        'body' => $body,
    ];
}

/**
 * Stream download a file to disk.
 * 
 * @param string $url URL to download from
 * @param string $destination Local file path
 * @return bool True on success
 */
function http_download(string $url, string $destination): bool
{
    $ch = curl_init();
    
    if ($ch === false) {
        return false;
    }
    
    $fp = fopen($destination, 'wb');
    
    if ($fp === false) {
        curl_close($ch);
        return false;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_FILE => $fp,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'NanoPub/1.0 (ActivityPub)',
    ]);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    
    curl_close($ch);
    fclose($fp);
    
    if ($result === false) {
        @unlink($destination);
        return false;
    }
    
    return true;
}

/**
 * Build a URL with query string.
 * 
 * @param string $base Base URL
 * @param array<string, mixed> $query Query parameters
 * @return string Full URL
 */
function build_url(string $base, array $query = []): string
{
    if (empty($query)) {
        return $base;
    }
    
    $separator = str_contains($base, '?') ? '&' : '?';
    return $base . $separator . http_build_query($query);
}

/**
 * Parse a URL and return components.
 * 
 * @param string $url URL to parse
 * @return array{scheme: string, host: string, port: int, path: string, query: string, fragment: string} URL components
 */
function parse_url_components(string $url): array
{
    $parsed = parse_url($url);
    
    if ($parsed === false) {
        return [
            'scheme' => '',
            'host' => '',
            'port' => 0,
            'path' => '',
            'query' => '',
            'fragment' => '',
        ];
    }
    
    return [
        'scheme' => $parsed['scheme'] ?? '',
        'host' => $parsed['host'] ?? '',
        'port' => $parsed['port'] ?? 0,
        'path' => $parsed['path'] ?? '',
        'query' => $parsed['query'] ?? '',
        'fragment' => $parsed['fragment'] ?? '',
    ];
}

/**
 * Get content type from response headers.
 * 
 * @param array<string, string> $headers Response headers
 * @return string|null Content type or null if not found
 */
function get_content_type(array $headers): ?string
{
    $contentType = $headers['content-type'] ?? null;
    
    if ($contentType === null) {
        return null;
    }
    
    // Strip charset and other parameters
    $parts = explode(';', $contentType);
    return trim($parts[0]);
}