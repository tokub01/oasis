<?php

namespace App\Support\Operations;

use RuntimeException;

/**
 * Compresses and encrypts backups with libsodium's secretstream (XChaCha20-Poly1305), chunk by chunk, so files of any
 * size are handled with constant memory and any change to the file is detected on decryption. The key is
 * `oasis.operations.backup_key` (base64, 32 bytes), deliberately separate from APP_KEY: a backup must stay readable
 * when the server and its .env are lost. Keep the key outside the server, e.g. in a password manager.
 *
 * File format: "OASISBK1", the 24-byte stream header, then chunks of [4-byte big-endian length][ciphertext].
 */
final class BackupCipher
{
    private const MAGIC = 'OASISBK1';

    private const CHUNK = 1 << 20;

    public static function generateKey(): string
    {
        return base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen());
    }

    /**
     * @param  resource  $plain  read from the start
     * @param  resource  $out
     */
    public static function encrypt($plain, $out): void
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push(self::key());
        fwrite($out, self::MAGIC.$header);
        $deflate = deflate_init(ZLIB_ENCODING_GZIP);
        if ($deflate === false) {
            throw new RuntimeException('Komprimierung ist nicht verfügbar.');
        }
        // At least one chunk, and the last one always carries the FINAL tag – also for an empty input.
        do {
            $data = (string) fread($plain, self::CHUNK);
            $last = feof($plain);
            $compressed = (string) deflate_add($deflate, $data, $last ? ZLIB_FINISH : ZLIB_NO_FLUSH);
            $tag = $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $compressed, '', $tag);
            fwrite($out, pack('N', strlen($cipher)).$cipher);
        } while (! $last);
    }

    /**
     * @param  resource  $encrypted  read from the start
     * @param  resource  $out  receives the plain dump
     *
     * @throws RuntimeException when the key is wrong or the file was changed or cut off
     */
    public static function decrypt($encrypted, $out): void
    {
        $head = (string) fread($encrypted, strlen(self::MAGIC) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if (! str_starts_with($head, self::MAGIC)) {
            throw new RuntimeException('Das ist keine Oasis-Sicherung.');
        }
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($head, strlen(self::MAGIC)), self::key());
        $inflate = inflate_init(ZLIB_ENCODING_GZIP);
        if ($inflate === false) {
            throw new RuntimeException('Entpacken ist nicht verfügbar.');
        }
        $finished = false;
        while (! $finished) {
            $length = fread($encrypted, 4);
            if ($length === false || strlen($length) !== 4) {
                throw new RuntimeException('Die Sicherung ist abgeschnitten.');
            }
            $size = unpack('N', $length);
            $cipher = $size === false ? '' : (string) fread($encrypted, $size[1]);
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
            if ($result === false) {
                throw new RuntimeException('Die Sicherung lässt sich nicht entschlüsseln: falscher Schlüssel oder veränderte Datei.');
            }
            [$compressed, $tag] = $result;
            fwrite($out, (string) inflate_add($inflate, $compressed));
            $finished = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }
    }

    private static function key(): string
    {
        $key = base64_decode((string) config('oasis.operations.backup_key'), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('OASIS_BACKUP_KEY fehlt oder ist ungültig. Einen erzeugt `php artisan oasis:backup-key`.');
        }

        return $key;
    }
}
