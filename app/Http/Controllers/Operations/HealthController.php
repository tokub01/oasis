<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\Operations\HealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /gesundheit for uptime monitors (docs/oasis-operations.md): 200 when every check passes, 503 otherwise, with the
 * results as JSON. Only with the secret `OASIS_HEALTH_TOKEN` (header X-Health-Token or ?token=); without a configured
 * or matching token the URL does not exist (404), so outsiders learn nothing about the system.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request, HealthCheck $health): JsonResponse
    {
        $token = (string) config('oasis.operations.health_token');
        $given = (string) ($request->header('X-Health-Token') ?? $request->query('token', ''));
        abort_if($token === '' || ! hash_equals($token, $given), 404);

        $results = $health->run();
        $healthy = HealthCheck::healthy($results);

        return response()->json([
            'status' => $healthy ? 'ok' : 'problem',
            'checked_at' => now()->toIso8601String(),
            'checks' => collect($results)->map(fn (array $result) => ['ok' => $result['ok'], 'detail' => $result['detail']])->all(),
        ], $healthy ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
