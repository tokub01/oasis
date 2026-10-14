<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Services\Membership\StripeMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * Controller redirecting authenticated members to the Stripe Customer Portal
 * to manage payment methods, billing details, and download invoices.
 */
class PortalController extends Controller
{
    /**
     * Redirect to Stripe billing portal.
     */
    public function index(Request $request, StripeMembershipService $service): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasStripeId()) {
            return redirect()->route('app.membership')
                ->with('status', 'Du hast bisher keine Zahlungsdaten bei Stripe hinterlegt.');
        }

        try {
            $url = $service->getBillingPortalUrl($user);

            return redirect()->away($url);
        } catch (\Throwable $e) {
            Log::error('Stripe billing portal loading failed.', [
                'exception_class' => $e::class,
                'provider_code' => $e instanceof ApiErrorException ? $e->getStripeCode() : null,
            ]);

            return redirect()->route('app.membership')
                ->with('error', 'Das Kundenportal konnte leider nicht geladen werden. Bitte versuche es später noch einmal.');
        }
    }
}
