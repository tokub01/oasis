<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * /.well-known/security.txt (RFC 9116): where security researchers report vulnerabilities. Generated, so the required
 * "Expires" date never runs out; it always lies 180 days ahead (the RFC asks for less than a year).
 */
class SecurityTxtController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'Contact: mailto:'.config('oasis.mail.security'),
            'Expires: '.now()->utc()->addDays(180)->startOfDay()->format('Y-m-d\TH:i:s\Z'),
            'Preferred-Languages: de, en',
            'Canonical: '.url('/.well-known/security.txt'),
            'Policy: '.route('oasis.sicherheit').'#luecke-titel',
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=86400']);
    }
}
