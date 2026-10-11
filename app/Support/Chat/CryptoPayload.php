<?php

namespace App\Support\Chat;

use Closure;

/** Validates public crypto objects and reproduces the browser core's canonical signature encoding. */
class CryptoPayload
{
    /** JSON crypto versions must be numbers, as the browser rejects string versions. */
    public static function integer(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value)) {
                $fail('Das verschlüsselte Datenformat erwartet eine ganze Zahl.');
            }
        };
    }

    /** Reject noncanonical Base64url and check decoded size without ever decrypting contents. */
    public static function base64(int $min, ?int $max = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($min, $max): void {
            $bytes = is_string($value) ? self::decode($value) : false;
            if ($bytes === false || strlen($bytes) < $min || strlen($bytes) > ($max ?? $min)) {
                $fail('Das verschlüsselte Datenformat ist ungültig.');
            }
        };
    }

    /**
     * Strict, unpadded URL-safe Base64 compatible with libsodium URLSAFE_NO_PADDING.
     *
     * @return non-empty-string|false
     */
    public static function decode(string $value): string|false
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
            return false;
        }
        $bytes = base64_decode(strtr($value, '-_', '+/'), true);
        if ($bytes === false || $bytes === '' || rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=') !== $value) {
            return false;
        }

        return $bytes;
    }

    /**
     * Decode bytes whose format/length was already checked by FormRequest or came from a validated identity.
     *
     * @return non-empty-string
     */
    private static function validatedBytes(string $value): string
    {
        $bytes = self::decode($value);
        assert($bytes !== false);

        return $bytes;
    }

    /**
     * Four-byte big-endian length-prefix each field, including the domain separation label.
     *
     * @param  list<string>  $fields
     */
    public static function canonical(string $label, array $fields): string
    {
        return implode('', array_map(fn (string $field) => pack('N', strlen($field)).$field, [$label, ...$fields]));
    }

    /**
     * Verify a structurally validated public identity bundle without a private key.
     *
     * @param  array<string, string>  $bundle
     */
    public static function bindingValid(array $bundle): bool
    {
        return sodium_crypto_sign_verify_detached(self::decode($bundle['binding']),
            self::canonical('oasis-chat/key-binding/v1', [self::decode($bundle['encryption']), self::decode($bundle['signing'])]),
            self::decode($bundle['signing']));
    }

    /**
     * Verify that the owner's signature binds this grant to this recipient's current encryption key.
     *
     * @param  array<string, mixed>  $grant
     * @param  array<string, string>  $ownerKeys
     * @param  array<string, string>  $recipientKeys
     */
    public static function grantValid(array $grant, array $ownerKeys, array $recipientKeys): bool
    {
        return sodium_crypto_sign_verify_detached(self::decode($grant['signature']),
            self::canonical('oasis-chat/key-grant/v1', [(string) $grant['v'], $grant['conversation_id'],
                (string) $grant['key_version'], $grant['recipient_id'], $grant['granter_id'],
                self::decode($recipientKeys['encryption']), self::decode($grant['wrapped_key'])]),
            self::decode($ownerKeys['signing']));
    }

    /**
     * Verify message authenticity without opening the ciphertext. Expiry is signed as Unix seconds.
     *
     * @param  array<string, mixed>  $envelope
     * @param  array<string, string>  $senderKeys
     */
    public static function messageValid(array $envelope, array $senderKeys): bool
    {
        $ad = self::canonical('oasis-chat/message-ad/v1', [(string) $envelope['v'], $envelope['conversation_id'],
            (string) $envelope['key_version'], $envelope['sender_id'], (string) strtotime($envelope['expires_at'])]);

        return sodium_crypto_sign_verify_detached(self::decode($envelope['signature']),
            self::canonical('oasis-chat/message-signature/v1', [self::decode($envelope['nonce']), self::decode($envelope['ciphertext']), $ad]),
            self::decode($senderKeys['signing']));
    }

    /**
     * Shared rules for signed envelope or grant headers; IDs must already be normalized strings.
     *
     * @return array<string, list<mixed>>
     */
    public static function headerRules(string $prefix): array
    {
        return [
            "$prefix.v" => ['required', 'integer', self::integer(), 'in:1'],
            "$prefix.conversation_id" => ['required', 'string', 'regex:/^[1-9][0-9]*$/D', 'max:20'],
            "$prefix.key_version" => ['required', 'integer', self::integer(), 'min:1', 'max:4294967295'],
            "$prefix.signature" => ['required', 'string', self::base64(64)],
        ];
    }
}
