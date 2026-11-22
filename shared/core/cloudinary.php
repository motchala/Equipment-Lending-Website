<?php

declare(strict_types=1);

/**
 * shared/core/cloudinary.php
 *
 * Minimal Cloudinary upload helper.
 * Uses file_get_contents() with stream context — no cURL dependency,
 * works on InfinityFree and XAMPP default config.
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

if (!function_exists('cloudinary_upload')) {
    function cloudinary_upload(string $tmp_path, string $original_filename): string
    {
        $cloud  = (string)($_ENV['CLOUDINARY_CLOUD_NAME'] ?? '');
        $key    = (string)($_ENV['CLOUDINARY_API_KEY']    ?? '');
        $secret = (string)($_ENV['CLOUDINARY_API_SECRET'] ?? '');
        $folder = (string)($_ENV['CLOUDINARY_FOLDER']     ?? 'equipment');

        if ($cloud === '' || $key === '' || $secret === '') {
            error_log('[PUPSync Cloudinary] Missing credentials in .env');
            return '';
        }

        if (!is_readable($tmp_path)) {
            error_log('[PUPSync Cloudinary] Temp file not readable: ' . $tmp_path);
            return '';
        }

        // Build a safe public_id from the original filename (no extension)
        $base_name = pathinfo($original_filename, PATHINFO_FILENAME);
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base_name);
        $public_id = $folder . '/' . time() . '_' . $safe_name;

        // Cloudinary signed upload parameters
        $timestamp = (string)time();
        $params_to_sign = 'folder=' . $folder
            . '&public_id=' . $public_id
            . '&timestamp=' . $timestamp;
        $signature = hash('sha256', $params_to_sign . $secret);

        // Build multipart body
        $boundary = '----PUPSyncBoundary' . bin2hex(random_bytes(8));
        $file_data = file_get_contents($tmp_path);
        if ($file_data === false) {
            error_log('[PUPSync Cloudinary] Could not read temp file: ' . $tmp_path);
            return '';
        }

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"upload\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $file_data . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"api_key\"\r\n\r\n{$key}\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"timestamp\"\r\n\r\n{$timestamp}\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"signature\"\r\n\r\n{$signature}\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"folder\"\r\n\r\n{$folder}\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"public_id\"\r\n\r\n{$public_id}\r\n";
        $body .= "--{$boundary}--\r\n";

        $url = "https://api.cloudinary.com/v1_1/{$cloud}/image/upload";

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: multipart/form-data; boundary={$boundary}\r\n"
                           . "Content-Length: " . strlen($body) . "\r\n",
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log('[PUPSync Cloudinary] Upload request failed for: ' . $original_filename);
            return '';
        }

        $data = json_decode($response, true);
        if (!isset($data['secure_url'])) {
            $err = $data['error']['message'] ?? $response;
            error_log('[PUPSync Cloudinary] Upload error: ' . $err);
            return '';
        }

        return (string)$data['secure_url'];
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

        $cloud  = (string)($_ENV['CLOUDINARY_CLOUD_NAME'] ?? '');
        $key    = (string)($_ENV['CLOUDINARY_API_KEY']    ?? '');
        $secret = (string)($_ENV['CLOUDINARY_API_SECRET'] ?? '');

        if ($cloud === '' || $key === '' || $secret === '') {
            return false;
        }

        // Extract public_id from URL: everything between /upload/vXXXX/ and the extension
        if (!preg_match('#/upload/(?:v\d+/)?(.+)\.[a-z]+$#i', $image_path, $m)) {
            return false;
        }
        $public_id = $m[1];

        $timestamp = (string)time();
        $signature = hash('sha256', 'public_id=' . $public_id . '&timestamp=' . $timestamp . $secret);

        $body = http_build_query([
            'public_id' => $public_id,
            'api_key'   => $key,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $url = "https://api.cloudinary.com/v1_1/{$cloud}/image/destroy";
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                           . "Content-Length: " . strlen($body) . "\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log('[PUPSync Cloudinary] Delete request failed for: ' . $public_id);
            return false;
        }

        $data = json_decode($response, true);
        return ($data['result'] ?? '') === 'ok';
    }
}
