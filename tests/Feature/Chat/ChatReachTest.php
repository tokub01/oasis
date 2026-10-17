<?php

/* "Schreiben" on oasis member lists and profiles (App\Support\Chat\ChatReach): only where the chat would allow it. */

use App\Livewire\Network\OasisShow;
use App\Models\Block;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Oasis;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\ChatFixtures as F;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

/** A public oasis with the given members (joined directly). */
function oasisWith(User ...$members): Oasis
{
    $oasis = N::oasis($members[0]);
    foreach (array_slice($members, 1) as $member) {
        $oasis->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
    }

    return $oasis;
}

it('lists the other members of an oasis with a write button for those who accept messages', function () {
    [$me] = F::user();
    [$mira] = F::user();
    $noChat = N::member();
    [$closed] = F::user();
    $closed->privacySetting()->create(['chat_invites_audience' => 'nobody', 'oasis_list_audience' => 'members']);
    $mira->privacySetting()->create(['oasis_list_audience' => 'members']);
    $noChat->privacySetting()->create(['oasis_list_audience' => 'members']);
    $oasis = oasisWith($me, $mira, $noChat, $closed);

    Livewire::actingAs($me)->test(OasisShow::class, ['oasis' => $oasis])
        ->assertSee($mira->display_name)->assertSee($noChat->display_name)->assertSee($closed->display_name)
        ->assertSee('#neu/'.$mira->username, false)
        ->assertDontSee('#neu/'.$noChat->username, false)->assertDontSee('#neu/'.$closed->username, false)
        ->set('memberQuery', mb_substr($mira->display_name, 0, 4))->assertSee($mira->display_name)->assertDontSee($noChat->display_name);
});

it('filters members by their contact filter, by default showing them only to friends and people they follow', function () {
    [$me] = F::user();
    $stranger = N::member();
    $followsMe = N::member();
    $friend = N::member();
    $hidden = N::member();
    $hidden->privacySetting()->create(['oasis_list_audience' => 'nobody']);
    Follow::create(['follower_id' => $followsMe->id, 'followable_type' => $me->getMorphClass(), 'followable_id' => $me->id]);
    Friendship::create(['requester_id' => $me->id, 'addressee_id' => $friend->id, 'accepted_at' => now()]);
    $oasis = oasisWith($me, $stranger, $followsMe, $friend, $hidden);

    Livewire::actingAs($me)->test(OasisShow::class, ['oasis' => $oasis])
        ->assertSee($followsMe->display_name)->assertSee($friend->display_name)
        ->assertDontSee($stranger->display_name)->assertDontSee($hidden->display_name)
        ->assertSee('2 weitere Mitglieder zeigen sich')
        ->assertSee('Zu deinem eigenen Schutz ist aktuell eingestellt');
});

it('hides the member list from non-members and blocked people from the list', function () {
    [$me] = F::user();
    [$blocked] = F::user();
    $oasis = oasisWith($me, $blocked);
    Block::create(['user_id' => $me->id, 'blocked_user_id' => $blocked->id]);

    Livewire::actingAs($me)->test(OasisShow::class, ['oasis' => $oasis])->assertDontSee($blocked->display_name);
    Livewire::actingAs(N::member())->test(OasisShow::class, ['oasis' => $oasis])->assertDontSee('Namen suchen');
});

it('shows "Schreiben" on a profile when the chat allows it', function () {
    [$me] = F::user();
    [$mira] = F::user();

    $this->actingAs($me)->get(route('oasis.profil', $mira->username))->assertOk()->assertSee('#neu/'.$mira->username, false);
    $mira->privacySetting()->create(['chat_invites_audience' => 'nobody']);
    $this->get(route('oasis.profil', $mira->username))->assertOk()->assertDontSee('#neu/'.$mira->username, false);
});
