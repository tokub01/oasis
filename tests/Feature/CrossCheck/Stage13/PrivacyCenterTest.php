<?php

/*
 * Stage 13 cross-check (independent reviewer): attacks the Privatsphäre-Center and the guided check as endpoints
 * (manipulated keys, value types, whole-array updates, step, protected methods), checks own-account-only writes,
 * consistency of the privacy-by-default values between model and migration, and the promises of the help texts.
 * Tests named "documents …" pin current behaviour that the review lists as a finding.
 */

use App\Actions\Privacy\UpdatePrivacySetting;
use App\Enums\Privacy\Audience;
use App\Livewire\Privacy\Center;
use App\Livewire\Privacy\Checkup;
use App\Models\PrivacySetting;
use App\Models\User;
use App\Support\Privacy\PrivacyOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

/** Class of what $action throws, or null – Pest's toThrow() cannot match interfaces such as Throwable. */
function thrownBy(Closure $action): ?string
{
    try {
        $action();
    } catch (Throwable $thrown) {
        return $thrown::class;
    }

    return null;
}

describe('opening writes nothing', function () {
    it('renders both pages over HTTP without storing a row', function (string $route) {
        $this->actingAs(N::member())->get(route($route))->assertOk();

        expect(PrivacySetting::count())->toBe(0);
    })->with(['app.privacy', 'app.privacy.check']);

    it('walks the whole check without changing anything and still stores nothing', function () {
        $this->actingAs(N::member());

        Livewire::test(Checkup::class)->call('next')->call('next')->call('next')->call('back')->call('next');

        expect(PrivacySetting::count())->toBe(0);
    });

    it('lets a read-only member (trial ended) open and change the settings – data protection is not a paid feature', function () {
        $me = N::readOnlyMember();
        $this->actingAs($me)->get(route('app.privacy'))->assertOk();

        Livewire::test(Center::class)->set('settings.chat_invites_audience', 'nobody')->assertHasNoErrors();

        expect($me->fresh()->privacy()->chat_invites_audience)->toBe(Audience::Nobody);
    });
});

describe('manipulated keys', function () {
    it('refuses columns that exist on the table but are not offered', function (string $key, mixed $value) {
        $me = N::member();
        $other = N::member();
        $this->actingAs($me);

        Livewire::test(Center::class)->set('settings.'.$key, $value)->assertStatus(422);

        expect(PrivacySetting::count())->toBe(0)->and($other->fresh()->privacySetting)->toBeNull();
    })->with([
        'user_id' => ['user_id', 1],
        'id' => ['id', 1],
        'created_at' => ['created_at', '2000-01-01'],
        'case variant' => ['Profile_Audience', 'everyone'],
    ]);

    it('documents: a path below a string setting fails inside Livewire (500) before the hook, without writing', function () {
        $this->actingAs(N::member());

        expect(thrownBy(fn () => Livewire::test(Center::class)->set('settings.profile_audience.value', 'everyone')))
            ->toBe(Exception::class);
        expect(PrivacySetting::count())->toBe(0);
    });

    it('never writes another account: user_id cannot be smuggled through a stored change', function () {
        $me = N::member();
        $victim = N::member();
        $this->actingAs($me);

        Livewire::test(Center::class)->set('settings.profile_audience', 'everyone')
            ->set('settings.user_id', $victim->id)->assertStatus(422);

        expect(PrivacySetting::count())->toBe(1)
            ->and(PrivacySetting::first()->user_id)->toBe($me->id)
            ->and($victim->fresh()->privacySetting)->toBeNull();
    });

    it('rejects unknown keys in the action itself, not only in the component', function () {
        $me = N::member();

        expect(fn () => app(UpdatePrivacySetting::class)->handle($me, 'user_id', 2))->toThrow(InvalidArgumentException::class);
        expect(PrivacySetting::count())->toBe(0);
    });

    it('refuses calling the protected saving helpers directly', function (string $method) {
        $this->actingAs(N::member());

        expect(thrownBy(fn () => Livewire::test(Center::class)->call($method, 'profile_audience', 'everyone')))->not->toBeNull();
        expect(PrivacySetting::count())->toBe(0);
    })->with(['saveSetting', 'loadSettings']);
});

describe('manipulated values', function () {
    it('refuses non-string audience values and keeps the stored choice', function (mixed $value) {
        $me = N::member();
        $this->actingAs($me);

        Livewire::test(Center::class)->set('settings.carry_audience', $value)
            ->assertHasErrors('settings.carry_audience')->assertSet('settings.carry_audience', 'members');

        expect(PrivacySetting::count())->toBe(0);
    })->with([
        'array' => [['everyone']],
        'assoc array' => [['value' => 'nobody']],
        'int' => [1],
        'bool' => [true],
        'null' => [null],
        'empty string' => [''],
        'enum name instead of value' => ['Nobody'],
        'padded' => [' nobody'],
    ]);

    it('refuses non-boolean switch values', function (mixed $value) {
        $this->actingAs(N::member());

        Livewire::test(Center::class)->set('settings.search_engines', $value)
            ->assertHasErrors('settings.search_engines')->assertSet('settings.search_engines', false);

        expect(PrivacySetting::count())->toBe(0);
    })->with(['on' => ['on'], 'yes' => ['yes'], 'array' => [[true]], 'two' => [2], 'null' => [null]]);

    it('stores loosely typed booleans that Laravel accepts as real booleans', function (mixed $value, bool $expected) {
        $me = N::member();
        $this->actingAs($me);

        Livewire::test(Center::class)->set('settings.searchable', $value)->assertHasNoErrors()
            ->assertSet('settings.searchable', $expected);

        expect($me->fresh()->privacy()->searchable)->toBe($expected)
            ->and(DB::table('privacy_settings')->value('searchable'))->toBe($expected ? 1 : 0);
    })->with(['string 0' => ['0', false], 'int 0' => [0, false], 'string 1' => ['1', true]]);

    it('accepts every offered choice of every setting and round-trips it', function () {
        $me = N::member();
        $this->actingAs($me);
        $component = Livewire::test(Center::class);

        foreach (PrivacyOptions::keys() as $key) {
            $choices = PrivacyOptions::isSwitch($key) ? [true, false] : array_keys(PrivacyOptions::choices($key));
            foreach ($choices as $choice) {
                $component->set('settings.'.$key, $choice)->assertHasNoErrors()->assertSet('settings.'.$key, $choice);
                expect(PrivacyOptions::value($me->fresh()->privacy(), $key))->toBe($choice);
            }
        }
        expect(PrivacySetting::count())->toBe(1);
    });

    it('refuses replacing the whole settings array with 422 instead of failing', function () {
        $this->actingAs(N::member());

        Livewire::test(Center::class)->set('settings', ['profile_audience' => 'everyone'])->assertStatus(422);
        expect(PrivacySetting::count())->toBe(0);
    });

    it('keeps the confirmation text server-side only', function () {
        $this->actingAs(N::member());

        expect(thrownBy(fn () => Livewire::test(Center::class)->set('status', 'Gespeichert: alles öffentlich')))
            ->toBe(CannotUpdateLockedPropertyException::class);
    });

    it('clears an older success message when a value is refused', function () {
        $this->actingAs(N::member());

        Livewire::test(Center::class)->set('settings.searchable', false)
            ->set('settings.mentions_audience', 'everyone')->assertHasErrors('settings.mentions_audience')
            ->assertDontSee('Gespeichert: In der Oasis-Suche auffindbar');
    });
});

describe('guided check', function () {
    it('does not let the client choose the step', function (mixed $step) {
        $this->actingAs(N::member());

        expect(thrownBy(fn () => Livewire::test(Checkup::class)->set('step', $step)))->toBe(CannotUpdateLockedPropertyException::class);
    })->with([0, 4, PHP_INT_MAX, 'summary']);

    it('stays within the steps when walking past either end', function () {
        $this->actingAs(N::member());

        Livewire::test(Checkup::class)->call('back')->assertSet('step', 1)
            ->call('next')->call('next')->call('next')->call('next')->call('next')->assertSet('step', 4)->assertOk();
    });

    it('rejects manipulated keys in the check too and keeps the summary readable', function () {
        $this->actingAs(N::member());

        Livewire::test(Checkup::class)->set('settings.default_post_visibility', ['public'])
            ->assertHasErrors('settings.default_post_visibility')
            ->call('next')->call('next')->call('next')->assertSee('Alles im Blick')->assertSee('Nur für Mitglieder');
        expect(PrivacySetting::count())->toBe(0);
    });

    it('shows a saved change in the summary of the same session', function () {
        $this->actingAs(N::member());

        Livewire::test(Checkup::class)->set('settings.search_engines', true)->call('next')->call('next')->call('next')
            ->assertSeeHtml('Öffentliches Profil in Suchmaschinen</dt>')
            ->assertSeeHtml('<dd class="m-0 font-bold text-night">Ja</dd>');
    });

    it('links export, blocked/muted and account deletion from the summary', function () {
        $this->actingAs(N::member());

        Livewire::test(Checkup::class)->call('next')->call('next')->call('next')
            ->assertSee(route('account.export'), false)->assertSee(route('safety.index'), false)
            ->assertSee(route('app.settings').'#loeschen-titel', false);
    });
});

describe('privacy by default', function () {
    it('uses the same defaults in the model as in the database columns', function () {
        $member = N::member();
        DB::table('privacy_settings')->insert(['user_id' => $member->id, 'created_at' => now(), 'updated_at' => now()]);
        $stored = $member->fresh()->privacySetting;
        $unsaved = N::member()->privacy();

        foreach (PrivacyOptions::keys() as $key) {
            expect(PrivacyOptions::value($stored, $key))->toBe(PrivacyOptions::value($unsaved, $key), $key);
        }
        expect($unsaved->search_engines)->toBeFalse()->and($unsaved->profile_audience)->toBe(Audience::Members);
    });

    it('offers only defaults that are themselves valid choices', function () {
        $defaults = N::member()->privacy();

        foreach (PrivacyOptions::keys() as $key) {
            $valid = Validator::make(['v' => PrivacyOptions::value($defaults, $key)], ['v' => PrivacyOptions::rule($key)])->passes();
            expect($valid)->toBeTrue($key);
        }
    });

    it('refuses allows() for settings that are not audiences', function (string $key) {
        expect(fn () => N::member()->privacy()->allows($key, N::member()))->toThrow(InvalidArgumentException::class);
    })->with(['searchable', 'default_post_visibility']);

    it('keeps the update atomic per account: a second change updates the same row', function () {
        $me = N::member();
        app(UpdatePrivacySetting::class)->handle($me, 'replies_audience', 'nobody');
        app(UpdatePrivacySetting::class)->handle($me, 'carry_audience', 'following');

        expect(PrivacySetting::where('user_id', $me->id)->count())->toBe(1)
            ->and($me->fresh()->privacy()->replies_audience)->toBe(Audience::Nobody)
            ->and($me->fresh()->privacy()->carry_audience)->toBe(Audience::Following);
        expect(fn () => app(UpdatePrivacySetting::class)->handle($me, 'carry_audience', 'everyone'))
            ->toThrow(ValidationException::class);
    });
});

describe('promises of the help texts', function () {
    it('keeps @name plain text for an author outside the mentions audience, also on the post page', function () {
        $target = N::member(['username' => 'quiet_target']);
        app(UpdatePrivacySetting::class)->handle($target, 'mentions_audience', 'nobody');
        $post = N::post(N::member(), ['body' => 'Hallo @quiet_target!']);

        $this->actingAs(N::member())->get(route('network.posts.show', $post))->assertOk()
            ->assertSee('@quiet_target')->assertDontSee(route('oasis.profil', 'quiet_target'), false);
    });

    it('turns @name into a link once the author is allowed', function () {
        N::member(['username' => 'open_target']);
        $post = N::post(N::member(), ['body' => 'Hallo @open_target!']);

        $this->actingAs(N::member())->get(route('network.posts.show', $post))->assertOk()
            ->assertSee(route('oasis.profil', 'open_target'), false);
    });

    it('keeps a guest view of a public Tropfen out of search engines by default', function () {
        $post = N::post(N::member());

        $this->get(route('network.posts.show', $post))->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    });

    it('documents: a closed profile does not hide the Tropfen it "shows" – they stay readable on their own page', function () {
        $author = N::member();
        app(UpdatePrivacySetting::class)->handle($author, 'profile_audience', 'following');
        $post = N::post($author, ['body' => 'Nur für meinen Kreis gedacht', 'visibility' => 'members']);
        $stranger = N::member();

        $this->actingAs($stranger)->get(route('oasis.profil', $author->username))->assertNotFound();
        $this->actingAs($stranger)->get(route('network.posts.show', $post))->assertOk()->assertSee('Nur für meinen Kreis gedacht');
    });

    it('lets the owner pass their own "Niemand" setting also when the row is read back from the database', function () {
        $owner = N::member();
        app(UpdatePrivacySetting::class)->handle($owner, 'replies_audience', 'nobody');

        expect(User::find($owner->id)->privacy()->allows('replies_audience', User::find($owner->id)))->toBeTrue();
    });

    it('keeps public Tropfen of a members-only profile out of search engines despite an enabled indexing preference', function () {
        $author = N::member();
        app(UpdatePrivacySetting::class)->handle($author, 'search_engines', true);
        expect($author->fresh()->privacy()->profile_audience)->toBe(Audience::Members);
        $post = N::post($author);

        $this->get(route('network.posts.show', $post))->assertOk()->assertSee('name="robots" content="noindex', false);
        $this->get(route('oasis.profil', $author->username))->assertNotFound()->assertSee('name="robots" content="noindex', false);
    });
});
