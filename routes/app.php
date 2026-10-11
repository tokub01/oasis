<?php

use App\Http\Controllers\Account\DeleteAccountController;
use App\Http\Controllers\Account\ExportAccountController;
use App\Http\Controllers\Network\FeedController;
use Illuminate\Support\Facades\Route;

/* Account routes use web sessions/CSRF via routes/web.php; only verified users enter the app. */
// "Quelle": the chronological feed with an end (stage 2).
Route::get('/app', [FeedController::class, 'source'])->middleware(['auth', 'verified'])->name('app.home');
Route::view('/app/einstellungen', 'app.settings')->middleware(['auth', 'verified'])->name('app.settings');
// Encrypted chat page; all chat data comes from the JSON API in routes/chat.php.
Route::view('/app/nachrichten', 'app.chat.index')->middleware(['auth', 'verified'])->name('app.chat');

// Export and deletion remain available even if an account's new email is not verified yet.
Route::middleware('auth')->group(function () {
    Route::get('/app/konto/export', ExportAccountController::class)->name('account.export');
    Route::delete('/app/konto', DeleteAccountController::class)
        ->middleware('password.confirm')->name('account.destroy');
});
