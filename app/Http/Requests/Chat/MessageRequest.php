<?php

namespace App\Http\Requests\Chat;

use App\Support\Chat\CryptoPayload;
use Illuminate\Foundation\Http\FormRequest;

/** Bounds opaque message envelopes before the send action checks membership, expiry and signature. */
class MessageRequest extends FormRequest
{
    /** Conversation membership is checked under the transaction lock in the send action. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Exact allowlist rejects plaintext; ciphertext fits the Pusher event size budget.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'envelope' => ['required', 'array:v,conversation_id,key_version,sender_id,nonce,ciphertext,signature,expires_at'],
            ...CryptoPayload::headerRules('envelope'),
            'envelope.sender_id' => ['required', 'string', 'regex:/^[1-9][0-9]*$/D', 'max:20'],
            'envelope.nonce' => ['required', 'string', CryptoPayload::base64(24)],
            'envelope.ciphertext' => ['required', 'string', 'max:8000', CryptoPayload::base64(16, 6000)],
            'envelope.expires_at' => ['required', 'string', 'date_format:Y-m-d\\TH:i:s\\Z'],
        ];
    }
}
