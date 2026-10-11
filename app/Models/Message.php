<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ciphertext-only message history; the daily model:prune schedule deletes expired rows.
 *
 * @property array<string, mixed> $envelope
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $expires_at
 */
class Message extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Decode the signed envelope and its unchanged expiry instant.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['envelope' => 'array', 'expires_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /**
     * Expired ciphertext is never useful to the chat API and must be deleted.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    /**
     * API history exposes no sender profile or unsigned replacement envelope fields.
     *
     * @return array<string, mixed>
     */
    public function apiData(): array
    {
        return ['id' => (string) $this->id, 'envelope' => $this->envelope,
            'created_at' => $this->created_at->toISOString(), 'expires_at' => $this->expires_at->toISOString()];
    }
}
