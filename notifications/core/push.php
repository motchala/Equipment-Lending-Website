<?php
declare(strict_types=1);

/**
 * notifications/core/push.php
 *
 * PUPSync Web Push — VAPID-based, no external SDK.
 *
 * Public API:
 *   ps_push_ensure_table(mysqli $conn): void
 *   ps_push_ensure_columns(mysqli $conn): void
 *   ps_push_send(mysqli, audience, user_id, family, title, body, url): void
 *
 * Credentials from .env:
 *   VAPID_PUBLIC_KEY   — URL-safe base64, uncompressed P-256 point
 *   VAPID_PRIVATE_KEY  — URL-safe base64, raw 32-byte scalar
 *   VAPID_SUBJECT      — mailto: or https: contact
 *
 * Failures are silently logged — push never interrupts the main action.
 */

if (!function_exists('ps_push_ensure_table')) {
    function ps_push_ensure_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $conn->query(
                "CREATE TABLE IF NOT EXISTS `tbl_push_subscriptions` (
                    `id`         int(11)      NOT NULL AUTO_INCREMENT,
                    `audience`   varchar(10)  NOT NULL,
                    `user_id`    varchar(64)  NOT NULL,
                    `player_id`  text         NOT NULL,
                    `auth_key`   varchar(255) DEFAULT NULL,
                    `p256dh_key` varchar(255) DEFAULT NULL,
                    `created_at` datetime     DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_push_sub` (`audience`, `user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } catch (Throwable $e) {
            error_log('[ps_push] ensure_table: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ps_push_ensure_columns')) {
    function ps_push_ensure_columns(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $cols = [];
            $res  = $conn->query("SHOW COLUMNS FROM tbl_push_subscriptions");
            if ($res) {
                while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
            }
            if (!in_array('auth_key', $cols, true)) {
                $conn->query("ALTER TABLE tbl_push_subscriptions ADD COLUMN `auth_key` varchar(255) DEFAULT NULL");
            }
            if (!in_array('p256dh_key', $cols, true)) {
                $conn->query("ALTER TABLE tbl_push_subscriptions ADD COLUMN `p256dh_key` varchar(255) DEFAULT NULL");
            }
            // player_id needs to hold full endpoint URLs, make it text if it's varchar(64)
            foreach ($cols as $col) {
                if ($col === 'player_id') {
                    $conn->query("ALTER TABLE tbl_push_subscriptions MODIFY COLUMN `player_id` text NOT NULL");
                    break;
                }
            }
        } catch (Throwable $e) {
            error_log('[ps_push] ensure_columns: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ps_push_get_subscription')) {
    function ps_push_get_subscription(mysqli $conn, string $audience, string $user_id): ?array
    {
        try {
            ps_push_ensure_table($conn);
            $st = $conn->prepare(
                'SELECT player_id AS endpoint, auth_key, p256dh_key
                   FROM tbl_push_subscriptions
                  WHERE audience = ? AND user_id = ? LIMIT 1'
            );
            if (!$st) return null;
            $st->bind_param('ss', $audience, $user_id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row || empty($row['endpoint']) || empty($row['auth_key']) || empty($row['p256dh_key'])) {
                return null;
            }
            return $row;
        } catch (Throwable $e) {
            error_log('[ps_push] get_subscription: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('ps_push_send')) {
    function ps_push_send(
        mysqli $conn,
        string $audience,
        string $user_id,
        string $family,
        string $title,
        string $body,
        string $url
    ): void {
        try {
            if (!notif_family_enabled($conn, $audience, $user_id, $family)) return;

            $sub = ps_push_get_subscription($conn, $audience, $user_id);
            if ($sub === null) return;

            require_once __DIR__ . '/../../config/env.php';
            load_env();

            $vapidPub     = trim((string)($_ENV['VAPID_PUBLIC_KEY']  ?? ''));
            $vapidPriv    = trim((string)($_ENV['VAPID_PRIVATE_KEY'] ?? ''));
            $vapidSubject = trim((string)($_ENV['VAPID_SUBJECT']     ?? ''));

            if ($vapidPub === '' || $vapidPriv === '') {
                error_log('[ps_push] VAPID keys missing from .env');
                return;
            }

            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base     = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
            $full_url = $scheme . '://' . $host . $base . '/' . ltrim($url, '/');

            $payload = json_encode([
                'title' => $title,
                'body'  => $body,
                'url'   => $full_url,
                'icon'  => $scheme . '://' . $host . $base . '/assets/images/logo.png',
            ]);

            ps_push_send_vapid($sub['endpoint'], $sub['auth_key'], $sub['p256dh_key'], $payload, $vapidPub, $vapidPriv, $vapidSubject);

        } catch (Throwable $e) {
            error_log('[ps_push] send: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ps_push_send_vapid')) {
    function ps_push_send_vapid(
        string $endpoint,
        string $authKey,
        string $p256dhKey,
        string $payload,
        string $vapidPub,
        string $vapidPriv,
        string $vapidSubject
    ): void {
        // Parse the push service origin from the endpoint
        $parts  = parse_url($endpoint);
        $origin = $parts['scheme'] . '://' . $parts['host'];

        // Build VAPID JWT header + claims
        $exp = time() + 12 * 3600;
        $vapidHeader = ps_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $vapidClaims = ps_base64url_encode(json_encode([
            'aud' => $origin,
            'exp' => $exp,
            'sub' => $vapidSubject,
        ]));
        $sigInput = $vapidHeader . '.' . $vapidClaims;

        // Sign with ES256 (ECDSA P-256 SHA-256)
        $privDer  = ps_vapid_private_to_der($vapidPriv, $vapidPub);
        $privKey  = openssl_pkey_get_private($privDer);
        if (!$privKey) {
            error_log('[ps_push] Could not load VAPID private key');
            return;
        }
        openssl_sign($sigInput, $rawSig, $privKey, OPENSSL_ALGO_SHA256);

        // Convert DER signature to raw r||s (64 bytes)
        $sig = ps_der_sig_to_raw($rawSig);
        $vapidJwt = $sigInput . '.' . ps_base64url_encode($sig);

        // Encrypt the payload using Web Push encryption (RFC 8291)
        $encrypted = ps_push_encrypt($payload, $authKey, $p256dhKey);
        if ($encrypted === null) {
            error_log('[ps_push] Payload encryption failed');
            return;
        }

        $headers =
            "Content-Type: application/octet-stream\r\n" .
            "Content-Encoding: aes128gcm\r\n" .
            "Authorization: vapid t={$vapidJwt}, k={$vapidPub}\r\n" .
            "TTL: 86400\r\n" .
            "Content-Length: " . strlen($encrypted) . "\r\n" .
            "User-Agent: PUPSync/1.0\r\n";

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => $headers,
                'content'       => $encrypted,
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => false],
        ]);

        $result = @file_get_contents($endpoint, false, $ctx);
        if ($result === false) {
            error_log('[ps_push] HTTP call to push endpoint failed: ' . $endpoint);
        }
    }
}

if (!function_exists('ps_base64url_encode')) {
    function ps_base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('ps_base64url_decode')) {
    function ps_base64url_decode(string $data): string
    {
        $pad  = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

if (!function_exists('ps_vapid_private_to_der')) {
    /**
     * Build an OpenSSL private key object from raw VAPID key bytes.
     * Uses PKCS#8 DER format which OpenSSL 3.x accepts cleanly.
     * Returns a PEM string.
     */
    function ps_vapid_private_to_der(string $vapidPrivB64, string $vapidPubB64): string
    {
        $priv = ps_base64url_decode($vapidPrivB64);
        $pub  = ps_base64url_decode($vapidPubB64);

        $priv = str_pad($priv, 32, chr(0), STR_PAD_LEFT);

        // Build ECPrivateKey (RFC 5915) DER
        // SEQUENCE {
        //   INTEGER 1,
        //   OCTET STRING (private key, 32 bytes),
        //   [1] BIT STRING (public key, 65 bytes uncompressed)
        // }
        $privOctet  = chr(0x04) . chr(0x20) . $priv;
        $pubBitStr  = chr(0xa1) . chr(0x43) . chr(0x03) . chr(0x41) . chr(0x00) . $pub;
        $version    = chr(0x02) . chr(0x01) . chr(0x01);
        $inner      = $version . $privOctet . $pubBitStr;
        $sec1       = chr(0x30) . chr(strlen($inner)) . $inner;

        $pem = "-----BEGIN EC PRIVATE KEY-----\n" .
               chunk_split(base64_encode($sec1), 64, "\n") .
               "-----END EC PRIVATE KEY-----\n";

        // OpenSSL 3.x is strict — verify it loads before returning
        while (openssl_error_string() !== false) {}
        $key = @openssl_pkey_get_private($pem);
        if ($key) {
            return $pem;
        }

        // Fallback: build PKCS#8 format which OpenSSL 3.x always accepts
        // OID for prime256v1: 1.2.840.10045.3.1.7 (8 bytes after tag+len)
        $oidBytes   = "\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $oidSeq     = "\x06\x08" . $oidBytes;
        $ecOid      = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"; // id-ecPublicKey
        $algSeq     = chr(0x30) . chr(strlen($ecOid) + strlen($oidSeq) + 2) .
                      $ecOid .
                      chr(0x06) . chr(strlen($oidBytes)) . $oidBytes;

        $privOctetStr = chr(0x04) . chr(strlen($priv)) . $priv;
        $innerSeq     = chr(0x02) . chr(0x01) . chr(0x01) . $privOctetStr;
        $ecPriv       = chr(0x30) . chr(strlen($innerSeq)) . $innerSeq;
        $privStr      = chr(0x04) . chr(strlen($ecPriv)) . $ecPriv;

        $pkcs8inner = chr(0x02) . chr(0x01) . chr(0x00) .
                      $algSeq .
                      $privStr;
        $pkcs8      = chr(0x30) . chr(strlen($pkcs8inner)) . $pkcs8inner;

        return "-----BEGIN PRIVATE KEY-----\n" .
               chunk_split(base64_encode($pkcs8), 64, "\n") .
               "-----END PRIVATE KEY-----\n";
    }
}

if (!function_exists('ps_der_sig_to_raw')) {
    /**
     * Convert OpenSSL DER-encoded ECDSA signature to raw r||s (64 bytes).
     */
    function ps_der_sig_to_raw(string $der): string
    {
        // SEQUENCE { INTEGER r, INTEGER s }
        $offset = 2; // skip SEQUENCE tag + length
        // r
        $offset++;   // INTEGER tag
        $rLen = ord($der[$offset++]);
        $r    = substr($der, $offset, $rLen);
        $offset += $rLen;
        // s
        $offset++;   // INTEGER tag
        $sLen = ord($der[$offset++]);
        $s    = substr($der, $offset, $sLen);

        // Strip leading zero padding and pad to 32 bytes
        $r = str_pad(ltrim($r, chr(0)), 32, chr(0), STR_PAD_LEFT);
        $s = str_pad(ltrim($s, chr(0)), 32, chr(0), STR_PAD_LEFT);

        return $r . $s;
    }
}

if (!function_exists('ps_push_encrypt')) {
    /**
     * Encrypt a push message payload using RFC 8291 (aes128gcm).
     * Returns the encrypted binary string or null on failure.
     */
    function ps_push_encrypt(string $payload, string $authB64, string $p256dhB64): ?string
    {
        try {
            $authSecret    = ps_base64url_decode($authB64);
            $receiverPub   = ps_base64url_decode($p256dhB64);

            // Generate a sender (ephemeral) EC key pair
            $keyConfig = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
            if (defined('OPENSSL_CNF') && OPENSSL_CNF !== '') {
                $keyConfig['config'] = OPENSSL_CNF;
            }
            while (openssl_error_string() !== false) {}
            $senderKey = openssl_pkey_new($keyConfig);
            if (!$senderKey) return null;

            $senderDetails  = openssl_pkey_get_details($senderKey);
            $senderPubX     = str_pad($senderDetails['ec']['x'], 32, chr(0), STR_PAD_LEFT);
            $senderPubY     = str_pad($senderDetails['ec']['y'], 32, chr(0), STR_PAD_LEFT);
            $senderPubRaw   = chr(4) . $senderPubX . $senderPubY; // uncompressed

            // Reconstruct the receiver's public key as an OpenSSL key object
            // The receiver's public key is the browser's p256dh (65 bytes, uncompressed)
            $receiverPem = ps_ec_pub_to_pem($receiverPub);
            $receiverKey = openssl_pkey_get_public($receiverPem);
            if (!$receiverKey) return null;

            // ECDH shared secret using openssl_pkey_derive (PHP 8.1+)
            $sharedSecret = openssl_pkey_derive($receiverKey, $senderKey);
            if ($sharedSecret === false) {
                error_log('[ps_push_encrypt] ECDH key derivation failed: ' . openssl_error_string());
                return null;
            }

            // Salt (16 random bytes)
            $salt = random_bytes(16);

            // RFC 8291 key derivation
            $keyInfo    = 'WebPush: info' . chr(0) . $receiverPub . $senderPubRaw;
            $prk        = hash_hmac('sha256', $sharedSecret, $authSecret, true);
            // This is a simplified HKDF expand
            $ikm        = hash_hmac('sha256', $prk, $keyInfo . chr(1), true);

            $cekInfo    = 'Content-Encoding: aes128gcm' . chr(0);
            $cekPrk     = hash_hmac('sha256', $ikm, $salt, true);
            $cek        = substr(hash_hmac('sha256', $cekPrk, $cekInfo . chr(1), true), 0, 16);

            $nonceInfo  = 'Content-Encoding: nonce' . chr(0);
            $nonce      = substr(hash_hmac('sha256', $cekPrk, $nonceInfo . chr(1), true), 0, 12);

            // Pad payload (RFC 8291 §4): payload + 0x02 delimiter
            $paddedPayload = $payload . chr(0x02);

            // AES-128-GCM encrypt
            $ciphertext = openssl_encrypt($paddedPayload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
            if ($ciphertext === false) return null;

            // aes128gcm content coding header (RFC 8188):
            // salt (16) + rs (uint32 big-endian = 4096) + keyid_len (1) + keyid (senderPubRaw, 65 bytes)
            $header =
                $salt .
                pack('N', 4096) .
                chr(strlen($senderPubRaw)) .
                $senderPubRaw;

            return $header . $ciphertext . $tag;

        } catch (Throwable $e) {
            error_log('[ps_push_encrypt] ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('ps_ec_pub_to_pem')) {
    /**
     * Wrap a raw 65-byte uncompressed P-256 public key in a PEM SubjectPublicKeyInfo.
     */
    function ps_ec_pub_to_pem(string $rawPub): string
    {
        // SubjectPublicKeyInfo DER for P-256
        $oid = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $bitString = chr(0x03) . chr(strlen($rawPub) + 1) . chr(0x00) . $rawPub;
        $spki      = chr(0x30) . chr(strlen($oid) + strlen($bitString)) . $oid . $bitString;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}
