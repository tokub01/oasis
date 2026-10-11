<?php

use App\Http\Controllers\Membership\CancellationController;
use App\Http\Controllers\Membership\CheckoutController;
use App\Http\Controllers\Membership\GiftPurchaseController;
use App\Http\Controllers\Membership\GiftRedemptionController;
use App\Http\Controllers\Membership\MembershipController;
use App\Http\Controllers\Membership\PortalController;
use App\Http\Controllers\Membership\StripeWebhookController;
use App\Http\Controllers\Membership\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Membership & Payment Routes (Stages 1 & 5)
|--------------------------------------------------------------------------
*/

// Public / Checkout routes (guarded by oasis.checkout_enabled inside the controller)
Route::post('/mitglied-werden/checkout', [CheckoutController::class, 'checkout'])->name('oasis.mitglied.checkout');
Route::post('/mitglied-werden', [CheckoutController::class, 'checkout']);

// Member-area routes: verified Oasis accounts
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/app/mitgliedschaft', [MembershipController::class, 'index'])->name('app.membership');
    Route::get('/app/mitgliedschaft/portal', [PortalController::class, 'index'])->name('app.membership.portal');

    // § 312k BGB Cancellation Flow
    Route::get('/app/mitgliedschaft/kuendigen', [CancellationController::class, 'confirm'])->name('app.membership.cancel.confirm');
    Route::post('/app/mitgliedschaft/kuendigen', [CancellationController::class, 'cancel'])->name('app.membership.cancel');

    // § 355/356 BGB 14-Day Statutory Withdrawal
    Route::get('/app/mitgliedschaft/widerrufen', [WithdrawalController::class, 'confirm'])->name('app.membership.withdraw.confirm');
    Route::post('/app/mitgliedschaft/widerrufen', [WithdrawalController::class, 'withdraw'])->name('app.membership.withdraw');

    // Gift membership redemption & purchase
    Route::post('/app/mitgliedschaft/einloesen', [GiftRedemptionController::class, 'redeem'])->name('app.membership.redeem');
    Route::post('/app/mitgliedschaft/geschenke/kaufen', [GiftPurchaseController::class, 'checkout'])->name('app.membership.gift.checkout');
});

// Custom Stripe Webhook route with signature verification
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');
