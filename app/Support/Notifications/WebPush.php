<?php

namespace App\Support\Notifications;

use App\Models\Notifications\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Web Push without a package (docs/oasis-pwa.md), built on PHP's OpenSSL:
 * - the message is encrypted for the browser (RFC 8291, "aes128gcm" content coding), so the push service of the
 *   browser vendor (Google, Mozilla, Apple, Microsoft) only carries ciphertext;
 * - the request is signed with Oasis' VAPID key (RFC 8292), so push services accept it and only Oasis can push to
 *   a subscription that was made with that key.
 *
 * Keys are raw P-256 values in base64url: the public key as the 65-byte uncompressed point, the private key as the
 * 32-byte scalar (`php artisan oasis:push-keys` creates a pair).
 */
final class WebPush
{
    /** Push services Oasis sends to. The endpoint comes from the browser, so it must not be an arbitrary URL (SSRF). */
    public const ALLOWED_HOSTS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com',
        '*.push.apple.com', '*.notify.windows.com', '*.push.services.mozilla.com'];

    /** DER prefix of a SubjectPublicKeyInfo for an uncompressed P-256 point (id-ecPublicKey, prime256v1). */
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** Seconds a push service keeps an undelivered message (the hint stays in the app anyway). */
    private const TTL = 86400;

    public function __construct(private readonly string $publicKey, private readonly string $privateKey, private readonly string $subject) {}

    /** The configured instance, or null while no VAPID keys are set (push is then switched off everywhere). */
    public static function fromConfig(): ?self
    {
        $public = config('oasis.push.public_key');
        $private = config('oasis.push.private_key');

        return filled($public) && filled($private) ? new self($public, $private, (string) config('oasis.push.subject')) : null;
    }

    public static function enabled(): bool
    {
        return self::fromConfig() !== null;
    }

    public static function allowedEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }
        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($parts['host'] === $allowed || (str_starts_with($allowed, '*.') && str_ends_with($parts['host'], substr($allowed, 1)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send one message. Returns false when the subscription is gone (404/410): it is then deleted.
     *
     * @param  array<string, string>  $message  title, body, url, tag – never post texts or names
     */
    public function send(PushSubscription $subscription, array $message): bool
    {
        if (! self::allowedEndpoint($subscription->endpoint)) {
            $subscription->delete();

            return false;
        }
        $body = $this->encrypt((string) json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            self::base64UrlDecode($subscription->p256dh), self::base64UrlDecode($subscription->auth));

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'vapid t='.$this->vapidToken($subscription->endpoint).', k='.$this->publicKey,
                'Content-Encoding' => 'aes128gcm',
                'TTL' => (string) self::TTL,
                'Urgency' => 'normal',
            ])->withBody($body, 'application/octet-stream')->post($subscription->endpoint);
        } catch (Throwable $failure) {
            Log::warning('Push service unreachable', ['exception' => $failure::class]);

            return true;
        }

        if (in_array($response->status(), [404, 410], true)) {
            $subscription->delete();

            return false;
        }
        if ($response->successful()) {
            $subscription->forceFill(['last_used_at' => now()])->save();
        } else {
            Log::warning('Push service rejected a message', ['status' => $response->status()]);
        }

        return true;
    }

    /**
     * RFC 8291: ECDH with a fresh key pair, HKDF with the browser's auth secret, one AES-128-GCM record.
     * Layout: salt (16) | record size (4) | key id length (1) | our public key (65) | ciphertext with tag.
     */
    public function encrypt(string $plaintext, string $browserPublicKey, string $authSecret): string
    {
        if (strlen($browserPublicKey) !== 65 || $browserPublicKey[0] !== "\x04" || strlen($authSecret) !== 16) {
            throw new RuntimeException('Invalid push subscription keys.');
        }
        $ephemeral = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($ephemeral === false) {
            throw new RuntimeException('Could not create a P-256 key.');
        }
        $serverPublicKey = self::publicPoint($ephemeral);
        $sharedSecret = openssl_pkey_derive(self::publicKeyFromPoint($browserPublicKey), $ephemeral);
        if ($sharedSecret === false) {
            throw new RuntimeException('ECDH failed.');
        }

        $ikm = hash_hkdf('sha256', $sharedSecret, 32, "WebPush: info\0".$browserPublicKey.$serverPublicKey, $authSecret);
        $salt = random_bytes(16);
        $contentKey = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // 0x02 marks the last (and only) record; no padding.
        $ciphertext = openssl_encrypt($plaintext."\x02", 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return $salt.pack('N', 4096).chr(65).$serverPublicKey.$ciphertext.$tag;
    }

    /** RFC 8292: an ES256 JWT for the push service's origin, valid for 12 hours. */
    public function vapidToken(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $header = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::base64UrlEncode((string) json_encode([
            'aud' => ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? ''),
            'exp' => now()->addHours(12)->getTimestamp(),
            'sub' => $this->subject,
        ], JSON_UNESCAPED_SLASHES));
        if (! openssl_sign($header.'.'.$claims, $der, $this->privateKeyResource(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the VAPID token.');
        }

        return $header.'.'.$claims.'.'.self::base64UrlEncode(self::derToRawSignature($der));
    }

    /** @return array{public: string, private: string} a new VAPID key pair in base64url */
    public static function generateKeys(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($key === false) {
            throw new RuntimeException('Could not create a P-256 key.');
        }

        return ['public' => self::base64UrlEncode(self::publicPoint($key)), 'private' => self::base64UrlEncode(str_pad(self::ecDetails($key)['d'], 32, "\0", STR_PAD_LEFT))];
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'), true);
    }

    /** The uncompressed point 0x04 | x | y of an EC key. */
    private static function publicPoint(\OpenSSLAsymmetricKey $key): string
    {
        $ec = self::ecDetails($key);

        return "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** @return array<string, string> raw big-endian values of an EC key: x, y and, for a private key, d */
    private static function ecDetails(\OpenSSLAsymmetricKey $key): array
    {
        $details = openssl_pkey_get_details($key);
        if ($details === false || ! isset($details['ec'])) {
            throw new RuntimeException('Not an EC key.');
        }

        return $details['ec'];
    }

    private static function publicKeyFromPoint(string $point): \OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX).$point), 64, "\n")."-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('Invalid P-256 public key.');
        }

        return $key;
    }

    /** SEC1 ECPrivateKey (RFC 5915) built from the raw scalar and the public point. */
    private function privateKeyResource(): \OpenSSLAsymmetricKey
    {
        $der = "\x30\x77\x02\x01\x01\x04\x20".self::base64UrlDecode($this->privateKey)
            ."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            ."\xa1\x44\x03\x42\x00".self::base64UrlDecode($this->publicKey);
        $key = openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n");
        if ($key === false) {
            throw new RuntimeException('Invalid VAPID private key.');
        }

        return $key;
    }

    /** OpenSSL signs ECDSA as DER SEQUENCE { INTEGER r, INTEGER s }; JWT needs r | s, 32 bytes each. */
    private static function derToRawSignature(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
        $raw = '';
        foreach ([0, 1] as $ignored) {
            $length = ord($der[$offset + 1]);
            $integer = ltrim(substr($der, $offset + 2, $length), "\0");
            $raw .= str_pad($integer, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $raw;
    }
}
