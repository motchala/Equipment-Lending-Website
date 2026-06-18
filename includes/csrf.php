<?php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? null;

    // Fallback: JSON-body requests carry it in a header instead of $_POST
    if ($token === null) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? null;
    }

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token))
    ) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Invalid or expired session. Please refresh the page and try again.']);
        exit;
    }
}