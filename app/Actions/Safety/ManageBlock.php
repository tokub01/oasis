<?php

namespace App\Actions\Safety;

use App\Models\Block;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages account blocks.
 *
 * Wer blockiert, und wer blockiert wird, sehen gegenseitig keine Tropfen, Antworten
 * und Profile mehr (404). Bestehende Follows in beide Richtungen werden beim Blockieren
 * unwiderruflich entfernt. Die blockierte Person wird nicht benachrichtigt.
 */
class ManageBlock
{
    /**
     * Block an account and remove existing follows in both directions.
     *
     * @throws ValidationException
     */
    public function block(User $blocker, User $target): Block
    {
        if ($blocker->id === $target->id) {
            throw ValidationException::withMessages(['block' => 'Du kannst dich nicht selbst blockieren.']);
        }

        return DB::transaction(function () use ($blocker, $target): Block {
            // Remove follows in both directions (blocker -> target and target -> blocker)
            Follow::query()
                ->where('followable_type', (new User)->getMorphClass())
                ->where(function ($query) use ($blocker, $target) {
                    $query->where(function ($sub) use ($blocker, $target) {
                        $sub->where('follower_id', $blocker->id)
                            ->where('followable_id', $target->id);
                    })->orWhere(function ($sub) use ($blocker, $target) {
                        $sub->where('follower_id', $target->id)
                            ->where('followable_id', $blocker->id);
                    });
                })->delete();

            $block = Block::firstOrCreate([
                'user_id' => $blocker->id,
                'blocked_user_id' => $target->id,
            ]);

            $blocker->clearSafetyCache();
            $target->clearSafetyCache();

            return $block;
        });
    }

    /**
     * Lift an existing block on an account.
     */
    public function unblock(User $blocker, User $target): void
    {
        Block::query()
            ->where('user_id', $blocker->id)
            ->where('blocked_user_id', $target->id)
            ->delete();

        $blocker->clearSafetyCache();
        $target->clearSafetyCache();
    }
}
