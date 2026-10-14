<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Services\Membership\StripeMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiErrorException;

/**
 * Controller for purchasing a 12-month gift membership voucher via Stripe Checkout.
 */
class GiftPurchaseController extends Controller
{
    /**
     * Start Checkout for gift membership purchase.
     */
    public function checkout(Request $request, StripeMembershipService $service): RedirectResponse
    {
        $tiers = array_keys(config('oasis.tiers', []));

        $validated = $request->validate([
            'tier' => ['required', 'string', Rule::in($tiers)],
        ]);

        $user = $request->user();

        try {
            $checkoutUrl = $service->createGiftCheckout($user, $validated['tier']);

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            Log::error('Gift purchase checkout failed.', [
                'exception_class' => $e::class,
                'provider_code' => $e instanceof ApiErrorException ? $e->getStripeCode() : null,
            ]);

            return redirect()->route('app.membership')
                ->with('error', 'Der Kauf konnte leider nicht gestartet werden. Bitte versuche es später noch einmal.');
        }
    }
}
