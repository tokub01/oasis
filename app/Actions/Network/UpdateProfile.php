<?php

namespace App\Actions\Network;

use App\Models\User;

/** Updates only editable profile fields for the authenticated owner; called after component validation. */
class UpdateProfile
{
    /** @param array{bio?: string|null, location?: string|null, avatar_color: string} $data */
    public function handle(User $user, array $data): void
    {
        $user->profile()->updateOrCreate([], array_intersect_key($data, array_flip(['bio', 'location', 'avatar_color'])));
    }
}
