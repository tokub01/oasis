<?php

/* Members under 18 (docs/oasis-privacy.md, "Unter 18"): stricter limits that hold wherever settings are read or written. */

use App\Actions\Network\PublishPost;
use App\Actions\Privacy\UpdatePrivacySetting;
use App\Enums\Privacy\Audience;
use App\Livewire\Network\Compose;
use App\Livewire\Privacy\Center;
use App\Models\PrivacySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

function minor(): User
{
    return N::member(['birth_date' => now()->subYears(16)->toDateString()]);
}

it('reads the strict limits even when looser values are stored', function () {
    $minor = minor();
    DB::table('privacy_settings')->insert(['user_id' => $minor->id, 'profile_audience' => 'everyone', 'location_audience' => 'everyone',
        'oases_audience' => 'everyone', 'chat_invites_audience' => 'members', 'default_post_visibility' => 'public', 'search_engines' => true,
        'created_at' => now(), 'updated_at' => now()]);
    $minor = $minor->fresh();
    $stranger = N::member();

    $privacy = $minor->privacy();
    expect($privacy->profile_audience)->toBe(Audience::Members)
        ->and($privacy->allows('profile_audience', null))->toBeFalse()
        ->and($privacy->allows('location_audience', null))->toBeFalse()
        ->and($privacy->allows('chat_invites_audience', $stranger))->toBeFalse()
        ->and($privacy->indexable())->toBeFalse()
        ->and($privacy->default_post_visibility->value)->toBe('members');
    $this->get(route('oasis.profil', $minor->username))->assertNotFound();
});

it('refuses looser values when a minor saves and starts a first row from the strict values', function () {
    $minor = minor();
    $update = app(UpdatePrivacySetting::class);

    foreach ([['profile_audience', 'everyone'], ['chat_invites_audience', 'members'], ['search_engines', true], ['default_post_visibility', 'public'], ['location_audience', 'everyone']] as [$key, $value]) {
        expect(fn () => $update->handle($minor, $key, $value))->toThrow(ValidationException::class);
    }
    $update->handle($minor, 'searchable', false);

    $row = PrivacySetting::where('user_id', $minor->id)->sole();
    expect($row->chat_invites_audience)->toBe(Audience::Following)->and($row->search_engines)->toBeFalse()
        ->and($row->searchable)->toBeFalse();
    $update->handle($minor, 'profile_audience', 'following');
    expect($minor->fresh()->privacy()->profile_audience)->toBe(Audience::Following);
});

it('publishes Tropfen of minors for members only and offers no public option', function () {
    $minor = minor();

    $post = app(PublishPost::class)->handle($minor, ['body' => 'Mein erster Tropfen', 'visibility' => 'public']);

    expect($post->visibility->value)->toBe('members');
    Livewire::actingAs($minor)->test(Compose::class)->assertDontSeeHtml('value="public"');
    Livewire::actingAs(N::member())->test(Compose::class)->assertSeeHtml('value="public"');
});

it('explains the limits in the privacy center and hides the choices a minor cannot make', function () {
    Livewire::actingAs(minor())->test(Center::class)
        ->assertSee('Unter 18 gilt hier eine strengere Grenze')->assertDontSee('Alle – auch ohne Konto')
        ->set('settings.profile_audience', 'everyone')->assertHasErrors('settings.profile_audience');

    Livewire::actingAs(N::member())->test(Center::class)->assertDontSee('Unter 18 gilt hier')->assertSee('Alle – auch ohne Konto');
});

it('lifts the limits on the 18th birthday without changing anything by itself', function () {
    $minor = minor();
    app(UpdatePrivacySetting::class)->handle($minor, 'profile_audience', 'following');

    $this->travel(2)->years();
    $adult = $minor->fresh();

    expect($adult->isMinor())->toBeFalse()->and($adult->privacy()->profile_audience)->toBe(Audience::Following);
    app(UpdatePrivacySetting::class)->handle($adult, 'profile_audience', 'everyone');
    expect($adult->fresh()->privacy()->allows('profile_audience', null))->toBeTrue();
});

it('brings existing data of minors under the limits with the migration', function () {
    $minor = minor();
    $adult = N::member(['birth_date' => now()->subYears(30)->toDateString()]);
    foreach ([$minor, $adult] as $user) {
        DB::table('privacy_settings')->insert(['user_id' => $user->id, 'profile_audience' => 'everyone', 'chat_invites_audience' => 'members',
            'search_engines' => true, 'default_post_visibility' => 'public', 'created_at' => now(), 'updated_at' => now()]);
        N::post($user, ['body' => 'Öffentlich von '.$user->id]);
    }

    (require database_path('migrations/2026_10_17_700000_limit_privacy_of_minors.php'))->up();

    $minorRow = DB::table('privacy_settings')->where('user_id', $minor->id)->first();
    $adultRow = DB::table('privacy_settings')->where('user_id', $adult->id)->first();
    expect([$minorRow->profile_audience, $minorRow->chat_invites_audience, (bool) $minorRow->search_engines, $minorRow->default_post_visibility])
        ->toBe(['members', 'following', false, 'members'])
        ->and([$adultRow->profile_audience, (bool) $adultRow->search_engines])->toBe(['everyone', true])
        ->and(DB::table('posts')->where('user_id', $minor->id)->value('visibility'))->toBe('members')
        ->and(DB::table('posts')->where('user_id', $adult->id)->value('visibility'))->toBe('public');
});
