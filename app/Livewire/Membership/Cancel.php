<?php

namespace App\Livewire\Membership;

use App\Exceptions\Membership\MembershipDomainException;
use App\Models\User;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Member subscription cancellation confirmation page on /app/mitgliedschaft/kuendigen (route app.membership.cancel.confirm).
 *
 * Complies with § 312k BGB: displays full contract details and effective date, then executes the cancellation on
 * "Jetzt kündigen" (`cancel`) and sends immediate text-form email confirmation.
 */
class Cancel extends Component
{
    /**
     * Redirect to the membership overview if the member has no active, cancellable subscription.
     */
    public function mount(): void
    {
        $user = $this->actor();
        $subscription = $user->subscription();

        if (! $subscription || ! $subscription->valid() || $subscription->onGracePeriod()) {
            session()->flash('status', 'Du hast derzeit kein aktives Abonnement, das gekündigt werden kann.');
            $this->redirectRoute('app.membership', navigate: true);
        }
    }

    /**
     * Execute the ordinary subscription cancellation ("Jetzt kündigen").
     */
    public function cancel(MollieMembershipService $service): void
    {
        $user = $this->actor();

        try {
            $service->cancelSubscription($user);

            session()->flash('status', 'Deine Kündigung wurde erfolgreich erfasst. Wir haben dir eine Bestätigung per E-Mail geschickt.');
            $this->redirectRoute('app.membership', navigate: true);
        } catch (MembershipDomainException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirectRoute('app.membership', navigate: true);
        } catch (\Throwable $e) {
            Log::error('Cancellation error.', [
                'exception_class' => $e::class,
            ]);

            session()->flash('error', 'Deine Kündigung konnte leider nicht verarbeitet werden. Bitte versuche es später noch einmal.');
            $this->redirectRoute('app.membership', navigate: true);
        }
    }

    /**
     * Render the § 312k BGB cancellation confirmation screen.
     */
    public function render(): View
    {
        $user = $this->actor();
        $subscription = $user->subscription();
        $tierKey = $user->currentMembershipTier() ?? 'oase';
        $tierName = config("oasis.tiers.{$tierKey}.name", 'Oasis');
        $effectiveDate = $subscription->ends_at ?? $subscription?->paid_until;

        return view('livewire.membership.cancel', [
            'user' => $user,
            'subscription' => $subscription,
            'tierName' => $tierName,
            'effectiveDate' => $effectiveDate,
        ])->layout('layouts.app', ['title' => 'Mitgliedschaft kündigen', 'active' => 'mitgliedschaft', 'wide' => true]);
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
