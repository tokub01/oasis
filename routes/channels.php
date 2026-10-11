<?php

use App\Models\ConversationMember;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// A private channel reveals no presence list. Left members lose authorization on their next subscription.
Broadcast::channel('conversation.{id}', fn (User $user, string $id) => $user->hasVerifiedEmail()
    && ConversationMember::where('conversation_id', $id)->where('user_id', $user->id)->whereNull('left_at')->exists());
