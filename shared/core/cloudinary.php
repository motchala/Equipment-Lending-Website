<?php

declare(strict_types=1);

/**
 * shared/core/cloudinary.php
 *
 * Minimal Cloudinary upload helper.
 * Uses cURL when the host has it, otherwise PHP streams (file_get_contents),
 * so it works on InfinityFree and the XAMPP default config.
 * When an upload fails, cloudinary_last_error() says why.
 *
 * USAGE:
 *   require_once PS_ROOT . '/shared/core/cloudinary.php';
 *   $url = cloudinary_upload($tmp_path, $original_filename);
 *   if ($url === '') { // upload failed }
 *
 * Returns the secure Cloudinary URL on success, '' on failure.
 * Failures are logged via error_log — they never throw.
 *
 * Required .env keys:
 *   CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY,
 *   CLOUDINARY_API_SECRET, CLOUDINARY_FOLDER
 */

if (!function_exists('cloudinary_last_error')) {
    /**
     * Remembers why the last Cloudinary call failed so the admin screen can show it
     * (and error_log has it) instead of the upload failing silently.
     */
    function cloudinary_last_error(?string $set = null): string
    {
        static $err = '';
        if ($set !== null) {
            $err = $set;
        }
        return $err;
    }
}

if (!function_exists('cloudinary_env')) {
    /** Reads a setting from $_ENV, getenv() or $_SERVER (shared hosts differ on which is filled). */
    function cloudinary_env(string $key, string $default = ''): string
    {
        foreach ([$_ENV[$key] ?? null, getenv($key) ?: null, $_SERVER[$key] ?? null] as $v) {
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
        return $default;
    }
}

if (!function_exists('cloudinary_post')) {
    /**
     * POSTs to the Cloudinary API. Uses cURL when the host has it, otherwise PHP streams.
     * $fields may contain a CURLFile / ['path' => ..., 'name' => ...] for the "file" field.
     * Returns the decoded JSON array, or null on a transport failure (reason in cloudinary_last_error()).
     */
    function cloudinary_post(string $url, array $fields, ?string $file_path = null): ?array
    {
        $raw = false;
        $http_code = 0;

        if (function_exists('curl_init')) {
            $post = $fields;
            if ($file_path !== null) {
                $post['file'] = new CURLFile($file_path, 'application/octet-stream', 'upload');
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $post,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT        => 40,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $raw = curl_exec($ch);
            $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                cloudinary_last_error('Could not reach Cloudinary (' . curl_error($ch) . ').');
                curl_close($ch);
                return null;
            }
            curl_close($ch);
        } else {
            $boundary = '----PUPSyncBoundary' . bin2hex(random_bytes(8));
            $body = '';
            foreach ($fields as $name => $value) {
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
            }
            if ($file_path !== null) {
                $file_data = @file_get_contents($file_path);
                if ($file_data === false) {
                    cloudinary_last_error('Could not read the uploaded temp file.');
                    return null;
                }
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"upload\"\r\n";
                $body .= "Content-Type: application/octet-stream\r\n\r\n{$file_data}\r\n";
            }
            $body .= "--{$boundary}--\r\n";

            $context = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: multipart/form-data; boundary={$boundary}\r\n"
                                     . "Content-Length: " . strlen($body) . "\r\n",
                    'content'       => $body,
                    'timeout'       => 40,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) {
                $last = error_get_last();
                cloudinary_last_error('Could not reach Cloudinary (' . ($last['message'] ?? 'outbound connection blocked or allow_url_fopen is off') . ').');
                return null;
            }
        }

        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            cloudinary_last_error('Unexpected reply from Cloudinary (HTTP ' . $http_code . ').');
            return null;
        }
        return $data;
    }
}

if (!function_exists('cloudinary_upload')) {
    function cloudinary_upload(string $tmp_path, string $original_filename): string
    {
        cloudinary_last_error('');

        $cloud  = cloudinary_env('CLOUDINARY_CLOUD_NAME');
        $key    = cloudinary_env('CLOUDINARY_API_KEY');
        $secret = cloudinary_env('CLOUDINARY_API_SECRET');
        // An empty CLOUDINARY_FOLDER= line must not produce a public_id starting with "/"
        $folder = trim(cloudinary_env('CLOUDINARY_FOLDER', 'equipment'), "/ \t");
        if ($folder === '') {
            $folder = 'equipment';
        }

        if ($cloud === '' || $key === '' || $secret === '') {
            cloudinary_last_error('Cloudinary credentials are missing in the .env file on the server.');
            error_log('[PUPSync Cloudinary] ' . cloudinary_last_error());
            return '';
        }

        if (!is_readable($tmp_path)) {
            cloudinary_last_error('Temp file not readable.');
            error_log('[PUPSync Cloudinary] Temp file not readable: ' . $tmp_path);
            return '';
        }

        // Build a safe public_id from the original filename (no extension)
        $base_name = pathinfo($original_filename, PATHINFO_FILENAME);
        $safe_name = trim((string)preg_replace('/[^a-zA-Z0-9_-]/', '_', $base_name), '_');
        if ($safe_name === '') {
            $safe_name = 'image';
        }
        $public_id = $folder . '/' . time() . '_' . substr($safe_name, 0, 60);

        $url = "https://api.cloudinary.com/v1_1/{$cloud}/image/upload";

        // Cloudinary accounts sign with SHA-1 by default and SHA-256 when switched in
        // account settings. Try SHA-1 first, retry with SHA-256 if the signature is rejected.
        foreach (['sha1', 'sha256'] as $algo) {
            $timestamp = (string)time();
            $params_to_sign = 'folder=' . $folder
                . '&public_id=' . $public_id
                . '&timestamp=' . $timestamp;

            $data = cloudinary_post($url, [
                'api_key'   => $key,
                'timestamp' => $timestamp,
                'signature' => hash($algo, $params_to_sign . $secret),
                'folder'    => $folder,
                'public_id' => $public_id,
            ], $tmp_path);

            if ($data === null) {
                error_log('[PUPSync Cloudinary] ' . cloudinary_last_error() . ' File: ' . $original_filename);
                return '';
            }
            if (isset($data['secure_url'])) {
                return (string)$data['secure_url'];
            }

            $err = (string)($data['error']['message'] ?? 'Unknown Cloudinary error');
            cloudinary_last_error('Cloudinary rejected the upload: ' . $err);
            error_log('[PUPSync Cloudinary] Upload error (' . $algo . '): ' . $err);
            if (stripos($err, 'signature') === false) {
                break; // not a signature problem — retrying will not help
            }
        }

        return '';
    }
}

if (!function_exists('cloudinary_delete')) {
    /**
     * Delete an image from Cloudinary by its secure URL.
     * Silently no-ops for local paths or the default image.
     * Returns true if deleted, false otherwise.
     */
    function cloudinary_delete(string $image_path): bool
    {
        if (!str_starts_with($image_path, 'https://res.cloudinary.com/')) {
            return false; // local path or default — not our job
        }

        $cloud  = cloudinary_env('CLOUDINARY_CLOUD_NAME');
        $key    = cloudinary_env('CLOUDINARY_API_KEY');
        $secret = cloudinary_env('CLOUDINARY_API_SECRET');

        if ($cloud === '' || $key === '' || $secret === '') {
            return false;
        }

        // Extract public_id from URL: everything between /upload/vXXXX/ and the extension
        if (!preg_match('#/upload/(?:v\d+/)?(.+)\.[a-z]+$#i', $image_path, $m)) {
            return false;
        }
        $public_id = $m[1];

        $url = "https://api.cloudinary.com/v1_1/{$cloud}/image/destroy";
        foreach (['sha1', 'sha256'] as $algo) {
            $timestamp = (string)time();
            $data = cloudinary_post($url, [
                'public_id' => $public_id,
                'api_key'   => $key,
                'timestamp' => $timestamp,
                'signature' => hash($algo, 'public_id=' . $public_id . '&timestamp=' . $timestamp . $secret),
            ]);
            if ($data === null) {
                error_log('[PUPSync Cloudinary] Delete request failed for: ' . $public_id . ' — ' . cloudinary_last_error());
                return false;
            }
            if (($data['result'] ?? '') === 'ok') {
                return true;
            }
            $err = (string)($data['error']['message'] ?? '');
            if (stripos($err, 'signature') === false) {
                return false;
            }
        }
        return false;
    }
}
