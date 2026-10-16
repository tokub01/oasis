<?php

use App\Livewire\Notifications\Inbox;
use App\Livewire\Notifications\Settings;
use Illuminate\Support\Facades\Route;

// Private hints remain usable for verified, read-only members. Both pages are Livewire components; their read and
// save actions run as Livewire requests (CSRF-protected) and are scoped to the signed-in account.
Route::middleware(['auth', 'verified'])->prefix('app/hinweise')->name('notifications.')->group(function (): void {
    Route::livewire('/', Inbox::class)->name('index');
    Route::livewire('/einstellungen', Settings::class)->name('settings');
});
