<?php

namespace App\Models\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A browser that receives push hints (docs/oasis-pwa.md). Created by PushSubscriptionController, removed by the member
 * (Hinweise einstellen), by the browser (unsubscribe) or by App\Support\Notifications\WebPush when the push service
 * reports the address as gone.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint push service address, https on an allowed host
 * @property string $endpoint_hash sha256 of the endpoint
 * @property string $p256dh browser public key (base64url, uncompressed P-256 point)
 * @property string $auth browser auth secret (base64url, 16 bytes)
 * @property string $device coarse label such as "Chrome auf Android"
 * @property Carbon|null $last_used_at
 * @property Carbon $created_at
 */
class PushSubscription extends Model
{
    protected $guarded = [];

    protected $hidden = ['endpoint', 'endpoint_hash', 'p256dh', 'auth'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
