<?php

use App\Http\Controllers\Network\FeedController;
use App\Http\Controllers\Network\FollowController;
use App\Http\Controllers\Network\InvitationController;
use App\Http\Controllers\Network\OasisController;
use App\Http\Controllers\Network\PostController;
use App\Http\Controllers\Network\PostImageController;
use App\Http\Controllers\Network\ProfileController;
use App\Http\Controllers\Network\ReactionController;
use App\Http\Controllers\Network\StatsController;
use Illuminate\Support\Facades\Route;

/*
 * Stage 2 core network (docs/oasis-network.md). Reading routes are open to guests and check visibility in the
 * policies; every write needs a verified member, and posting/reacting/following additionally the `participate` gate.
 */
Route::name('network.')->group(function () {
    // Readable without an account (public Tropfen, oases and profiles; members-only ones need a login).
    Route::get('/tropfen/{post}', [PostController::class, 'show'])->whereNumber('post')->name('posts.show');
    Route::get('/tropfen/{post}/bild/{image}', PostImageController::class)->whereNumber(['post', 'image'])->name('images.show');
    Route::get('/oasen/{oasis}', [OasisController::class, 'show'])->name('oases.show');
    Route::get('/einladung/{token}', [InvitationController::class, 'show'])->name('invitations.show');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/app/entdecken', [FeedController::class, 'discover'])->name('discover');

        Route::post('/app/tropfen', [PostController::class, 'store'])->middleware('throttle:network-posts')->name('posts.store');
        Route::get('/tropfen/{post}/weitertragen', [PostController::class, 'carry'])->whereNumber('post')->name('posts.carry');
        Route::delete('/tropfen/{post}', [PostController::class, 'destroy'])->whereNumber('post')->name('posts.destroy');
        Route::post('/tropfen/{post}/aus-oase-entfernen', [PostController::class, 'removeFromOasis'])->whereNumber('post')->name('posts.remove');
        Route::post('/tropfen/{post}/reaktion', [ReactionController::class, 'store'])->whereNumber('post')->name('reactions.store');
        Route::delete('/tropfen/{post}/reaktion', [ReactionController::class, 'destroy'])->whereNumber('post')->name('reactions.destroy');

        Route::post('/profil/{user:username}/folgen', [FollowController::class, 'followUser'])->name('follow.user');
        Route::delete('/profil/{user:username}/folgen', [FollowController::class, 'unfollowUser'])->name('unfollow.user');
        Route::post('/oasen/{oasis}/folgen', [FollowController::class, 'followOasis'])->name('follow.oasis');
        Route::delete('/oasen/{oasis}/folgen', [FollowController::class, 'unfollowOasis'])->name('unfollow.oasis');

        Route::get('/app/oasen', [OasisController::class, 'index'])->name('oases.index');
        Route::get('/app/oasen/neu', [OasisController::class, 'create'])->name('oases.create');
        Route::post('/app/oasen', [OasisController::class, 'store'])->name('oases.store');
        Route::get('/oasen/{oasis}/bearbeiten', [OasisController::class, 'edit'])->name('oases.edit');
        Route::put('/oasen/{oasis}', [OasisController::class, 'update'])->name('oases.update');
        Route::put('/oasen/{oasis}/mitglieder/{user:username}', [OasisController::class, 'setRole'])
            ->withoutScopedBindings()->name('oases.role');
        Route::get('/oasen/{oasis}/statistik', [OasisController::class, 'stats'])->name('oases.stats');

        Route::get('/app/profil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/app/profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/app/statistik', StatsController::class)->name('stats');

        Route::get('/app/einladen', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('/app/einladen', [InvitationController::class, 'store'])->name('invitations.store');
    });
});
