<?php

use App\Http\Controllers\Auth\TwoFactorRecoveryController;
use App\Http\Controllers\Operations\HealthController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/oasis.php';

// Lost second factor: self-service recovery with e-mail link and waiting period (docs/oasis-accounts.md).
Route::prefix('zwei-faktor-hilfe')->name('two-factor.recovery.')->group(function (): void {
    Route::get('/', [TwoFactorRecoveryController::class, 'create'])->name('create');
    Route::post('/', [TwoFactorRecoveryController::class, 'store'])->middleware('throttle:6,1')->name('store');
    Route::middleware('throttle:30,1')->where(['token' => '[A-Za-z0-9]{48}'])->group(function (): void {
        Route::get('/{token}', [TwoFactorRecoveryController::class, 'show'])->name('show');
        Route::post('/{token}/bestaetigen', [TwoFactorRecoveryController::class, 'confirm'])->name('confirm');
        Route::post('/{token}/abschliessen', [TwoFactorRecoveryController::class, 'complete'])->name('complete');
        Route::post('/{token}/abbrechen', [TwoFactorRecoveryController::class, 'cancel'])->name('cancel');
    });
});

// Operations: health check for uptime monitors (token-protected, docs/oasis-operations.md).
Route::get('/gesundheit', HealthController::class)->name('operations.health');

require __DIR__.'/app.php';

// Stage 4a: authorized staff moderation and public signed complaints.
require __DIR__.'/moderation.php';

// Stage 3: ciphertext-only chat APIs (web sessions, CSRF and verified email).
require __DIR__.'/chat.php';

// Stage 2: core network (Tropfen, profiles, oases, follows, statistics).
require __DIR__.'/network.php';

// Stage 1 & 5: Membership, Mollie Checkout, portal and cancellation.
require __DIR__.'/membership.php';

// Stage 6: admin statistics, transparency reports and member votes.
require __DIR__.'/admin.php';

// Stage 8: search and #Thema pages.
require __DIR__.'/search.php';

require __DIR__.'/notifications.php';

require __DIR__.'/safety.php';
