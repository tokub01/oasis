<?php

namespace App\Http\Requests\Chat;

use App\Support\Chat\CryptoPayload;
use Illuminate\Foundation\Http\FormRequest;

/** Complete owner-signed recipient batches for one pending conversation key version. */
class GrantRequest extends FormRequest
{
    /** Owner authorization is checked under the conversation lock by the rotation action. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Each object uses exactly the crypto core's createKeyGrant format.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'key_version' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'grants' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'grants.*' => ['required', 'array:v,conversation_id,key_version,recipient_id,granter_id,wrapped_key,signature'],
            ...CryptoPayload::headerRules('grants.*'),
            'grants.*.recipient_id' => ['required', 'string', 'regex:/^[1-9][0-9]*$/D', 'max:20', 'distinct:strict'],
            'grants.*.granter_id' => ['required', 'string', 'regex:/^[1-9][0-9]*$/D', 'max:20'],
            'grants.*.wrapped_key' => ['required', 'string', CryptoPayload::base64(80)],
        ];
    }
}
