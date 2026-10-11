<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Services\Membership\StripeMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Controller handling the checkout initiation from /mitglied-werden.
 */
class CheckoutController extends Controller
{
    /**
     * Start the Stripe Checkout session for a selected membership tier.
     */
    public function checkout(Request $request, StripeMembershipService $service): RedirectResponse
    {
        abort_unless(config('oasis.checkout_enabled'), 404);

        $minMaezen = (int) config('oasis.tiers.maezen.monthly', 12);

        $validated = $request->validate([
            'stufe' => ['required', 'string', Rule::in(['solidar', 'oase', 'maezen'])],
            'rhythmus' => ['required', 'string', Rule::in(['monat', 'jahr'])],
            'betrag' => ['nullable', 'numeric', 'min:'.$minMaezen],
            'zahlart' => ['nullable', 'string', Rule::in(['sepa', 'paypal', 'karte'])],
        ], [
            'stufe.required' => 'Bitte wähle eine Mitgliedschaftsstufe aus.',
            'rhythmus.required' => 'Bitte wähle deine Zahlweise aus.',
            'betrag.min' => "Der Mindestbeitrag für Mäzen beträgt {$minMaezen} € im Monat.",
        ]);

        $user = $request->user();

        if (! $user) {
            return redirect()->route('register')
                ->with('status', 'Erstelle bitte zuerst dein Oasis-Konto, um deine Mitgliedschaft abzuschließen.');
        }

        $paymentMethod = $validated['zahlart'] ?? 'sepa';
        $customAmount = ($validated['stufe'] === 'maezen' && ! empty($validated['betrag']))
            ? (int) $validated['betrag']
            : null;

        try {
            $checkoutUrl = $service->createSubscriptionCheckout(
                $user,
                $validated['stufe'],
                $validated['rhythmus'],
                $customAmount,
                $paymentMethod
            );

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout initiation failed: '.$e->getMessage());

            return redirect()->route('oasis.mitglied')
                ->with('error', 'Der Bezahlvorgang konnte leider nicht gestartet werden. Bitte versuche es später noch einmal.');
        }
    }
}
