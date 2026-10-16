<?php

use App\Http\Controllers\Account\ExportAccountController;
use App\Livewire\Account\Settings;
use App\Livewire\Network\Source;
use Illuminate\Support\Facades\Route;

/* Account routes use web sessions/CSRF via routes/web.php; only verified users enter the app. */
// "Quelle": the chronological feed with an end (stage 2).
Route::livewire('/app', Source::class)->middleware(['auth', 'verified'])->name('app.home');
// Settings (incl. account deletion, GDPR Art. 17) stay reachable while a changed e-mail address is unverified, so the
// member can correct a mistyped address or delete the account; every action still checks what it needs itself.
Route::livewire('/app/einstellungen', Settings::class)->middleware('auth')->name('app.settings');
// Encrypted chat page; all chat data comes from the JSON API in routes/chat.php.
Route::view('/app/nachrichten', 'app.chat.index')->middleware(['auth', 'verified'])->name('app.chat');

// Export remains a controller download and stays available even if an account's new email is not verified yet.
Route::middleware('auth')->group(function () {
    Route::get('/app/konto/export', ExportAccountController::class)->name('account.export');
});
