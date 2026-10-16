<?php

use App\Http\Controllers\Membership\InvoiceController;
use App\Http\Controllers\Membership\MollieWebhookController;
use App\Livewire\Membership\Cancel;
use App\Livewire\Membership\Overview;
use App\Livewire\Membership\Portal;
use App\Livewire\Membership\Withdraw;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Membership & Payment Routes (Stages 1, 5, 11 & 12)
|--------------------------------------------------------------------------
|
| Member-facing screens are Livewire full-page components. Invoice rendering
| and the Mollie webhook remain controllers.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/app/mitgliedschaft', Overview::class)->name('app.membership');
    Route::livewire('/app/mitgliedschaft/portal', Portal::class)->name('app.membership.portal');

    Route::get('/app/mitgliedschaft/rechnungen/{payment}', [InvoiceController::class, 'show'])
        ->whereNumber('payment')
        ->name('app.membership.invoice');

    // § 312k BGB Cancellation Flow
    Route::livewire('/app/mitgliedschaft/kuendigen', Cancel::class)->name('app.membership.cancel.confirm');

    // § 355/356 BGB 14-Day Statutory Withdrawal
    Route::livewire('/app/mitgliedschaft/widerrufen', Withdraw::class)->name('app.membership.withdraw.confirm');
});

// Mollie notifications verified through the authenticated API
Route::post('/mollie/webhook', [MollieWebhookController::class, 'handleWebhook'])->name('mollie.webhook');
