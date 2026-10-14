<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A unidirectional mute of an account.
 *
 * Nur für den Betrachter: Tropfen der stummgeschalteten Person verschwinden aus
 * der Quelle, Entdecken, Oasen-Listen und Antworten. Ihr Profil bleibt aufrufbar.
 * Die stummgeschaltete Person wird nicht benachrichtigt und merkt nichts.
 *
 * @property int $id
 * @property int $user_id
 * @property int $muted_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $muter
 * @property User $muted
 */
class Mute extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'muted_user_id',
    ];

    /**
     * The account that muted the other account.
     *
     * @return BelongsTo<User, $this>
     */
    public function muter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The account that has been muted.
     *
     * @return BelongsTo<User, $this>
     */
    public function muted(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_user_id');
    }
}
