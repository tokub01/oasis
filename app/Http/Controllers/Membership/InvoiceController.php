<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\MembershipPayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Member-owned invoices rendered from the immutable billing snapshot, with no remote customer data lookup. */
class InvoiceController extends Controller
{
    /** Only the payer can read a paid invoice; identifiers do not reveal other members' purchases. */
    public function show(Request $request, int $payment): View
    {
        $invoice = MembershipPayment::whereKey($payment)->where('user_id', $request->user()->id)->where('status', 'paid')->where('kind', '!=', 'mandate')->firstOrFail();

        return view('app.membership.invoice', ['payment' => $invoice, 'details' => $invoice->invoice_data ?? []]);
    }
}
