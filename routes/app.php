<?php

use App\Http\Controllers\Account\ExportAccountController;
use App\Livewire\Account\Settings;
use App\Livewire\Network\Source;
use App\Livewire\Onboarding\Welcome;
use App\Livewire\Privacy\Center;
use App\Livewire\Privacy\Checkup;
use App\Livewire\Privacy\Devices;
use App\Livewire\Privacy\HiddenWords;
use Illuminate\Support\Facades\Route;

/* Account routes use web sessions/CSRF via routes/web.php; only verified users enter the app. */
// "Quelle": the chronological feed with an end (stage 2).
Route::livewire('/app', Source::class)->middleware(['auth', 'verified'])->name('app.home');
// Settings (incl. account deletion, GDPR Art. 17) stay reachable while a changed e-mail address is unverified, so the
// member can correct a mistyped address or delete the account; every action still checks what it needs itself.
Route::livewire('/app/einstellungen', Settings::class)->middleware('auth')->name('app.settings');
// Privatsphäre-Center and the guided check (stage 13); the settings are enforced through PrivacySetting::allows().
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('/app/privatsphaere', Center::class)->name('app.privacy');
    // Einstieg für neue Mitglieder (skippable; the start page leads here once, docs/oasis-network.md).
    Route::livewire('/app/willkommen', Welcome::class)->name('app.welcome');
    Route::livewire('/app/privatsphaere/check', Checkup::class)->name('app.privacy.check');
    Route::livewire('/app/privatsphaere/woerter', HiddenWords::class)->name('app.privacy.words');
    Route::livewire('/app/privatsphaere/geraete', Devices::class)->name('app.privacy.devices');
});
// Encrypted chat page; all chat data comes from the JSON API in routes/chat.php.
Route::view('/app/nachrichten', 'app.chat.index')->middleware(['auth', 'verified'])->name('app.chat');

// Export remains a controller download and stays available even if an account's new email is not verified yet.
Route::middleware('auth')->group(function () {
    Route::get('/app/konto/export', ExportAccountController::class)->name('account.export');
});
