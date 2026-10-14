<?php

namespace App\Http\Controllers\Membership;

use App\Exceptions\Membership\MembershipDomainException;
use App\Http\Controllers\Controller;
use App\Services\Membership\StripeMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * Controller for redeeming a gift membership voucher on the user's account.
 */
class GiftRedemptionController extends Controller
{
    /**
     * Redeem a gift code.
     */
    public function redeem(Request $request, StripeMembershipService $service): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ], [
            'code.required' => 'Bitte gib einen Gutscheincode ein.',
        ]);

        $user = $request->user();

        try {
            $gift = $service->redeemGift($user, $validated['code']);
            $validUntil = $gift->expires_at?->timezone('Europe/Berlin')->format('d.m.Y');

            return redirect()->route('app.membership')
                ->with('status', "Gutschein erfolgreich eingelöst! Deine Mitgliedschaft ist bis zum {$validUntil} aktiv.");
        } catch (MembershipDomainException $e) {
            return redirect()->route('app.membership')
                ->withErrors(['code' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Gift redemption error.', [
                'exception_class' => $e::class,
                'provider_code' => $e instanceof ApiErrorException ? $e->getStripeCode() : null,
            ]);

            return redirect()->route('app.membership')
                ->withErrors(['code' => 'Der Gutschein konnte leider nicht eingelöst werden. Bitte prüfe deine Eingabe oder wende dich an den Support.']);
        }
    }
}
