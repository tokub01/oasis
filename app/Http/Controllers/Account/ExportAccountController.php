<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Downloads only the signed-in account's profile data, used by the settings export link. */
class ExportAccountController extends Controller
{
    /** Explicitly allowlist export fields so passwords and 2FA secrets never enter the download. */
    public function __invoke(Request $request): StreamedResponse
    {
        $data = $request->user()->only([
            'id', 'display_name', 'username', 'email', 'birth_date', 'email_verified_at',
            'terms_accepted_at', 'trial_ends_at', 'created_at', 'updated_at',
        ]);

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'oasis-konto.json',
            ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store, private'],
        );
    }
}
