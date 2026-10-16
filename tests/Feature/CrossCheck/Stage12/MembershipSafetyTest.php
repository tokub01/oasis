<?php

/*
 * Stage 12 cross-check (Claude reviews Antigravity's area, finished by Codex): membership, safety, votes and account
 * settings as Livewire components. Written against the behaviour of the former controllers (d9ad74f), with
 * manipulated properties and direct method calls that bypass the rendered page.
 */

use App\Livewire\Account\Settings;
use App\Livewire\Governance\Votes;
use App\Livewire\Membership\Cancel;
use App\Livewire\Membership\Checkout;
use App\Livewire\Membership\Overview;
use App\Livewire\Membership\Withdraw;
use App\Livewire\Safety\SafetyOverview;
use App\Models\Block;
use App\Models\GiftMembership;
use App\Models\MembershipPayment;
use App\Models\Mute;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteParticipation;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Mollie\Laravel\Facades\Mollie;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    config(['oasis.checkout_enabled' => true]);
});

/** A paying member with an active Mollie subscription created $daysAgo days ago. */
function stage12Subscriber(int $daysAgo = 3): User
{
    $user = N::member(['mollie_customer_id' => 'cst_s12_'.fake()->unique()->numberBetween(1, 99999)]);
    Subscription::create(['user_id' => $user->id, 'type' => 'oase', 'mollie_id' => 'sub_s12_'.$user->id,
        'status' => 'active', 'paid_until' => now()->addMonth(), 'created_at' => now()->subDays($daysAgo)]);

    return $user;
}

/** An open vote with two options. */
function stage12Vote(array $attributes = []): Vote
{
    $vote = Vote::create($attributes + ['title' => 'Testfrage', 'description' => 'Worum es geht.', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'created_by' => N::member()->id]);
    $vote->options()->createMany([['label' => 'Ja', 'position' => 1], ['label' => 'Nein', 'position' => 2]]);

    return $vote;
}

describe('Checkout /mitglied-werden', function () {
    it('charges the configured price for a fixed tier even when a large Mäzen amount was injected', function () {
        $user = N::member(['mollie_customer_id' => 'cst_fixed']);
        Mollie::fake([CreatePaymentRequest::class => MockResponse::created(['resource' => 'payment', 'id' => 'tr_fixed',
            '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/fixed']]])]);

        Livewire::actingAs($user)->test(Checkout::class)
            ->set('stufe', 'solidar')->set('betrag', '999')->set('stufe', 'solidar')->set('rhythmus', 'monat')
            ->call('checkout')->assertHasNoErrors()->assertRedirect('https://www.mollie.com/checkout/fixed');

        Mollie::assertSent(fn ($request) => $request->payload()->get('amount')['value'] === '3.00');
        expect(MembershipPayment::sole()->amount)->toBe(300);
    });

    it('rejects manipulated tier, rhythm and payment method without contacting Mollie', function (string $field, string $value) {
        Mollie::fake();
        Livewire::actingAs(N::member())->test(Checkout::class)->set($field, $value)->call('checkout')->assertHasErrors($field);

        Mollie::assertSentCount(0);
        expect(MembershipPayment::count())->toBe(0);
    })->with([
        'tier' => ['stufe', 'gratis'],
        'rhythm' => ['rhythmus', 'woche'],
        'payment method' => ['zahlart', 'bitcoin'],
    ]);

    it('rejects a Mäzen amount below the minimum and fractional euros', function (string $amount) {
        Mollie::fake();
        Livewire::actingAs(N::member())->test(Checkout::class)->set('betrag', $amount)->call('checkout')->assertHasErrors('betrag');
        Mollie::assertSentCount(0);
    })->with(['below minimum' => ['1'], 'fraction' => ['12.50'], 'negative' => ['-20'], 'empty' => ['']]);

    it('sends guests to registration without creating an order', function () {
        Mollie::fake();
        Livewire::test(Checkout::class)->call('checkout')->assertRedirect(route('register'));
        Mollie::assertSentCount(0);
        expect(MembershipPayment::count())->toBe(0);
    });

    it('does not open a second paid membership for a running subscriber', function () {
        Mollie::fake();
        Livewire::actingAs(stage12Subscriber())->test(Checkout::class)->call('checkout')
            ->assertNoRedirect()->assertSet('error', fn ($error) => $error !== '');
        Mollie::assertSentCount(0);
        expect(MembershipPayment::count())->toBe(0);
    });

    it('is unreachable, also by a direct action call, while checkout is switched off', function () {
        $component = Livewire::actingAs(N::member())->test(Checkout::class);
        config(['oasis.checkout_enabled' => false]);
        Mollie::fake();

        $component->call('checkout')->assertStatus(404);
        Mollie::assertSentCount(0);
        $this->get(route('oasis.mitglied'))->assertNotFound();
    });

    it('keeps the locked error banner out of client control', function () {
        expect(fn () => Livewire::actingAs(N::member())->test(Checkout::class)->set('error', '<b>gefälscht</b>'))
            ->toThrow(Exception::class);
    });
});

describe('Membership overview, cancellation and withdrawal', function () {
    it('refuses gift checkout for a manipulated tier', function () {
        Mollie::fake();
        Livewire::actingAs(N::member())->test(Overview::class)->call('buyGift', 'platin')->assertHasErrors('tier');
        Mollie::assertSentCount(0);
    });

    it('does not let a redeemed voucher be redeemed again by someone else', function () {
        $gift = GiftMembership::create(['code' => 'OASIS-ABCD-EFGH', 'tier' => 'oase', 'months' => 12, 'amount' => 5000,
            'currency' => 'EUR', 'purchaser_user_id' => N::member()->id]);
        $first = N::member();
        Livewire::actingAs($first)->test(Overview::class)->set('code', ' oasis-abcd-efgh ')->call('redeem')->assertHasNoErrors();

        Livewire::actingAs(N::member())->test(Overview::class)->set('code', 'OASIS-ABCD-EFGH')->call('redeem')
            ->assertHasErrors(['code' => 'Dieser Gutscheincode wurde bereits eingelöst.']);
        expect($gift->fresh()->redeemed_by_user_id)->toBe($first->id);
    });

    it('does not leak the Mollie customer id or foreign voucher codes into the overview snapshot', function () {
        $user = stage12Subscriber();
        GiftMembership::create(['code' => 'OASIS-FREM-DEXX', 'tier' => 'oase', 'months' => 12, 'amount' => 5000,
            'currency' => 'EUR', 'purchaser_user_id' => N::member()->id]);

        $component = Livewire::actingAs($user)->test(Overview::class);
        expect(json_encode($component->snapshot))->not->toContain($user->mollie_customer_id)->not->toContain('sub_s12_');
        $component->assertDontSee('OASIS-FREM-DEXX');
    });

    it('re-checks the 14-day withdrawal period on the action, not only when the page was opened', function () {
        $user = stage12Subscriber(daysAgo: 10);
        $component = Livewire::actingAs($user)->test(Withdraw::class);
        $this->travel(6)->days();
        Mollie::fake();

        $component->call('withdraw')->assertRedirect(route('app.membership'));
        expect(session('error'))->toContain('Widerrufsfrist')
            ->and($user->subscription()->fresh()->status)->toBe('active');
        Mollie::assertSentCount(0);
    });

    it('does not cancel or withdraw anything once the subscription is gone after the page was opened', function (string $component, string $method) {
        $user = stage12Subscriber();
        $page = Livewire::actingAs($user)->test($component);
        $user->subscription()->delete();
        Mollie::fake();

        $page->call($method)->assertRedirect(route('app.membership'));
        Mollie::assertSentCount(0);
        Mail::assertNothingSent();
    })->with([
        'cancel' => [Cancel::class, 'cancel'],
        'withdraw' => [Withdraw::class, 'withdraw'],
    ]);

    it('serves invoices only to their owner', function () {
        $owner = N::member();
        $payment = MembershipPayment::create(['user_id' => $owner->id, 'kind' => 'membership', 'tier' => 'oase',
            'rhythm' => 'monat', 'amount' => 500, 'status' => 'paid', 'paid_at' => now(), 'mollie_payment_id' => 'tr_inv']);

        $this->actingAs(N::member())->get(route('app.membership.invoice', $payment))->assertNotFound();
    });
});

describe('Safety overview', function () {
    it('lifts only the caller\'s own block and mute, never those of other members', function () {
        $me = N::member();
        $other = N::member();
        $target = N::member();
        Block::create(['user_id' => $other->id, 'blocked_user_id' => $target->id]);
        Mute::create(['user_id' => $other->id, 'muted_user_id' => $target->id]);

        // Foreign rows are unknown to the caller: 404, and no display name ends up in a status message.
        Livewire::actingAs($me)->test(SafetyOverview::class)->call('unblock', $target->id)->assertStatus(404);
        Livewire::actingAs($me)->test(SafetyOverview::class)->call('unmute', $target->id)->assertStatus(404);

        expect(Block::where('user_id', $other->id)->count())->toBe(1)
            ->and(Mute::where('user_id', $other->id)->count())->toBe(1);
    });

    it('offers no blocking or muting endpoint on the overview, only lifting', function () {
        expect(method_exists(SafetyOverview::class, 'block'))->toBeFalse()
            ->and(method_exists(SafetyOverview::class, 'mute'))->toBeFalse();
    });

    it('keeps the status banner locked', function () {
        expect(fn () => Livewire::actingAs(N::member())->test(SafetyOverview::class)->set('status', 'x'))->toThrow(Exception::class);
    });
});

describe('Member votes', function () {
    it('counts one secret ballot per member and refuses a second one', function () {
        $vote = stage12Vote();
        $member = stage12Subscriber();
        $yes = $vote->options->first();

        Livewire::actingAs($member)->test(Votes::class)->set('options.'.$vote->id, $yes->id)->call('cast', $vote->id)->assertHasNoErrors();
        Livewire::actingAs($member)->test(Votes::class)->set('options.'.$vote->id, $yes->id)->call('cast', $vote->id)->assertHasErrors('option');

        expect($yes->fresh()->votes_count)->toBe(1)->and(VoteParticipation::count())->toBe(1);
    });

    it('refuses ballots on ended votes and from members without membership', function () {
        $ended = stage12Vote(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
        Livewire::actingAs(stage12Subscriber())->test(Votes::class)->call('cast', $ended->id, $ended->options->first()->id)
            ->assertHasErrors('option');

        $open = stage12Vote();
        Livewire::actingAs(N::readOnlyMember())->test(Votes::class)->call('cast', $open->id, $open->options->first()->id)
            ->assertStatus(403);
        expect(VoteParticipation::count())->toBe(0);
    });
});

describe('Account settings', function () {
    it('deletes nothing without a recent password confirmation and sends the member to confirm it', function () {
        $user = N::member();
        Livewire::actingAs($user)->test(Settings::class)->set('confirm', true)->call('deleteAccount')
            ->assertRedirect(route('password.confirm'));

        expect(User::find($user->id))->not->toBeNull();
    });

    it('requires the explicit checkbox even with a confirmed password', function () {
        $user = N::member();
        $this->withSession(['auth.password_confirmed_at' => time()]);
        Livewire::actingAs($user)->test(Settings::class)->call('deleteAccount')->assertHasErrors('confirm');

        expect(User::find($user->id))->not->toBeNull();
    });

    it('requires a recent password confirmation for every two-factor change', function (string $method) {
        $user = N::member();
        Livewire::actingAs($user)->test(Settings::class)->call($method)->assertRedirect(route('password.confirm'));
        expect($user->fresh()->two_factor_secret)->toBeNull();
    })->with(['enableTwoFactor', 'confirmTwoFactor', 'regenerateRecoveryCodes', 'disableTwoFactor']);

    it('does not take over another account\'s email address', function () {
        $taken = N::member(['email' => 'vergeben@example.tld']);
        $user = N::member();
        Livewire::actingAs($user)->test(Settings::class)->set('email', $taken->email)->call('updateProfile')->assertHasErrors('email');
        expect($user->fresh()->email)->not->toBe($taken->email);
    });

    it('does not keep a rejected current password in the component snapshot', function () {
        $component = Livewire::actingAs(N::member())->test(Settings::class)
            ->set('current_password', 'falsches-passwort-123')->set('password', 'neues-Passwort-1234')
            ->set('password_confirmation', 'neues-Passwort-1234')->call('updatePassword')->assertHasErrors('current_password');

        expect(json_encode($component->snapshot))->not->toContain('falsches-passwort-123')->not->toContain('neues-Passwort-1234');
    });
});
