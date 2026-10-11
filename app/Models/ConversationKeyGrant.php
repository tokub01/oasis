<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An owner-signed sealed conversation key for exactly one recipient and key version.
 *
 * @property array<string, mixed> $grant
 */
class ConversationKeyGrant extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * Preserve the signed crypto core object without adding model fields to it.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['grant' => 'array'];
    }
}
