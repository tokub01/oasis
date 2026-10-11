<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one account's encrypted private identity and authenticated public bundle for chat APIs.
 *
 * @property array<string, mixed> $locked_identity
 * @property array<string, string> $public_keys
 * @property CarbonImmutable|null $key_changed_at
 */
class ChatIdentity extends Model
{
    protected $guarded = [];

    /**
     * Decode opaque encrypted JSON and key-change timestamps.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['locked_identity' => 'array', 'public_keys' => 'array', 'key_changed_at' => 'immutable_datetime'];
    }
}
