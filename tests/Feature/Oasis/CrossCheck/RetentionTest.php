<?php

/*
 * The privacy policy promises automatic deletion after the periods in config('oasis.privacy'). These tests prove
 * that `model:prune` deletes exactly the expired rows, and deletes nothing while a period is not decided (null).
 */

use App\Models\CancellationRequest;
use App\Models\ContentReport;
use App\Models\OrganisationRequest;
use App\Models\WaitlistEntry;
use App\Models\WithdrawalRequest;

function cancellationAt(string $createdAt): CancellationRequest
{
    $row = CancellationRequest::create([
        'name' => 'A', 'email' => 'a@example.tld', 'contract' => 'Oasis-Mitgliedschaft',
        'type' => 'ordentlich', 'timing' => 'naechst',
    ]);
    $row->forceFill(['created_at' => $createdAt])->save();

    return $row;
}

it('deletes nothing while the retention periods are not decided', function () {
    config(['oasis.privacy.records_years' => null, 'oasis.privacy.unconfirmed_waitlist_days' => null]);
    cancellationAt(now()->subYears(20)->toDateTimeString());
    WaitlistEntry::create(['email' => 'w@example.tld', 'consented_at' => now(), 'token' => str_repeat('a', 64)])
        ->forceFill(['created_at' => now()->subYears(5)])->save();

    $this->artisan('model:prune')->assertSuccessful();

    expect(CancellationRequest::count())->toBe(1)->and(WaitlistEntry::count())->toBe(1);
});

it('deletes form records after the configured number of years', function () {
    config(['oasis.privacy.records_years' => 3]);
    $old = cancellationAt(now()->subYears(3)->subDay()->toDateTimeString());
    $recent = cancellationAt(now()->subYears(2)->toDateTimeString());

    $this->artisan('model:prune')->assertSuccessful();

    expect(CancellationRequest::find($old->id))->toBeNull()
        ->and(CancellationRequest::find($recent->id))->not->toBeNull();
});

it('applies the record retention to all four form tables', function (string $model) {
    expect(method_exists($model, 'prunable'))->toBeTrue();
})->with([CancellationRequest::class, WithdrawalRequest::class, ContentReport::class, OrganisationRequest::class]);

it('deletes only unconfirmed waitlist entries after the configured days', function () {
    config(['oasis.privacy.unconfirmed_waitlist_days' => 30]);
    $unconfirmed = WaitlistEntry::create(['email' => 'u@example.tld', 'consented_at' => now(), 'token' => str_repeat('u', 64)]);
    $unconfirmed->forceFill(['created_at' => now()->subDays(31)])->save();
    $confirmed = WaitlistEntry::create(['email' => 'c@example.tld', 'consented_at' => now(), 'token' => str_repeat('c', 64), 'confirmed_at' => now()]);
    $confirmed->forceFill(['created_at' => now()->subDays(31)])->save();

    $this->artisan('model:prune')->assertSuccessful();

    expect(WaitlistEntry::find($unconfirmed->id))->toBeNull()
        ->and(WaitlistEntry::find($confirmed->id))->not->toBeNull();
});

it('schedules the pruning daily', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('model:prune')->assertSuccessful();
});
