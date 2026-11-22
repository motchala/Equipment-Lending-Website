<?php
/**
 * sse/core/stream.php — SSE protocol helpers
 *
 * Pure SSE mechanics only. No business logic, no DB queries, no config includes.
 */

/**
 * Emit SSE response headers and prepare the output buffer for streaming.
 *
 * Header order matches the task spec. After headers are sent, any existing
 * output buffer is discarded and PHP auto-flush is enabled — both steps are
 * required for LiteSpeed shared hosting where the SAPI buffer would otherwise
 * hold the stream until the response ends.
 */
function sse_send_headers(): void
{
    header('Content-Type: text/event-stream; charset=UTF-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Content-Security-Policy: default-src \'none\'');

    ob_end_clean();
    ob_implicit_flush(true);
}

/**
 * Write a named SSE event to the output stream and flush immediately.
 *
 * @param string $event  The SSE event name (e.g. 'update', 'close').
 * @param string $data   The event payload (typically a JSON string).
 */
function sse_emit(string $event, string $data): void
{
    echo "event: {$event}\ndata: {$data}\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}


/**
 * Write a heartbeat comment if the interval has elapsed, then flush.
 *
 * SSE comment lines (lines starting with ':') are ignored by the browser
 * event parser but keep the TCP connection alive through proxies and load
 * balancers that would otherwise time out a silent connection.
 *
 * @param int $lastHb   Reference to the Unix timestamp of the last heartbeat.
 *                      Updated in-place when a heartbeat is emitted.
 * @param int $interval Minimum seconds between heartbeats (default 20).
 */
function sse_maybe_heartbeat(int &$lastHb, int $interval = 20): void
{
    if (time() - $lastHb >= $interval) {
        echo ": heartbeat\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
        $lastHb = time();
    }
}

/**
 * Return whether the client connection is still open.
 *
 * @return bool true  = client is still connected.
 *              false = client has disconnected.
 */
function sse_connection_open(): bool
{
    return connection_aborted() === 0;
}

/**
 * Return whether the stream has exceeded its maximum allowed age.
 *
 * Streams are intentionally short-lived (default 55 s) so the client
 * reconnects before the server or any intermediate proxy would forcibly
 * close the connection at 60 s. The client must handle the 'close' event
 * and reconnect automatically.
 *
 * @param int $startTime Unix timestamp when the stream was opened.
 * @param int $maxAge    Maximum stream lifetime in seconds (default 55).
 * @return bool true when the stream should be closed.
 */
function sse_should_close(int $startTime, int $maxAge = 55): bool
{
    return time() - $startTime >= $maxAge;
}
