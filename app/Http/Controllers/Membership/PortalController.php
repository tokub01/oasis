<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\MembershipPayment;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** Account-owned billing management; Mollie has no hosted customer portal. */
class PortalController extends Controller
{
    /** Show payments and a CSRF-protected mandate replacement form. */
    public function index(Request $request): View
    {
        return view('app.membership.portal', ['payments' => MembershipPayment::where('user_id', $request->user()->id)->where('status', 'paid')->where('kind', '!=', 'mandate')->latest('id')->get()]);
    }

    /** Send the member to hosted checkout to authorize a new recurring mandate. */
    public function update(Request $request, MollieMembershipService $service): RedirectResponse
    {
        $data = $request->validate(['zahlart' => ['required', 'in:sepa,paypal,karte']]);
        try {
            return redirect()->away($service->changePaymentMethod($request->user(), $data['zahlart']));
        } catch (\Throwable $e) {
            Log::error('Mollie payment method update failed.', ['exception_class' => $e::class]);

            return redirect()->route('app.membership.portal')->with('error', 'Das Zahlungsmittel konnte nicht geändert werden. Bitte versuche es später erneut.');
        }
    }
}
