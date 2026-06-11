<?php
/**
 * Minimal .env loader for PUPSync.
 * Reads the .env file from the project root and populates $_ENV.
 * Call load_env() once before accessing any env variable.
 */
function load_env(): void
{
    // Walk up from /includes/ to the project root
    $env_path = dirname(__DIR__) . '/.env';

    if (!file_exists($env_path)) {
        error_log('[PUPSync] .env file not found at: ' . $env_path);
        return;
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(trim($line), '#')) continue;

        // Only process lines that contain =
        if (!str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);

        $key   = trim($key);
        $value = trim($value);

        // Strip surrounding quotes if present ("value" or 'value')
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Only set if not already defined (system env takes priority)
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}