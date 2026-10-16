<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Attempt budgets for Livewire actions. Each Livewire method is a public endpoint, so actions that guess secrets
 * (gift codes), create orders at the payment provider or send mail are limited like the former HTTP routes were.
 * A used-up budget is shown as a form error ("throttle"), never as an error page.
 */
trait LimitsAttempts
{
    /**
     * Count one attempt for $key and stop when $max attempts happened within $decaySeconds.
     *
     * @param  string  $key  budget name plus who it belongs to, e.g. 'gift-redeem|'.$userId or 'oasis-melden|'.$ip
     *
     * @throws ValidationException when the budget is used up (nothing else runs)
     */
    protected function limitAttempts(string $key, int $max, int $decaySeconds = 60): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'throttle' => 'Zu viele Versuche in kurzer Zeit. Bitte warte '
                    .$this->waitingTime(RateLimiter::availableIn($key)).' und versuche es dann erneut.',
            ]);
        }
        RateLimiter::hit($key, $decaySeconds);
    }

    /** "eine Minute" or "N Minuten", rounded up. */
    private function waitingTime(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes === 1 ? 'eine Minute' : $minutes.' Minuten';
    }
}
