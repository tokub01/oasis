<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One answer of a vote with its secret tally (`votes_count`).
 *
 * @property int $id
 * @property int $vote_id
 * @property string $label
 * @property int $position
 * @property int $votes_count
 */
class VoteOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['label', 'position'];
}
