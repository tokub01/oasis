<?php

use App\Actions\Governance\ManageVotes;
use App\Livewire\Account\Settings;
use App\Livewire\Governance\Votes;
use App\Livewire\Membership\Cancel;
use App\Livewire\Membership\Checkout;
use App\Livewire\Membership\Overview;
use App\Livewire\Membership\Portal;
use App\Livewire\Membership\Withdraw;
use App\Livewire\Safety\Bookmarks;
use App\Livewire\Safety\SafetyOverview;
use App\Models\GiftMembership;
use App\Models\VoteParticipation;
use App\Services\Membership\MollieMembershipService;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

test('stage12 private actions reauthorize after authentication is lost', function (string $class, string $method, array $arguments) {
    $actor = N::member();
    $actor->subscriptions()->create(['type' => 'oase', 'mollie_id' => 'sub_crosscheck', 'status' => 'active', 'paid_until' => now()->addMonth()]);
    $component = Livewire::actingAs($actor)->test($class);
    auth()->logout();
    $component->call($method, ...$arguments)->assertForbidden();
})->with([
    [SafetyOverview::class, 'unblock', [1]],
    [SafetyOverview::class, 'unmute', [1]],
    [Overview::class, 'redeem', []],
    [Overview::class, 'buyGift', []],
    [Portal::class, 'updatePaymentMethod', []],
    [Cancel::class, 'cancel', []],
    [Withdraw::class, 'withdraw', []],
    [Settings::class, 'updateProfile', []],
    [Settings::class, 'updatePassword', []],
    [Settings::class, 'enableTwoFactor', []],
    [Settings::class, 'confirmTwoFactor', []],
    [Settings::class, 'disableTwoFactor', []],
    [Settings::class, 'regenerateRecoveryCodes', []],
    [Settings::class, 'deleteAccount', []],
    [Votes::class, 'cast', [1, 1]],
]);

test('stage12 sensitive settings cannot bypass recent password confirmation', function (string $method) {
    $actor = N::member();
    Livewire::actingAs($actor)->test(Settings::class)->set('confirm', true)->call($method)
        ->assertRedirect(route('password.confirm'));
    expect($actor->fresh())->not->toBeNull();
    expect($actor->fresh()->two_factor_secret)->toBeNull();
})->with(['deleteAccount', 'enableTwoFactor', 'disableTwoFactor', 'regenerateRecoveryCodes']);

test('stage12 recovery-code display cannot be enabled by a forged public property', function () {
    $component = Livewire::actingAs(N::member())->test(Settings::class);
    expect(fn () => $component->set('showRecoveryCodes', true))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('stage12 bookmark queries remain private after live refresh', function () {
    $owner = N::member();
    $other = N::member();
    $post = N::post(N::member(), ['body' => 'Nur fuer die private Merkliste']);
    $owner->bookmarks()->create(['post_id' => $post->id]);
    Livewire::actingAs($other)->test(Bookmarks::class)->call('$refresh')->assertDontSee($post->body);
});

test('stage12 ballots reject an option belonging to another vote without recording participation', function () {
    $actor = N::member();
    GiftMembership::factory()->create(['redeemed_by_user_id' => $actor->id, 'redeemed_at' => now(), 'expires_at' => now()->addYear()]);
    $input = ['title' => 'Wohin mit der Ruecklage?', 'description' => 'Ein gemeinsamer Beschluss.', 'starts_at' => now()->subDay()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(), 'options' => ['A', 'B']];
    $vote = app(ManageVotes::class)->create($actor, $input);
    $other = app(ManageVotes::class)->create($actor, $input);
    Livewire::actingAs($actor)->test(Votes::class)->call('cast', $vote->id, $other->options()->first()->id)->assertHasErrors('option');
    expect(VoteParticipation::count())->toBe(0);
});

test('stage12 checkout rejects fractional euros instead of silently charging a rounded amount', function () {
    config(['oasis.checkout_enabled' => true]);
    $service = Mockery::mock(MollieMembershipService::class)->makePartial();
    $service->shouldNotReceive('createSubscriptionCheckout');
    app()->instance(MollieMembershipService::class, $service);
    Livewire::actingAs(N::member())->test(Checkout::class)->set('stufe', 'maezen')->set('betrag', '12.50')
        ->call('checkout')->assertHasErrors('betrag');
});
