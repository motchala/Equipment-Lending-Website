<?php
/**
 * sse/core/connection-table.php
 *
 * tbl_sse_connections self-heal and helpers.
 *
 * All functions receive a mysqli $conn from the caller.
 * This file does NOT include any config files.
 * Error log prefix: [PUPSync SSE]
 */

// ─── Ensure table ────────────────────────────────────────────────────────────

/**
 * Create tbl_sse_connections if it does not already exist.
 * Uses $conn->query() — safe for DDL (no user input).
 * Never throws; logs failures and continues.
 */
function sse_ensure_connection_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS tbl_sse_connections (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_type   VARCHAR(16)  NOT NULL,
        user_id     VARCHAR(64)  NOT NULL,
        stream_type VARCHAR(32)  NOT NULL,
        created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        last_ping   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_sse_conn (user_type, user_id, stream_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    try {
        $conn->query($sql);
    } catch (Throwable $e) {
        error_log('[PUPSync SSE] ensure_table: ' . $e->getMessage());
    }
}

// ─── Register (upsert) ───────────────────────────────────────────────────────

/**
 * Remove stale rows, then upsert the current connection.
 * Stale = last_ping older than 2 minutes.
 * Never throws; logs failures and continues.
 */
function sse_register_connection(
    mysqli $conn,
    string $userType,
    string $userId,
    string $streamType
): void {
    try {
        // 1. Delete stale rows (older than 2 minutes)
        $del = $conn->prepare(
            'DELETE FROM tbl_sse_connections
             WHERE last_ping < DATE_SUB(NOW(), INTERVAL 2 MINUTE)'
        );
        if ($del === false) {
            throw new RuntimeException($conn->error);
        }
        $del->execute();
        $del->close();

        // 2. Upsert current connection
        $ins = $conn->prepare(
            'INSERT INTO tbl_sse_connections (user_type, user_id, stream_type)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE last_ping = NOW()'
        );
        if ($ins === false) {
            throw new RuntimeException($conn->error);
        }
        $ins->bind_param('sss', $userType, $userId, $streamType);
        $ins->execute();
        $ins->close();
    } catch (Throwable $e) {
        error_log('[PUPSync SSE] register_connection: ' . $e->getMessage());
    }
}

// ─── Ping ────────────────────────────────────────────────────────────────────

/**
 * Update last_ping to NOW() for an existing connection row.
 * Never throws; logs failures and continues.
 */
function sse_ping_connection(
    mysqli $conn,
    string $userType,
    string $userId,
    string $streamType
): void {
    try {
        $stmt = $conn->prepare(
            'UPDATE tbl_sse_connections
             SET last_ping = NOW()
             WHERE user_type = ? AND user_id = ? AND stream_type = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException($conn->error);
        }
        $stmt->bind_param('sss', $userType, $userId, $streamType);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[PUPSync SSE] ping_connection: ' . $e->getMessage());
    }
}

// ─── Remove ──────────────────────────────────────────────────────────────────

/**
 * Delete the connection row on stream close.
 * Never throws; logs failures and continues.
 */
function sse_remove_connection(
    mysqli $conn,
    string $userType,
    string $userId,
    string $streamType
): void {
    try {
        $stmt = $conn->prepare(
            'DELETE FROM tbl_sse_connections
             WHERE user_type = ? AND user_id = ? AND stream_type = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException($conn->error);
        }
        $stmt->bind_param('sss', $userType, $userId, $streamType);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[PUPSync SSE] remove_connection: ' . $e->getMessage());
    }
}
