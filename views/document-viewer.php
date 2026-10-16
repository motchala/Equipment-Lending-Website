<?php
require_once __DIR__ . '/../config/session.php';

if (($_SESSION['admin'] ?? false) !== true) {
    http_response_code(403);
    exit('Access denied.');
}

$relative_path = (string)($_GET['file'] ?? '');
if (!preg_match('#^uploads/(request_letters|room_documents)/([A-Za-z0-9._ -]+)$#', $relative_path, $matches)) {
    http_response_code(404);
    exit('Document not found.');
}

$upload_directory = realpath(__DIR__ . '/../uploads/' . $matches[1]);
$file_path = realpath(__DIR__ . '/../' . $relative_path);
$expected_prefix = $upload_directory === false
    ? ''
    : rtrim($upload_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if (
    $upload_directory === false
    || $file_path === false
    || !is_file($file_path)
    || strpos($file_path, $expected_prefix) !== 0
) {
    http_response_code(404);
    exit('Document not found.');
}

$filename = basename($file_path);
$file_url = '../uploads/' . $matches[1] . '/' . rawurlencode($filename);
$file_url_json = json_encode(
    $file_url,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
$display_name = preg_replace('/^(\d{8}_\d{6}_|\d{9,}_)/', '', $filename);
$display_name_html = htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8');
$csp_nonce = base64_encode(random_bytes(16));

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'nonce-{$csp_nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self'; frame-src blob:; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $display_name_html; ?> | PupSync</title>
    <link rel="icon" href="../assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="../assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="../assets/images/apple-touch-icon.png">
    <style>
        html, body { width: 100%; height: 100%; margin: 0; overflow: hidden; }
        iframe { display: block; width: 100%; height: 100%; border: 0; }
        #status { font: 14px Arial, sans-serif; }
    </style>
</head>
<body>
    <div id="status" role="status" hidden>Unable to load this document.</div>
    <iframe id="document" title="<?php echo $display_name_html; ?>"></iframe>
    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        (async function () {
            const fileUrl = <?php echo $file_url_json; ?>;
            const status = document.getElementById('status');
            try {
                const response = await fetch(fileUrl, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('Document request failed: ' + response.status);
                const objectUrl = URL.createObjectURL(await response.blob());
                document.getElementById('document').src = objectUrl;
            } catch (error) {
                status.hidden = false;
                console.error(error);
            }
        })();
    </script>
</body>
</html>
