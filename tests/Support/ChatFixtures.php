<?php

namespace Tests\Support;

use App\Models\ChatIdentity;
use App\Models\Conversation;
use App\Models\ConversationKeyGrant;
use App\Models\ConversationMember;
use App\Models\User;
use App\Support\Chat\CryptoPayload;

/** Fresh test-only sodium keys and real signed ciphertext; no deployment identities are used. */
class ChatFixtures
{
    /** Encode public/ciphertext fixture bytes like the browser's unpadded Base64url. */
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** Produce a valid binding and locked-identity-shaped blob without running a costly browser KDF. */
    public static function identity(): array
    {
        $box = sodium_crypto_box_keypair();
        $sign = sodium_crypto_sign_keypair();
        $enc = sodium_crypto_box_publickey($box);
        $pub = sodium_crypto_sign_publickey($sign);
        $secret = sodium_crypto_sign_secretkey($sign);
        $bundle = ['encryption' => self::encode($enc), 'signing' => self::encode($pub),
            'binding' => self::encode(sodium_crypto_sign_detached(CryptoPayload::canonical('oasis-chat/key-binding/v1', [$enc, $pub]), $secret))];

        return ['secret' => $secret, 'public_keys' => $bundle, 'locked_identity' => [
            'version' => 1, 'kdf' => ['alg' => 'argon2id13', 'opslimit' => 3, 'memlimit' => 268435456,
                'salt' => self::encode(random_bytes(16))],
            'nonce' => self::encode(random_bytes(24)), 'ciphertext' => self::encode(random_bytes(112)),
            'publicKeys' => ['encryption' => $bundle['encryption'], 'signing' => $bundle['signing']],
        ]];
    }

    /** Create a chat-ready verified account while retaining the signing secret in test memory only. */
    public static function user(): array
    {
        $user = User::factory()->create();
        $identity = self::identity();
        ChatIdentity::create(['user_id' => $user->id, 'locked_identity' => $identity['locked_identity'],
            'public_keys' => $identity['public_keys']]);

        return [$user, $identity];
    }

    /** Build a signed sealed key grant, including the recipient public key in authenticated bytes. */
    public static function grant(Conversation $conversation, User $owner, array $ownerIdentity, User $recipient): array
    {
        $keys = ChatIdentity::where('user_id', $recipient->id)->firstOrFail()->public_keys;
        $wrapped = sodium_crypto_box_seal(str_repeat('k', 32), CryptoPayload::decode($keys['encryption']));
        $grant = ['v' => 1, 'conversation_id' => (string) $conversation->id, 'key_version' => $conversation->key_version,
            'recipient_id' => (string) $recipient->id, 'granter_id' => (string) $owner->id, 'wrapped_key' => self::encode($wrapped)];
        $bytes = CryptoPayload::canonical('oasis-chat/key-grant/v1', ['1', $grant['conversation_id'],
            (string) $grant['key_version'], $grant['recipient_id'], $grant['granter_id'],
            CryptoPayload::decode($keys['encryption']), $wrapped]);

        return [...$grant, 'signature' => self::encode(sodium_crypto_sign_detached($bytes, $ownerIdentity['secret']))];
    }

    /** Construct an active group with grants for tests focusing on messages rather than setup. */
    public static function conversation(User $owner, array $ownerIdentity, array $members): Conversation
    {
        $conversation = Conversation::create(['type' => 'group', 'created_by' => $owner->id, 'lifetime_days' => 7,
            'key_version' => 1, 'rotation_required' => false]);
        foreach ([$owner, ...$members] as $user) {
            ConversationMember::create(['conversation_id' => $conversation->id, 'user_id' => $user->id,
                'role' => $user->id === $owner->id ? 'owner' : 'member', 'joined_at' => now()->subMinute(), 'accepted_at' => now()->subMinute()]);
            $grant = self::grant($conversation, $owner, $ownerIdentity, $user);
            ConversationKeyGrant::create(['conversation_id' => $conversation->id, 'key_version' => 1,
                'recipient_id' => $user->id, 'granter_id' => $owner->id, 'grant' => $grant]);
        }

        return $conversation;
    }

    /** Encrypt and sign actual text using the same canonical envelope domains as the JS core. */
    public static function envelope(Conversation $conversation, User $sender, array $identity, ?string $expiry = null): array
    {
        $expiry ??= now()->addDays($conversation->lifetime_days)->utc()->format('Y-m-d\\TH:i:s\\Z');
        $envelope = ['v' => 1, 'conversation_id' => (string) $conversation->id, 'key_version' => $conversation->key_version,
            'sender_id' => (string) $sender->id, 'expires_at' => $expiry];
        $ad = CryptoPayload::canonical('oasis-chat/message-ad/v1', ['1', $envelope['conversation_id'],
            (string) $envelope['key_version'], $envelope['sender_id'], (string) strtotime($expiry)]);
        $nonce = random_bytes(24);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt('private test text', $ad, $nonce, str_repeat('k', 32));
        $bytes = CryptoPayload::canonical('oasis-chat/message-signature/v1', [$nonce, $ciphertext, $ad]);

        return [...$envelope, 'nonce' => self::encode($nonce), 'ciphertext' => self::encode($ciphertext),
            'signature' => self::encode(sodium_crypto_sign_detached($bytes, $identity['secret']))];
    }
}
