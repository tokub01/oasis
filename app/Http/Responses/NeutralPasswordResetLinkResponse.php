<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Answers unknown-address and broker-throttled reset requests exactly like a successful one.
 *
 * Fortify's default turns an unknown address into a validation error, which tells anyone whether an email address
 * has an Oasis account (account enumeration). A second request also reveals account existence if broker throttling
 * is exposed. Bound in FortifyServiceProvider; browser and JSON responses match the successful Fortify response.
 */
class NeutralPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    /**
     * Return the neutral "sent" response without exposing the password broker's lookup status.
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $message = trans(Password::RESET_LINK_SENT);

        return $request->wantsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }
}
