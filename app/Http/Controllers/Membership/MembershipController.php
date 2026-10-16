<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\MembershipPayment;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller for the member-facing membership overview page (/app/mitgliedschaft).
 */
class MembershipController extends Controller
{
    /**
     * Display the membership dashboard with status, tier, invoices, and voucher redemption.
     */
    public function index(Request $request, MollieMembershipService $service): View
    {
        $user = $request->user();
        $subscription = $user->subscription();
        $activeGift = $user->giftMemberships()->where('expires_at', '>', now())->latest('expires_at')->first();

        $trialDaysLeft = $user->trial_ends_at?->isFuture()
            ? (int) ceil(now()->diffInDays($user->trial_ends_at))
            : null;

        $isEligibleForWithdrawal = false;
        if ($subscription && $subscription->valid() && $subscription->created_at) {
            $isEligibleForWithdrawal = ! now()->greaterThan($subscription->created_at->copy()->addDays(14)->endOfDay());
        }

        $invoices = MembershipPayment::where('user_id', $user->id)->where('status', 'paid')->where('kind', '!=', 'mandate')->latest('id')->get();

        return view('app.membership.index', [
            'user' => $user,
            'subscription' => $subscription,
            'activeGift' => $activeGift,
            'trialDaysLeft' => $trialDaysLeft,
            'isFoundingMember' => $user->isFoundingMember(),
            'remainingFoundingSeats' => $service->remainingFoundingSeats(),
            'isEligibleForWithdrawal' => $isEligibleForWithdrawal,
            'invoices' => $invoices,
            'purchasedGifts' => $user->purchasedGiftMemberships()->latest('id')->get(),
            'tiers' => config('oasis.tiers', []),
        ]);
    }
}
