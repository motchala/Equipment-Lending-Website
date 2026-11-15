<?php

declare(strict_types=1);

/**
 * google-sheets/core/client.php
 *
 * Single HTTP helper for all Google API calls.
 * Uses file_get_contents() with stream context — same mechanism as the AI chat.
 * No cURL dependency.
 *
 * Public API:
 *   gs_api(string $method, string $url, ?array $body, string $token): array
 *     Returns decoded JSON array. On HTTP/network error returns ['error' => message].
 */

function gs_api(string $method, string $url, ?array $body, string $token): array
{
    $headers  = "Authorization: Bearer {$token}\r\n";
    $headers .= "User-Agent: PUPSync/1.0\r\n";
    $content  = '';

    if ($body !== null) {
        $content  = json_encode($body);
        $headers .= "Content-Type: application/json\r\n";
        $headers .= 'Content-Length: ' . strlen($content) . "\r\n";
    }

    $opts = [
        'http' => [
            'method'           => strtoupper($method),
            'header'           => $headers,
            'content'          => $content,
            'timeout'          => 10,
            'follow_location'  => 1,
            'ignore_errors'    => true,   // lets us read error responses too
        ],
        'ssl' => ['verify_peer' => false],
    ];

    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);

    if ($raw === false) {
        return ['error' => 'Network error calling Google API.'];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['error' => 'Unexpected response from Google API.'];
    }

    return $decoded;
}
