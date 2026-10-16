<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Unsigned Mollie callbacks contain only an identifier; the API is the sole authority on payment status. */
class MollieWebhookController extends Controller
{
    /** Fail with a retryable response on API errors, never grant access from the submitted body. */
    public function handleWebhook(Request $request, MollieMembershipService $service): Response
    {
        $validated = $request->validate(['id' => ['required', 'string', 'regex:/^tr_[a-zA-Z0-9]+$/', 'max:100']]);
        try {
            $service->processPayment($validated['id']);
        } catch (\Throwable $e) {
            Log::error('Mollie webhook processing failed.', ['exception_class' => $e::class]);

            return response('', 503);
        }

        return response('', 200);
    }
}
