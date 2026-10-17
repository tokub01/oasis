<?php

namespace App\Livewire\Membership;

use App\Exceptions\Membership\MembershipDomainException;
use App\Models\User;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Statutory 14-day contract withdrawal confirmation on /app/mitgliedschaft/widerrufen (route app.membership.withdraw.confirm).
 *
 * Complies with §§ 355, 356 BGB: verifies an active subscription within the 14-day withdrawal period, terminates
 * the subscription immediately on "Jetzt widerrufen" (`withdraw`), refunds paid contributions and sends confirmation emails.
 */
class Withdraw extends Component
{
    /**
     * Redirect to the membership overview if there is no active subscription or the 14-day period has expired.
     */
    public function mount(): void
    {
        $user = $this->actor();
        $subscription = $user->subscription();

        if (! $subscription || ! $subscription->valid()) {
            session()->flash('status', 'Du hast derzeit kein aktives Abonnement.');
            $this->redirectRoute('app.membership', navigate: true);

            return;
        }

        if ($subscription->created_at && now()->greaterThan($subscription->created_at->copy()->addDays(14)->endOfDay())) {
            session()->flash('status', 'Die gesetzliche 14-tägige Widerrufsfrist für diesen Vertrag ist bereits abgelaufen.');
            $this->redirectRoute('app.membership', navigate: true);
        }
    }

    /**
     * Execute the statutory 14-day withdrawal ("Jetzt widerrufen").
     */
    public function withdraw(MollieMembershipService $service): void
    {
        $user = $this->actor();

        try {
            $service->withdrawSubscription($user);

            session()->flash('status', 'Dein Widerruf wurde erfolgreich erfasst. Deine Mitgliedschaft wurde mit sofortiger Wirkung beendet.');
            $this->redirectRoute('app.membership', navigate: true);
        } catch (MembershipDomainException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirectRoute('app.membership', navigate: true);
        } catch (\Throwable $e) {
            Log::error('Withdrawal error.', [
                'exception_class' => $e::class,
            ]);

            session()->flash('error', 'Dein Widerruf konnte leider nicht verarbeitet werden. Bitte versuche es später noch einmal.');
            $this->redirectRoute('app.membership', navigate: true);
        }
    }

    /**
     * Render the statutory 14-day withdrawal confirmation screen.
     */
    public function render(): View
    {
        $user = $this->actor();
        $subscription = $user->subscription();
        $tierKey = $user->currentMembershipTier() ?? 'oase';
        $tierName = config("oasis.tiers.{$tierKey}.name", 'Oasis');

        return view('livewire.membership.withdraw', [
            'user' => $user,
            'subscription' => $subscription,
            'tierName' => $tierName,
        ])->layout('layouts.app', ['title' => 'Mitgliedschaft widerrufen', 'active' => 'mitgliedschaft', 'wide' => true]);
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
