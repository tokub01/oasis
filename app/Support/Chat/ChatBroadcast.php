<?php

namespace App\Support\Chat;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Dispatches chat events after commit; history remains authoritative during a transport outage. */
class ChatBroadcast
{
    /** Keep network failures from causing retries/duplicates of an already committed message. */
    public static function afterCommit(object $event): void
    {
        DB::afterCommit(function () use ($event): void {
            try {
                event($event);
            } catch (Throwable $exception) {
                // Exceptions from transports can include request bodies/credentials: log neither.
                Log::warning('Chat broadcast failed.', ['event' => $event::class, 'error_type' => $exception::class]);
            }
        });
    }
}
