<?php

namespace App\Http\Requests\Chat;

use App\Support\Chat\CryptoPayload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a participant's forwarded message envelope and plaintext report.
 * Conversation membership and cryptographic signature are verified in the action.
 * Used by ChatReportController.
 */
class ReportMessageRequest extends FormRequest
{
    /** Membership is verified under authorization in the controller and action. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Enforce exact envelope structure, reasonable plaintext length, and reporter reason.
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
            // A message holds at most 4000 characters (MAX_MESSAGE_LENGTH in resources/js/chat/conversation.js).
            'plaintext' => ['required', 'string', 'min:1', 'max:4000'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
