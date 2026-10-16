<?php

namespace App\Http\Controllers\Membership;

use App\Exceptions\Membership\MembershipDomainException;
use App\Http\Controllers\Controller;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Controller for member subscription cancellations complying with § 312k BGB button flow:
 * Step 1: Confirmation page with full contract details and effective date.
 * Step 2: "Jetzt kündigen" submission with prompt text-form email confirmation.
 */
class CancellationController extends Controller
{
    /**
     * Show the cancellation confirmation page with contract details (§ 312k BGB).
     */
    public function confirm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $subscription = $user->subscription();

        if (! $subscription || ! $subscription->valid() || $subscription->onGracePeriod()) {
            return redirect()->route('app.membership')
                ->with('status', 'Du hast derzeit kein aktives Abonnement, das gekündigt werden kann.');
        }

        $tierKey = $user->currentMembershipTier() ?? 'oase';
        $tierName = config("oasis.tiers.{$tierKey}.name", 'Oasis');
        $effectiveDate = $subscription->ends_at ?? $subscription->paid_until;

        return view('app.membership.cancel', [
            'user' => $user,
            'subscription' => $subscription,
            'tierName' => $tierName,
            'effectiveDate' => $effectiveDate,
        ]);
    }

    /**
     * Process the cancellation ("Jetzt kündigen").
     */
    public function cancel(Request $request, MollieMembershipService $service): RedirectResponse
    {
        $user = $request->user();

        try {
            $effectiveDate = $service->cancelSubscription($user);

            return redirect()->route('app.membership')
                ->with('status', 'Deine Kündigung wurde erfolgreich erfasst. Wir haben dir eine Bestätigung per E-Mail geschickt.');
        } catch (MembershipDomainException $e) {
            return redirect()->route('app.membership')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Cancellation error.', [
                'exception_class' => $e::class,
            ]);

            return redirect()->route('app.membership')
                ->with('error', 'Deine Kündigung konnte leider nicht verarbeitet werden. Bitte versuche es später noch einmal.');
        }
    }
}
