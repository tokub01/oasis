<?php

namespace App\Livewire\Membership;

use App\Models\MembershipPayment;
use App\Models\User;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Account-owned billing management on /app/mitgliedschaft/portal (route app.membership.portal).
 *
 * Lists paid membership contributions with invoice links and lets the member authorize a new recurring payment
 * mandate via Mollie hosted checkout (`updatePaymentMethod`).
 */
class Portal extends Component
{
    /** Selected payment method for mandate replacement ('sepa', 'karte', 'paypal'). */
    public string $zahlart = 'sepa';

    /** Error message if starting the mandate replacement checkout fails, announced via role="alert". */
    #[Locked]
    public string $error = '';

    /**
     * Redirect the authenticated member to Mollie hosted checkout to authorize a new recurring mandate.
     */
    public function updatePaymentMethod(MollieMembershipService $service): void
    {
        $user = $this->actor();
        $this->error = '';

        $data = $this->validate([
            'zahlart' => ['required', 'in:sepa,paypal,karte'],
        ]);

        try {
            $checkoutUrl = $service->changePaymentMethod($user, $data['zahlart']);

            $this->redirect($checkoutUrl);
        } catch (\Throwable $e) {
            Log::error('Mollie payment method update failed.', ['exception_class' => $e::class]);

            $this->error = 'Das Zahlungsmittel konnte nicht geändert werden. Bitte versuche es später erneut.';
            session()->flash('error', $this->error);
        }
    }

    /**
     * Render the member's paid invoices and payment method replacement form.
     */
    public function render(): View
    {
        $user = $this->actor();

        $payments = MembershipPayment::where('user_id', $user->id)
            ->where('status', 'paid')
            ->where('kind', '!=', 'mandate')
            ->latest('id')
            ->get();

        return view('livewire.membership.portal', [
            'payments' => $payments,
        ])->layout('layouts.app', ['title' => 'Zahlungen verwalten', 'active' => 'mitgliedschaft']);
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
