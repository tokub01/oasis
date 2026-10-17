<?php

namespace App\Models;

use App\Actions\Notifications\SendHint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A restriction ("Einschränken") of one account by another. Quieter than blocking: the restricted person notices
 * nothing and can still read and answer. Their replies under the member's Tropfen are seen only by themselves, and the
 * member gets no hints from them ({@see User::hidesReplyFrom()}, {@see SendHint}).
 *
 * @property int $id
 * @property int $user_id
 * @property int $restricted_user_id
 * @property Carbon $created_at
 * @property User $restricted
 */
class Restriction extends Model
{
    protected $fillable = ['user_id', 'restricted_user_id'];

    /** @return BelongsTo<User, $this> */
    public function restricted(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restricted_user_id');
    }
}
