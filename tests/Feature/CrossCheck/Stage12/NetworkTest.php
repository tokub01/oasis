<?php

/*
 * Stage 12 cross-check (Claude reviews Codex): the network and search screens as Livewire components. Written
 * against the behaviour of the former controllers, from an attacker's point of view: every public method is called
 * directly, with foreign ids, manipulated properties and accounts that lost their rights.
 */

use App\Actions\Safety\ManageBlock;
use App\Ai\Agents\ContentCheck as ContentCheckAgent;
use App\Enums\Role;
use App\Livewire\Network\Compose;
use App\Livewire\Network\Discover;
use App\Livewire\Network\Invitations;
use App\Livewire\Network\OasisForm;
use App\Livewire\Network\OasisShow;
use App\Livewire\Network\PostShow;
use App\Livewire\Network\ProfileEdit;
use App\Livewire\Network\ProfileShow;
use App\Livewire\Search\Index as SearchIndex;
use App\Livewire\Search\Tag;
use App\Models\ModerationCase;
use App\Models\Post;
use App\Models\Reaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Cache::flush();
});

describe('post-card actions', function () {
    it('lets nobody delete or remove a foreign Tropfen', function () {
        $author = N::member();
        $post = N::post($author);
        $this->actingAs(N::member());

        Livewire::test(Discover::class)->call('deletePost', $post->id)->assertStatus(403);
        Livewire::test(Discover::class)->call('removePostFromOasis', $post->id)->assertStatus(403);
        expect(Post::find($post->id))->not->toBeNull()->and($post->fresh()->removed_from_oasis_at)->toBeNull();
    });

    it('refuses reactions to Tropfen of a blocked person and to held Tropfen', function () {
        $me = N::member();
        $blocked = N::member();
        $blockedPost = N::post($blocked);
        app(ManageBlock::class)->block($me, $blocked);
        $held = N::post(N::member());
        $held->forceFill(['held_at' => now()])->save();
        $this->actingAs($me->fresh());

        Livewire::test(Discover::class)->call('react', $blockedPost->id)->assertStatus(403);
        Livewire::test(Discover::class)->call('react', $held->id)->assertStatus(403);
        expect(Reaction::count())->toBe(0);
    });

    it('refuses writes by read-only members and guests', function () {
        $post = N::post(N::member());
        $this->actingAs(N::readOnlyMember());
        Livewire::test(Discover::class)->call('react', $post->id)->assertStatus(403);

        auth()->logout();
        Livewire::test(Tag::class, ['tag' => 'thema'])->call('bookmark', $post->id)->assertStatus(403);
        expect(Reaction::count())->toBe(0);
    });

    it('does not let a host of one oasis remove a Tropfen from another oasis', function () {
        $host = N::member();
        N::oasis($host, ['name' => 'Eigene', 'slug' => 'eigene']);
        $other = N::oasis(N::member(), ['name' => 'Fremde', 'slug' => 'fremde']);
        $post = N::post(N::member(), ['oasis_id' => $other->id]);
        $this->actingAs($host);

        Livewire::test(Discover::class)->call('removePostFromOasis', $post->id)->assertStatus(403);
        expect($post->fresh()->removed_from_oasis_at)->toBeNull();
    });
});

describe('composer', function () {
    it('keeps the context ids locked against browser manipulation', function () {
        $this->actingAs(N::member());
        $foreign = N::oasis(N::member(), ['visibility' => 'members', 'slug' => 'geschlossen']);

        expect(fn () => Livewire::test(Compose::class)->set('oasisId', $foreign->id))
            ->toThrow(CannotUpdateLockedPropertyException::class);
        expect(fn () => Livewire::test(Compose::class)->set('replyToId', N::post(N::member())->id))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('refuses posting into an oasis the member does not belong to and replying to a blocked person', function () {
        $me = N::member();
        $oasis = N::oasis(N::member(), ['slug' => 'nicht-meine']);
        $blocked = N::member();
        $post = N::post($blocked);
        app(ManageBlock::class)->block($blocked, $me);
        $this->actingAs($me->fresh());

        Livewire::test(Compose::class, ['oasisId' => $oasis->id])->assertStatus(403);
        Livewire::test(Compose::class, ['replyToId' => $post->id])->assertStatus(403);
        expect(Post::count())->toBe(1);
    });

    it('validates kind, visibility and image types instead of trusting the browser', function () {
        Storage::fake(config('oasis.media_disk'));
        $this->actingAs(N::member());

        Livewire::test(Compose::class)->set('body', 'Hallo')->set('kind', 'admin')->set('visibility', 'everyone')
            ->call('publish')->assertHasErrors(['kind', 'visibility']);
        // Refused already at upload time; the PDF is never attached to the Tropfen.
        Livewire::test(Compose::class)->set('body', 'Hallo')
            ->set('images', [UploadedFile::fake()->create('rechnung.pdf', 10, 'application/pdf')])
            ->assertHasErrors('images.0')->assertSet('images', []);
        Livewire::test(Compose::class)->set('body', 'Hallo')
            ->set('images', [UploadedFile::fake()->image('foto.jpg')])->set('alts', [''])
            ->call('publish')->assertHasErrors('alts.0');
        expect(Post::count())->toBe(0);
    });

    it('keeps the posting budget of ten per minute', function () {
        $this->actingAs(N::member());
        foreach (range(1, 10) as $i) {
            Livewire::test(Compose::class)->set('body', 'Tropfen '.$i)->call('publish')->assertHasNoErrors();
        }
        Livewire::test(Compose::class)->set('body', 'Elf')->call('publish')->assertHasErrors('body');
        expect(Post::count())->toBe(10);
    });

    it('still asks "Nochmal lesen?" and reports the Tropfen when it is sent anyway', function () {
        config(['oasis.moderation.enabled' => true]);
        ContentCheckAgent::fake(fn () => ['insult' => 0.9, 'hate' => 0, 'threat' => 0, 'sexual' => 0, 'sexual_minors' => 0, 'reason' => 'Beleidigung']);
        $this->actingAs(N::member());

        $compose = Livewire::test(Compose::class)->set('body', 'Du bist ein Idiot')->assertSet('toneNudge', false)
            ->call('publish')->assertHasErrors('body');
        expect(Post::count())->toBe(0);

        $compose->set('tone_confirmed', true)->call('publish')->assertHasNoErrors();
        expect(Post::count())->toBe(1)->and(ModerationCase::count())->toBe(1);
    });

    it('resets the tone confirmation when the text changes', function () {
        config(['oasis.moderation.enabled' => true]);
        ContentCheckAgent::fake(fn () => ['insult' => 0.9, 'hate' => 0, 'threat' => 0, 'sexual' => 0, 'sexual_minors' => 0, 'reason' => '']);
        $this->actingAs(N::member());

        Livewire::test(Compose::class)->set('body', 'Du Idiot')->set('tone_confirmed', true)
            ->set('body', 'Du Vollidiot')->assertSet('tone_confirmed', false)
            ->call('publish')->assertHasErrors('body');
        expect(Post::count())->toBe(0);
    });
});

describe('pages', function () {
    it('hides held Tropfen from other members and guests but shows them to author and staff', function () {
        $author = N::member();
        $post = N::post($author);
        $post->forceFill(['held_at' => now()])->save();

        $this->actingAs(N::member());
        Livewire::test(PostShow::class, ['post' => $post])->assertStatus(404);
        $this->actingAs($author);
        Livewire::test(PostShow::class, ['post' => $post])->assertStatus(200);
        $this->actingAs(N::member(['role' => Role::Moderator]));
        Livewire::test(PostShow::class, ['post' => $post])->assertStatus(200);
    });

    it('lets only hosts change oasis roles, even when calling the method directly', function () {
        $host = N::member();
        $oasis = N::oasis($host, ['slug' => 'rollen']);
        $member = N::member();
        $oasis->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($member);
        Livewire::test(OasisForm::class, ['oasis' => $oasis])->assertStatus(403);
        expect($oasis->isHost($member))->toBeFalse();

        $this->actingAs($host);
        $form = Livewire::test(OasisForm::class, ['oasis' => $oasis]);
        $form->call('setRole', $member->id, 'superhost')->assertHasErrors('role');
        $host->forceFill(['trial_ends_at' => now()->subDay()])->save();
        $oasis->members()->updateExistingPivot($host->id, ['role' => 'member', 'joined_at' => now()]);
        $form->call('setRole', $member->id, 'host')->assertStatus(403);
        expect($oasis->fresh()->isHost($member))->toBeFalse();
    });

    it('does not follow a blocked profile or a members-only oasis for guests', function () {
        $me = N::member();
        $other = N::member();
        $other->profile()->create(['visibility' => 'public']);
        app(ManageBlock::class)->block($other, $me);
        $this->actingAs($me->fresh());

        Livewire::test(ProfileShow::class, ['handle' => $other->username])->assertStatus(404);

        auth()->logout();
        $oasis = N::oasis(N::member(), ['visibility' => 'public', 'slug' => 'offen']);
        Livewire::test(OasisShow::class, ['oasis' => $oasis])->call('follow')->assertStatus(403);
    });

    it('never puts foreign e-mail addresses or invitation tokens into the public snapshot', function () {
        $me = N::member();
        $other = N::member(['email' => 'geheim@example.tld']);
        $other->profile()->create(['visibility' => 'public']);
        $oasis = N::oasis($me, ['slug' => 'schnappschuss']);
        $oasis->members()->attach($other->id, ['role' => 'member', 'joined_at' => now()]);
        $this->actingAs($me);

        $snapshot = json_encode(Livewire::test(OasisForm::class, ['oasis' => $oasis])->snapshot);
        expect($snapshot)->not->toContain('geheim@example.tld');
        $snapshot = json_encode(Livewire::test(ProfileShow::class, ['handle' => $other->username])->snapshot);
        expect($snapshot)->not->toContain('geheim@example.tld');

        $invitations = Livewire::test(Invitations::class)->call('create');
        $token = $me->invitations()->value('token');
        expect(json_encode($invitations->snapshot))->not->toContain($token);
    });

    it('validates the own profile and stores nothing for invalid choices', function () {
        $me = N::member();
        $this->actingAs($me);

        Livewire::test(ProfileEdit::class)->set('visibility', 'secret')->set('avatar_color', 'neon')
            ->call('save')->assertHasErrors(['visibility', 'avatar_color']);
        expect($me->profile()->exists())->toBeFalse();
    });
});

describe('search', function () {
    it('bounds the term and keeps the search budget for live updates', function () {
        $this->actingAs(N::member());

        Livewire::test(SearchIndex::class)->set('q', str_repeat('a', 101))->assertHasErrors('q');
        $search = Livewire::test(SearchIndex::class);
        foreach (range(1, 29) as $i) {
            $search->set('q', 'suche'.$i);
        }
        $search->set('q', 'zu viel')->assertHasErrors('q');
    });

    it('jumps to the topic page for a single #Thema', function () {
        $this->actingAs(N::member());

        Livewire::test(SearchIndex::class)->set('q', '#Garten')->assertRedirect(route('search.tags.show', 'garten'));
    });

    it('rejects invalid topics', function () {
        $this->get(route('search.tags.show', 'a'))->assertNotFound();
    });
});
