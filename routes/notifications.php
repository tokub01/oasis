<?php

use App\Http\Controllers\Notifications\DigestUnsubscribeController;
use App\Http\Controllers\Notifications\PushSubscriptionController;
use App\Livewire\Notifications\Inbox;
use App\Livewire\Notifications\Settings;
use Illuminate\Support\Facades\Route;

// Private hints remain usable for verified, read-only members. Both pages are Livewire components; their read and
// save actions run as Livewire requests (CSRF-protected) and are scoped to the signed-in account.
Route::middleware(['auth', 'verified'])->prefix('app/hinweise')->name('notifications.')->group(function (): void {
    Route::livewire('/', Inbox::class)->name('index');
    Route::livewire('/einstellungen', Settings::class)->name('settings');
    // Push hints for this browser (resources/js/oasis.js, module push); JSON, CSRF via the X-CSRF-TOKEN header.
    Route::post('/push', [PushSubscriptionController::class, 'store'])->middleware('throttle:10,1')->name('push.store');
    Route::post('/push/aus', [PushSubscriptionController::class, 'destroy'])->middleware('throttle:10,1')->name('push.destroy');
});

// Switch the daily summary off from the mail without signing in (signed links; one-click POST per RFC 8058 without
// CSRF token, see bootstrap/app.php).
Route::middleware('signed')->prefix('hinweise/zusammenfassung/{user}')->name('notifications.digest.')->group(function (): void {
    Route::get('/abbestellen', [DigestUnsubscribeController::class, 'show'])->middleware('throttle:20,1')->name('unsubscribe');
    Route::post('/abbestellen', [DigestUnsubscribeController::class, 'confirm'])->middleware('throttle:6,1')->name('confirm');
    Route::post('/ein-klick', [DigestUnsubscribeController::class, 'oneClick'])->middleware('throttle:6,1')->name('one-click');
});
