<?php

namespace App\Http\Controllers\Membership;

use App\Exceptions\Membership\MembershipDomainException;
use App\Http\Controllers\Controller;
use App\Services\Membership\StripeMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Controller for statutory 14-day contract withdrawals (§ 355, 356 BGB).
 */
class WithdrawalController extends Controller
{
    /**
     * Show withdrawal confirmation page.
     */
    public function confirm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $subscription = $user->subscription();

        if (! $subscription || ! $subscription->valid()) {
            return redirect()->route('app.membership')
                ->with('status', 'Du hast derzeit kein aktives Abonnement.');
        }

        if ($subscription->created_at && now()->greaterThan($subscription->created_at->copy()->addDays(14)->endOfDay())) {
            return redirect()->route('app.membership')
                ->with('status', 'Die gesetzliche 14-tägige Widerrufsfrist für diesen Vertrag ist bereits abgelaufen.');
        }

        $tierKey = $user->currentMembershipTier() ?? 'oase';
        $tierName = config("oasis.tiers.{$tierKey}.name", 'Oasis');

        return view('app.membership.withdraw', [
            'user' => $user,
            'subscription' => $subscription,
            'tierName' => $tierName,
        ]);
    }

    /**
     * Process statutory withdrawal.
     */
    public function withdraw(Request $request, StripeMembershipService $service): RedirectResponse
    {
        $user = $request->user();

        try {
            $service->withdrawSubscription($user);

            return redirect()->route('app.membership')
                ->with('status', 'Dein Widerruf wurde erfolgreich erfasst. Deine Mitgliedschaft wurde mit sofortiger Wirkung beendet.');
        } catch (MembershipDomainException $e) {
            return redirect()->route('app.membership')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Withdrawal error: '.$e->getMessage());

            return redirect()->route('app.membership')
                ->with('error', 'Dein Widerruf konnte leider nicht verarbeitet werden. Bitte versuche es später noch einmal.');
        }
    }
}
