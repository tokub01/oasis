<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\ExportAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Downloads the signed-in account's own data as a ZIP (see ExportAccount), also in read-only mode. */
class ExportAccountController extends Controller
{
    public function __invoke(Request $request, ExportAccount $export): BinaryFileResponse
    {
        return response()->download($export->handle($request->user()), 'oasis-daten.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend();
    }
}
