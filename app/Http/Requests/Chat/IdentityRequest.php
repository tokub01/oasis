<?php

namespace App\Http\Requests\Chat;

use App\Support\Chat\CryptoPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Accepts only encrypted lockIdentity objects and matching signed public-key bundles. */
class IdentityRequest extends FormRequest
{
    /** Session/verification middleware supplies authorization; users cannot select another identity. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Production Argon2id costs and exact keys prevent accidental private-data persistence.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'locked_identity' => ['required', 'array:version,kdf,nonce,ciphertext,publicKeys'],
            'locked_identity.version' => ['required', 'integer', CryptoPayload::integer(), 'in:1'],
            'locked_identity.kdf' => ['required', 'array:alg,opslimit,memlimit,salt'],
            'locked_identity.kdf.alg' => ['required', 'in:argon2id13'],
            // Production costs only (crypto core MODERATE): a weaker blob would be cheap to brute-force after a DB leak.
            'locked_identity.kdf.opslimit' => ['required', 'integer', CryptoPayload::integer(), 'in:3'],
            'locked_identity.kdf.memlimit' => ['required', 'integer', CryptoPayload::integer(), 'in:268435456'],
            'locked_identity.kdf.salt' => ['required', 'string', CryptoPayload::base64(16)],
            'locked_identity.nonce' => ['required', 'string', CryptoPayload::base64(24)],
            'locked_identity.ciphertext' => ['required', 'string', CryptoPayload::base64(112)],
            'locked_identity.publicKeys' => ['required', 'array:encryption,signing'],
            'locked_identity.publicKeys.encryption' => ['required', 'string', CryptoPayload::base64(32)],
            'locked_identity.publicKeys.signing' => ['required', 'string', CryptoPayload::base64(32)],
            'public_keys' => ['required', 'array:encryption,signing,binding'],
            'public_keys.encryption' => ['required', 'string', CryptoPayload::base64(32)],
            'public_keys.signing' => ['required', 'string', CryptoPayload::base64(32)],
            'public_keys.binding' => ['required', 'string', CryptoPayload::base64(64)],
        ];
    }

    /**
     * Check consistency and binding only after byte lengths have passed validation.
     *
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $bundle = $this->input('public_keys');
            $embedded = $this->input('locked_identity.publicKeys');
            if ($embedded['encryption'] !== $bundle['encryption'] || $embedded['signing'] !== $bundle['signing']
                || ! CryptoPayload::bindingValid($bundle)) {
                $validator->errors()->add('public_keys', 'Die öffentlichen Schlüssel stimmen nicht überein.');
            }
        }];
    }
}
