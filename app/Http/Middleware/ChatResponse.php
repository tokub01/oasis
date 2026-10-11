<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Chat APIs always use JSON/no-store and never flash input back into the authentication session. */
class ChatResponse
{
    /** Bound request sizes before parsing ciphertext arrays; mark normal/error responses noncacheable. */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        abort_if(strlen($request->getContent()) > 32768, 413, 'Die Anfrage ist zu groß.');
        // Do not silently accept plaintext/passphrases beside an otherwise valid encrypted object.
        $allowed = match ($request->route()?->getName()) {
            'chat.api.identity.update' => ['locked_identity', 'public_keys'],
            'chat.api.conversations.store' => ['type', 'title', 'member_ids', 'lifetime_days'],
            'chat.api.conversations.update' => ['title', 'lifetime_days'],
            'chat.api.members.store' => ['user_id'],
            'chat.api.grants.store' => ['key_version', 'grants'],
            'chat.api.rotation.store' => ['key_version'],
            'chat.api.messages.store' => ['envelope'],
            'chat.api.reports.store' => ['envelope', 'plaintext', 'reason'],
            default => null,
        };
        if ($allowed !== null && array_diff(array_keys($request->all()), [...$allowed, '_token'])) {
            throw ValidationException::withMessages(['payload' => 'Die Anfrage enthält unerlaubte Datenfelder.']);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
