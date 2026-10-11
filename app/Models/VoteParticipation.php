<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records that a member took part in a vote – never what they chose.
 *
 * @property int $vote_id
 * @property int $user_id
 */
class VoteParticipation extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['vote_id', 'user_id', 'voted_on'];
}
