<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Services\Membership\StripeMembershipService;
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
    public function index(Request $request, StripeMembershipService $service): View
    {
        $user = $request->user();
        $subscription = $user->subscription();
        $activeGift = $user->giftMemberships()->where('expires_at', '>', now())->latest('expires_at')->first();

        $trialDaysLeft = $user->trial_ends_at?->isFuture()
            ? (int) ceil(now()->diffInDays($user->trial_ends_at))
            : null;

        $isEligibleForWithdrawal = false;
        if ($subscription && $subscription->valid() && $subscription->created_at) {
            $isEligibleForWithdrawal = $subscription->created_at->diffInDays(now()) <= 14;
        }

        $invoices = [];
        try {
            if ($user->hasStripeId()) {
                $invoices = $user->invoices();
            }
        } catch (\Throwable) {
            // In local/test without live API connection, proceed with empty invoice list
            $invoices = [];
        }

        return view('app.membership.index', [
            'user' => $user,
            'subscription' => $subscription,
            'activeGift' => $activeGift,
            'trialDaysLeft' => $trialDaysLeft,
            'isFoundingMember' => $user->isFoundingMember(),
            'remainingFoundingSeats' => $service->remainingFoundingSeats(),
            'isEligibleForWithdrawal' => $isEligibleForWithdrawal,
            'invoices' => $invoices,
            'tiers' => config('oasis.tiers', []),
        ]);
    }
}
