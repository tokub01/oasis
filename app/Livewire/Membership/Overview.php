<?php

namespace App\Livewire\Membership;

use App\Exceptions\Membership\MembershipDomainException;
use App\Livewire\Concerns\LimitsAttempts;
use App\Models\User;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Member-facing membership dashboard on /app/mitgliedschaft (route app.membership).
 *
 * Shows the current subscription, gifted membership or trial state, founding member status, purchased vouchers,
 * inline voucher redemption (`redeem`) and 12-month gift voucher checkout (`buyGift`).
 */
class Overview extends Component
{
    use LimitsAttempts;

    /** Voucher code entered by the member for inline redemption. */
    public string $code = '';

    /** Selected tier key when purchasing a gift membership voucher. */
    public string $tier = 'oase';

    /** Confirmation message after redeeming a voucher, announced via role="status". */
    #[Locked]
    public string $status = '';

    /** Error message when starting a gift checkout fails, announced via role="alert". */
    #[Locked]
    public string $error = '';

    /**
     * Redeem a 12-month gift membership voucher code on the signed-in account.
     */
    public function redeem(MollieMembershipService $service): void
    {
        $user = $this->actor();
        $this->error = '';
        $this->resetErrorBag();
        // Gift codes are secrets: five tries per minute stop guessing.
        $this->limitAttempts('gift-redeem|'.$user->id, 5);

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:64'],
        ], [
            'code.required' => 'Bitte gib einen Gutscheincode ein.',
        ]);

        try {
            $gift = $service->redeemGift($user, $validated['code']);
            $validUntil = $gift->expires_at?->timezone('Europe/Berlin')->format('d.m.Y');

            $this->code = '';
            $this->status = "Gutschein erfolgreich eingelöst! Deine Mitgliedschaft ist bis zum {$validUntil} aktiv.";
        } catch (MembershipDomainException $e) {
            $this->addError('code', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Gift redemption error.', [
                'exception_class' => $e::class,
            ]);

            $this->addError('code', 'Der Gutschein konnte leider nicht eingelöst werden. Bitte prüfe deine Eingabe oder wende dich an den Support.');
        }
    }

    /**
     * Start a Mollie one-off checkout session to purchase a 12-month gift membership voucher.
     */
    public function buyGift(MollieMembershipService $service, ?string $tier = null): void
    {
        $user = $this->actor();
        $this->error = '';
        $this->resetErrorBag();

        // Every attempt creates an order and a payment at Mollie.
        $this->limitAttempts('gift-checkout|'.$user->id, 5);
        $chosenTier = $tier ?? $this->tier;
        $tiers = array_keys(config('oasis.tiers', []));

        $validated = Validator::make(
            ['tier' => $chosenTier],
            ['tier' => ['required', 'string', Rule::in($tiers)]]
        )->validate();

        try {
            $checkoutUrl = $service->createGiftCheckout($user, $validated['tier']);

            $this->redirect($checkoutUrl);
        } catch (\Throwable $e) {
            Log::error('Gift purchase checkout failed.', [
                'exception_class' => $e::class,
            ]);

            $this->error = 'Der Kauf konnte leider nicht gestartet werden. Bitte versuche es später noch einmal.';
        }
    }

    /**
     * Render the membership overview for the authenticated, verified member.
     */
    public function render(MollieMembershipService $service): View
    {
        $user = $this->actor()->fresh();
        $subscription = $user->subscription();
        $activeGift = $user->giftMemberships()->where('expires_at', '>', now())->latest('expires_at')->first();

        $trialDaysLeft = $user->trial_ends_at?->isFuture()
            ? (int) ceil(now()->diffInDays($user->trial_ends_at))
            : null;

        $isEligibleForWithdrawal = false;
        if ($subscription && $subscription->valid() && $subscription->created_at) {
            $isEligibleForWithdrawal = ! now()->greaterThan($subscription->created_at->copy()->addDays(14)->endOfDay());
        }

        return view('livewire.membership.overview', [
            'user' => $user,
            'subscription' => $subscription,
            'activeGift' => $activeGift,
            'trialDaysLeft' => $trialDaysLeft,
            'isFoundingMember' => $user->isFoundingMember(),
            'remainingFoundingSeats' => $service->remainingFoundingSeats(),
            'isEligibleForWithdrawal' => $isEligibleForWithdrawal,
            'purchasedGifts' => $user->purchasedGiftMemberships()->latest('id')->get(),
            'tiers' => config('oasis.tiers', []),
        ])->layout('layouts.app', ['title' => 'Mitgliedschaft', 'active' => 'mitgliedschaft']);
    }

    /**
     * Re-authorize the signed-in, verified member on every request.
     */
    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasVerifiedEmail(), 403);

        return $user;
    }
}
