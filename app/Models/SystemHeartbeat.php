<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Last success and failure of one recurring operation (docs/oasis-operations.md): scheduler, mollie-sync, backup,
 * backup-verify and the alert throttles. Written by the operations themselves, read by the health check.
 *
 * @property string $key
 * @property CarbonInterface|null $last_success_at
 * @property CarbonInterface|null $last_failure_at
 * @property array<string, mixed>|null $details
 */
class SystemHeartbeat extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public const CREATED_AT = null;

    protected $fillable = ['key', 'last_success_at', 'last_failure_at', 'details'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_success_at' => 'datetime', 'last_failure_at' => 'datetime', 'details' => 'array'];
    }

    /** @param  array<string, mixed>  $details  counts and sizes only, never personal data */
    public static function success(string $key, array $details = []): void
    {
        static::updateOrCreate(['key' => $key], ['last_success_at' => now(), 'details' => $details]);
    }

    /** @param  array<string, mixed>  $details */
    public static function failure(string $key, array $details = []): void
    {
        static::updateOrCreate(['key' => $key], ['last_failure_at' => now(), 'details' => $details]);
    }

    public static function lastSuccess(string $key): ?CarbonInterface
    {
        return static::find($key)?->last_success_at;
    }
}
