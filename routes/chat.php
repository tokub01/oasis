<?php

use App\Http\Controllers\Chat\ChatReportController;
use App\Http\Controllers\Chat\ConversationController;
use App\Http\Controllers\Chat\IdentityController;
use App\Http\Controllers\Chat\KeyGrantController;
use App\Http\Controllers\Chat\MessageController;
use App\Http\Controllers\Chat\PublicKeysController;
use App\Http\Middleware\ChatResponse;
use Illuminate\Support\Facades\Route;

// Web sessions + CSRF, verified email, bounded account budgets and no device caching for all chat data.
// Starting chats, adding members and sending need the stage 1 participation check (read-only accounts get 403).
Route::prefix('app/chat/api')->name('chat.api.')
    ->middleware([ChatResponse::class, 'auth', 'verified', 'throttle:chat-api'])->group(function () {
        Route::get('identity', [IdentityController::class, 'show'])->name('identity.show');
        Route::put('identity', [IdentityController::class, 'update'])->middleware('throttle:chat-identity')->name('identity.update');
        Route::get('users', [PublicKeysController::class, 'users'])->name('users.index');
        Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('conversations', [ConversationController::class, 'store'])->middleware('participate')->name('conversations.store');
        Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::patch('conversations/{conversation}', [ConversationController::class, 'update'])->name('conversations.update');
        Route::get('conversations/{conversation}/public-keys', [PublicKeysController::class, 'index'])->name('keys.index');
        Route::post('conversations/{conversation}/members', [ConversationController::class, 'add'])->middleware('participate')->name('members.store');
        Route::delete('conversations/{conversation}/members/{user}', [ConversationController::class, 'remove'])
            ->whereNumber('user')->name('members.destroy');
        Route::get('conversations/{conversation}/key-grants', [KeyGrantController::class, 'index'])->name('grants.index');
        Route::post('conversations/{conversation}/key-grants', [KeyGrantController::class, 'store'])->name('grants.store');
        Route::post('conversations/{conversation}/rotate', [KeyGrantController::class, 'rotate'])->name('rotation.store');
        Route::get('conversations/{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->middleware(['participate', 'throttle:chat-messages'])->name('messages.store');
        Route::post('conversations/{conversation}/reports', [ChatReportController::class, 'store'])
            ->middleware('throttle:chat-reports')->name('reports.store');
    });
