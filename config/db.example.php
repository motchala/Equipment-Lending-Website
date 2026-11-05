<?php

/**
 * db.php
 *
 * Database connection bootstrap for PUPSync.
 *
 * Reads credentials from the project-root .env file via env.php,
 * then opens (and caches) a MySQLi connection.
 *
 * USAGE:
 *   require_once __DIR__ . '/config/db.php';
 *   $conn = getDB();
 *
 * The connection is cached in a static variable so calling getDB()
 * multiple times in one request returns the same mysqli object
 * without opening a second connection.
 *
 * SETUP: copy this file to config/db.php (same folder). Nothing else to edit:
 * it reads the DB_* values from the .env file in the project root.
 * config/db.php itself is excluded from version control
 * (.gitignore) because it bootstraps the credential loader.
 * Re-create it on every fresh clone or deployment using .env.example
 * as a reference for the required .env variables.
 */

require_once __DIR__ . '/env.php';
load_env();

/**
 * Return the shared MySQLi connection for this request.
 *
 * @return mysqli
 * @throws RuntimeException if the connection cannot be established.
 */
function getDB(): mysqli
{
    static $conn = null;

    if ($conn !== null) {
        return $conn;
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';
    $name = $_ENV['DB_NAME'] ?? 'lending_db';

    // Suppress the default connection warning; we handle the error ourselves.
    $conn = @new mysqli($host, $user, $pass, $name);

    if ($conn->connect_error) {
        // Log the real error server-side (display_errors is off in production).
        error_log('[PUPSync] Database connection failed: ' . $conn->connect_error);

        // Return a user-friendly 500 rather than a blank page or leaked credentials.
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body>'
            . '<h2>Service temporarily unavailable.</h2>'
            . '<p>The system could not connect to the database. '
            . 'Please contact the administrator or try again in a moment.</p>'
            . '</body></html>';
        exit;
    }

    // Always use UTF-8 to match the database charset.
    $conn->set_charset('utf8mb4');

    return $conn;
}
