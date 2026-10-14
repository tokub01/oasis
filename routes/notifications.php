<?php

use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Notifications\SettingsController;
use Illuminate\Support\Facades\Route;

// Private hints remain usable for verified, read-only members; writes use the web CSRF middleware.
Route::middleware(['auth', 'verified'])->prefix('app/hinweise')->name('notifications.')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/einstellungen', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/einstellungen', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/alle-gelesen', [NotificationController::class, 'readAll'])->name('read-all');
    Route::post('/{notification}/gelesen', [NotificationController::class, 'read'])->whereUuid('notification')->name('read');
});
