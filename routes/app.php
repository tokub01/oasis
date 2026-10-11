<?php

use App\Http\Controllers\Account\DeleteAccountController;
use App\Http\Controllers\Account\ExportAccountController;
use Illuminate\Support\Facades\Route;

/* Account routes use web sessions/CSRF via routes/web.php; only verified users enter the app. */
Route::view('/app', 'app.home')->middleware(['auth', 'verified'])->name('app.home');
Route::view('/app/einstellungen', 'app.settings')->middleware(['auth', 'verified'])->name('app.settings');

// Export and deletion remain available even if an account's new email is not verified yet.
Route::middleware('auth')->group(function () {
    Route::get('/app/konto/export', ExportAccountController::class)->name('account.export');
    Route::delete('/app/konto', DeleteAccountController::class)
        ->middleware('password.confirm')->name('account.destroy');
});
