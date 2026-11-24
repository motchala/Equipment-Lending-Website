<?php
/**
 * upload-check.php — TEMPORARY diagnostic for equipment image uploads.
 * Admin-only. Open it while signed in as admin, copy what it shows, then DELETE this file.
 */
require_once __DIR__ . '/shared/core/site-paths.php';
require_once PS_ROOT . '/config/env.php';
load_env();
require_once PS_ROOT . '/config/session.php';
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    exit('Sign in as an admin first, then open this page again.');
}
require_once PS_ROOT . '/shared/core/cloudinary.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$rows = [];
function row(string $label, bool $ok, string $detail = ''): void
{
    global $rows;
    $rows[] = [$label, $ok, $detail];
}

row('PHP version', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION . ' (needs 8.0+)');
row('cURL extension', function_exists('curl_init'), function_exists('curl_init') ? 'available' : 'missing — will use streams');
row('allow_url_fopen', (bool)ini_get('allow_url_fopen'), ini_get('allow_url_fopen') ? 'on' : 'off');
row('openssl extension', extension_loaded('openssl'), extension_loaded('openssl') ? 'available' : 'missing');
row('file_uploads', (bool)ini_get('file_uploads'), 'upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size'));
row('GD / getimagesize', function_exists('getimagesize'), function_exists('getimagesize') ? 'available' : 'missing');

$uploads = PS_ROOT . '/uploads';
row('uploads/ folder exists', is_dir($uploads), $uploads);
row('uploads/ folder writable', is_dir($uploads) && is_writable($uploads), is_dir($uploads) ? substr(sprintf('%o', fileperms($uploads)), -4) : '');

foreach (['CLOUDINARY_CLOUD_NAME', 'CLOUDINARY_API_KEY', 'CLOUDINARY_API_SECRET', 'CLOUDINARY_FOLDER'] as $k) {
    $v = cloudinary_env($k);
    row('.env ' . $k, $v !== '' || $k === 'CLOUDINARY_FOLDER', $v === '' ? 'EMPTY or missing' : ($k === 'CLOUDINARY_CLOUD_NAME' || $k === 'CLOUDINARY_FOLDER' ? $v : 'set (' . strlen($v) . ' chars)'));
}

// Outbound HTTPS in general
$out = null;
if (function_exists('curl_init')) {
    $ch = curl_init('https://api.cloudinary.com/');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_NOBODY => true]);
    curl_exec($ch);
    $out = curl_errno($ch) === 0 ? 'reachable (HTTP ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ')' : 'FAILED: ' . curl_error($ch);
    curl_close($ch);
} elseif (ini_get('allow_url_fopen')) {
    $r = @file_get_contents('https://api.cloudinary.com/', false, stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]));
    $out = $r === false ? 'FAILED: ' . (error_get_last()['message'] ?? 'unknown') : 'reachable';
}
row('Can this server reach api.cloudinary.com?', $out !== null && strpos((string)$out, 'FAILED') === false, $out ?? 'no way to test (no cURL, allow_url_fopen off)');

// Real signed upload of a 1x1 PNG, then delete it again
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$tmp = tempnam(sys_get_temp_dir(), 'pschk');
file_put_contents($tmp, $png);
$url = cloudinary_upload($tmp, 'ps-check.png');
@unlink($tmp);
if ($url !== '') {
    row('Cloudinary test upload', true, $url);
    row('Cloudinary test delete', cloudinary_delete($url), 'test image removed');
} else {
    row('Cloudinary test upload', false, cloudinary_last_error());
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Upload check</title>
<style>
body{font:15px/1.5 system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem}
td,th{padding:6px 10px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}
.ok{color:#0a7d2c;font-weight:700}.bad{color:#b00020;font-weight:700}
</style>
<h2>Equipment image upload check</h2>
<table>
<?php foreach ($rows as [$label, $ok, $detail]): ?>
<tr><td><?= htmlspecialchars($label) ?></td><td class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'OK' : 'PROBLEM' ?></td><td><?= htmlspecialchars($detail) ?></td></tr>
<?php endforeach; ?>
</table>
<p>Copy this table and send it back, then <b>delete upload-check.php</b> from the server.</p>
