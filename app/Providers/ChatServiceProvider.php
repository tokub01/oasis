<?php

namespace App\Providers;

use App\Actions\Chat\ConversationKeys;
use App\Http\Middleware\ChatResponse;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Registers stage 3 chat rate limits, CSRF-protected channel auth and account deletion key invalidation. */
class ChatServiceProvider extends ServiceProvider
{
    /** Keep chat infrastructure separate from account/public-site providers. */
    public function boot(): void
    {
        foreach (['chat-api' => 120, 'chat-messages' => 30, 'chat-identity' => 5, 'chat-reports' => 5] as $name => $budget) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($budget)
                ->by($name.':'.($request->user()->id ?? $request->ip())));
        }
        // Register directly: Laravel's default Broadcast::routes removes CSRF and allows GET.
        Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])
            ->middleware(['web', ChatResponse::class, 'auth', 'verified', 'throttle:chat-api'])
            ->name('broadcasting.auth');
        require base_path('routes/channels.php');

        User::deleting(function (User $user): void {
            // Owned conversations cascade completely. Surviving groups/direct chats need a fresh key.
            $conversations = Conversation::where('created_by', '!=', $user->id)
                ->whereHas('activeMembers', fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($conversations as $conversation) {
                ConversationKeys::invalidate($conversation);
            }
        });
    }
}
