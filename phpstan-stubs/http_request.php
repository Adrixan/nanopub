<?php

declare(strict_types=1);

/**
 * NanoPub HTTP request function.
 *
 * @param string $method HTTP method (GET, POST, etc.)
 * @param string $url URL to request
 * @param array<string, mixed> $data Request data
 * @param array<string, string> $headers Request headers
 * @param string $bodyType Body encoding type ('none', 'form', 'json')
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function http_request(string $method, string $url, array $data = [], array $headers = [], string $bodyType = 'none'): array
{
}
