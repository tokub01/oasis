<?php

namespace App\Actions\Account;

use App\Actions\Network\ManageOasis;
use App\Models\ContentCheck;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an account completely (Art. 17 GDPR).
 *
 * - Refused while a paid membership is running and not cancelled: deleting would leave Mollie charging an account
 *   that no longer exists. The member cancels first (the membership then runs out) and deletes afterwards.
 * - Oases where the account is the only host get a new host (longest member) or are closed (stage 9, F8).
 * - Database rows cascade from `users`; uploaded images are removed from the media disk after the commit, so a
 *   failed deletion never loses files of a still existing account.
 * - The cascade fires no model events, so the automated checks of the deleted texts are removed explicitly (except
 *   those a moderation case is based on, see ContentCheck).
 */
class DeleteAccount
{
    public function __construct(private readonly ManageOasis $oases) {}

    /** @throws ValidationException when a paid, not cancelled membership is still running */
    public function handle(User $user): void
    {
        $subscription = $user->subscription();
        if ($subscription !== null && $subscription->valid() && ! $subscription->canceled()) {
            throw ValidationException::withMessages([
                'confirm' => 'Du hast noch eine laufende Mitgliedschaft. Kündige sie zuerst – danach kannst du dein Konto löschen.',
            ]);
        }

        $postIds = Post::where('user_id', $user->id)->pluck('id')->all();
        DB::transaction(function () use ($user): void {
            $this->oases->handOverBeforeDeletion($user);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
            ContentCheck::orphaned()->delete();
        });

        $disk = Storage::disk(config('oasis.media_disk'));
        foreach ($postIds as $postId) {
            $disk->deleteDirectory('network/posts/'.$postId);
        }
    }
}
