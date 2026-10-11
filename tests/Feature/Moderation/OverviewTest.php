<?php

use App\Actions\Moderation\AssignCase;
use App\Enums\Role;
use App\Models\ContentReport;
use App\Models\ModerationCase;
use App\Models\User;
use App\Queries\Moderation\QueueOverview;

/* Overview figures and latest activity on the moderation start page. */
beforeEach(fn () => $this->withoutVite());

function overviewStaff(): User
{
    return User::factory()->create(['role' => Role::Moderator, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()]);
}

function overviewCase(string $priority = 'normal'): ModerationCase
{
    $report = ContentReport::create(['url' => 'https://oasis.tld/tropfen/1', 'reason' => 'Grund', 'name' => 'N', 'email' => 'n@oasis.tld', 'truthful_confirmed_at' => now()]);
    $case = ModerationCase::where('source_id', $report->id)->sole();
    $case->forceFill(['priority' => $priority])->save();

    return $case;
}

it('counts active, unassigned, urgent and own cases and finds the oldest waiting one', function () {
    $staff = overviewStaff();
    $this->travel(-3)->hours();
    $oldest = overviewCase();
    $this->travelBack();
    overviewCase('urgent');
    app(AssignCase::class)->handle($oldest, $staff, $staff);

    $figures = app(QueueOverview::class)->figures($staff);
    expect($figures)->toMatchArray(['active' => 2, 'unassigned' => 1, 'urgent' => 1, 'mine' => 1, 'complaints' => 0, 'decided_week' => 0])
        ->and($figures['oldest_waiting']->diffInHours(now()))->toBeGreaterThanOrEqual(2.9)
        ->and($figures['median_seconds'])->toBeNull();
});

it('shows the overview and the latest activity above the queue', function () {
    $staff = overviewStaff();
    $case = overviewCase('urgent');
    app(AssignCase::class)->handle($case, $staff, $staff);

    $this->actingAs($staff)->get(route('moderation.index'))->assertOk()
        ->assertSee('Ohne Zuständigkeit')->assertSee('Dringend')->assertSee('Letzte Aktivität')
        ->assertSee('Fall #'.$case->id.' · Zugewiesen')->assertSee($staff->display_name);
});

it('shows an empty overview honestly', function () {
    $this->actingAs(overviewStaff())->get(route('moderation.index'))->assertOk()
        ->assertSee('Noch keine Aktivität.')->assertSee('keine');
});
