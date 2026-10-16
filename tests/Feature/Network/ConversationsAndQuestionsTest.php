<?php

use App\Enums\Network\PostKind;
use App\Jobs\Moderation\CheckContent;
use App\Models\Follow;
use App\Models\Post;
use Illuminate\Support\Facades\Queue;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Queue::fake([CheckContent::class]);
});

it('publishes a question to one\'s Karawane and saves kind as question', function () {
    $author = N::member();

    $this->actingAs($author)->post(route('network.posts.store'), [
        'body' => 'Was liest du diesen Herbst?',
        'visibility' => 'public',
        'kind' => 'question',
    ])->assertRedirect(route('app.home'));

    $post = Post::latest('id')->first();
    expect($post->kind)->toBe(PostKind::Question)
        ->and($post->isQuestion())->toBeTrue();
});

it('defaults kind to post when not specified or invalid', function () {
    $author = N::member();

    $this->actingAs($author)->post(route('network.posts.store'), [
        'body' => 'Ein normaler Tropfen.',
        'visibility' => 'public',
    ])->assertRedirect(route('app.home'));

    $post = Post::latest('id')->first();
    expect($post->kind)->toBe(PostKind::Post)
        ->and($post->isQuestion())->toBeFalse();
});

it('forces replies to be regular posts even if kind=question is submitted', function () {
    $author = N::member();
    $question = N::post($author, ['body' => 'Welches Buch empfiehlst du?', 'kind' => 'question']);

    $responder = N::member();
    $this->actingAs($responder)->post(route('network.posts.store'), [
        'body' => 'Momo von Michael Ende.',
        'visibility' => 'public',
        'reply_to' => $question->id,
        'kind' => 'question',
    ])->assertRedirect();

    $reply = Post::latest('id')->first();
    expect($reply->kind)->toBe(PostKind::Post)
        ->and($reply->isQuestion())->toBeFalse();
});

it('displays the question badge on question cards and distinguishes author from readers', function () {
    $author = N::member(['username' => 'lara', 'display_name' => 'Lara']);
    $reader = N::member(['username' => 'paul', 'display_name' => 'Paul']);

    $question = N::post($author, ['body' => 'Was kocht ihr heute?', 'kind' => 'question']);
    $normalPost = N::post($author, ['body' => 'Guten Morgen!', 'kind' => 'post']);

    // Author sees "Frage an meine Karawane"
    $this->actingAs($author)->get(route('network.posts.show', $question))
        ->assertOk()
        ->assertSee('Frage an meine Karawane');

    // Reader sees "Frage an die Karawane"
    $this->actingAs($reader)->get(route('network.posts.show', $question))
        ->assertOk()
        ->assertSee('Frage an die Karawane');

    // Normal post does not show question badge
    $this->actingAs($author)->get(route('network.posts.show', $normalPost))
        ->assertOk()
        ->assertDontSee('Frage an meine Karawane')
        ->assertDontSee('Frage an die Karawane');
});

it('shows prominent reply count on question cards only to the asking author', function () {
    $author = N::member(['username' => 'eva']);
    $reader = N::member(['username' => 'tim']);

    $question = N::post($author, ['body' => 'Gibt es Fragen?', 'kind' => 'question']);

    // 0 replies: author sees prominent banner "Noch hat niemand geantwortet"
    $this->actingAs($author)->get(route('network.posts.show', $question))
        ->assertOk()
        ->assertSee('Noch hat niemand geantwortet');

    // Reader does NOT see prominent question reply count
    $this->actingAs($reader)->get(route('network.posts.show', $question))
        ->assertOk()
        ->assertDontSee('Noch hat niemand geantwortet')
        ->assertDontSee('Mensch hat geantwortet')
        ->assertDontSee('Menschen haben geantwortet');

    // 1 reply: author sees "1 Mensch hat geantwortet"
    $reply1 = N::post($reader, ['body' => 'Hier ist meine Frage!', 'reply_to_id' => $question->id]);

    $this->actingAs($author)->get(route('network.posts.show', $question))
        ->assertOk()
        ->assertSee('1 Mensch hat geantwortet');

    $this->actingAs($reader)->get(route('network.posts.show', $question))
        ->assertOk()
        ->assertDontSee('1 Mensch hat geantwortet');

    // 2 replies: author sees "2 Menschen haben geantwortet"
    $reader2 = N::member(['username' => 'max']);
    N::post($reader2, ['body' => 'Zweite Antwort.', 'reply_to_id' => $question->id]);

    $this->actingAs($author)->get(route('network.posts.show', $question))
        ->assertOk()
        ->assertSee('2 Menschen haben geantwortet');

    $this->actingAs($reader)->get(route('network.posts.show', $question))
        ->assertOk()
        ->assertDontSee('2 Menschen haben geantwortet');
});

it('bundles followed replies in the Quelle under the origin Tropfen (up to 2 + weitere Antworten)', function () {
    $me = N::member(['username' => 'ich']);
    $friendA = N::member(['username' => 'freund_a', 'display_name' => 'Freund A']);
    $friendB = N::member(['username' => 'freund_b', 'display_name' => 'Freund B']);
    $friendC = N::member(['username' => 'freund_c', 'display_name' => 'Freund C']);
    $stranger = N::member(['username' => 'fremder', 'display_name' => 'Fremder']);

    // I follow friendA, friendB, friendC (not stranger)
    $this->actingAs($me)->post(route('network.follow.user', $friendA));
    $this->actingAs($me)->post(route('network.follow.user', $friendB));
    $this->actingAs($me)->post(route('network.follow.user', $friendC));

    // FriendA posts a Tropfen
    $origin = N::post($friendA, ['body' => 'Hauptbeitrag von Freund A']);

    // FriendB replies (reply 1)
    N::post($friendB, ['body' => 'Erste Antwort von Freund B', 'reply_to_id' => $origin->id]);

    // FriendC replies (reply 2)
    N::post($friendC, ['body' => 'Zweite Antwort von Freund C', 'reply_to_id' => $origin->id]);

    // Stranger replies (reply 3 - stranger)
    N::post($stranger, ['body' => 'Fremde Antwort von Unbekannt', 'reply_to_id' => $origin->id]);

    $res = $this->actingAs($me)->get(route('app.home'))->assertOk();

    // Both followed replies are bundled under origin
    $res->assertSee('Hauptbeitrag von Freund A')
        ->assertSee('Erste Antwort von Freund B')
        ->assertSee('Zweite Antwort von Freund C')
        // Stranger reply is NOT shown in the bundle
        ->assertDontSee('Fremde Antwort von Unbekannt')
        // More replies exist (total replies is 3) -> "weitere Antworten" link
        ->assertSee('weitere Antworten');
});

it('filters out blocked or muted users from conversation replies in the Quelle', function () {
    $me = N::member(['username' => 'ich']);
    $friend = N::member(['username' => 'freund']);
    $troll = N::member(['username' => 'troll']);

    $this->actingAs($me)->post(route('network.follow.user', $friend));
    $this->actingAs($me)->post(route('network.follow.user', $troll));

    $origin = N::post($friend, ['body' => 'Schöner Sonnentag']);
    N::post($troll, ['body' => 'Unangenehmer Troll-Kommentar', 'reply_to_id' => $origin->id]);
    N::post($friend, ['body' => 'Eigener Zusatzkommentar', 'reply_to_id' => $origin->id]);

    // Now I block the troll
    $this->actingAs($me)->post(route('safety.block.store', $troll))->assertRedirect();

    $this->actingAs($me)->get(route('app.home'))->assertOk()
        ->assertSee('Schöner Sonnentag')
        ->assertSee('Eigener Zusatzkommentar')
        ->assertDontSee('Unangenehmer Troll-Kommentar');
});

it('renders sanfter Antwort-Anstoß only for mutual follows and not in Lesemodus', function () {
    $me = N::member(['username' => 'me']);
    $mutual = N::member(['username' => 'mutual_friend', 'display_name' => 'Gegenseitiger Freund']);
    $oneWay = N::member(['username' => 'oneway_friend', 'display_name' => 'Einseitiger Freund']);

    // Mutual follow: me follows mutual, mutual follows me
    Follow::create([
        'follower_id' => $me->id,
        'followable_type' => $mutual->getMorphClass(),
        'followable_id' => $mutual->id,
    ]);
    Follow::create([
        'follower_id' => $mutual->id,
        'followable_type' => $me->getMorphClass(),
        'followable_id' => $me->id,
    ]);

    // One-way follow: me follows oneWay, but oneWay does NOT follow me
    Follow::create([
        'follower_id' => $me->id,
        'followable_type' => $oneWay->getMorphClass(),
        'followable_id' => $oneWay->id,
    ]);

    $postFromMutual = N::post($mutual, ['body' => 'Post von gegenseitigem Freund']);
    $postFromOneWay = N::post($oneWay, ['body' => 'Post von einseitigem Freund']);
    $ownPost = N::post($me, ['body' => 'Mein eigener Tropfen']);

    // Check in Quelle:
    $res = $this->actingAs($me)->get(route('app.home'))->assertOk();

    // Mutual follow card HAS the quick-reply form with placeholder "Antworten …"
    $res->assertSee('Post von gegenseitigem Freund')
        ->assertSee('placeholder="Antworten …"', false);

    // One-way follow card does NOT have quick-reply for oneWay
    $res->assertSee('Post von einseitigem Freund');
    // Verify that quick-reply form exists for mutual post but NOT for oneWay or own post
    $res->assertSee('id="quick-reply-'.$postFromMutual->id.'"', false);
    $res->assertDontSee('id="quick-reply-'.$postFromOneWay->id.'"', false);
    $res->assertDontSee('id="quick-reply-'.$ownPost->id.'"', false);

    // Submitting quick-reply form creates a reply
    $this->actingAs($me)->post(route('network.posts.store'), [
        'reply_to' => $postFromMutual->id,
        'visibility' => 'public',
        'body' => 'Schnelle Antwort aus der Karte!',
    ])->assertRedirect(route('network.posts.show', $postFromMutual->id).'#tropfen-'.Post::latest('id')->first()->id);

    // In read-only mode (Lesemodus): quick-reply form is NOT shown even for mutual follow
    $readOnlyMe = N::readOnlyMember();
    Follow::create([
        'follower_id' => $readOnlyMe->id,
        'followable_type' => $mutual->getMorphClass(),
        'followable_id' => $mutual->id,
    ]);
    Follow::create([
        'follower_id' => $mutual->id,
        'followable_type' => $readOnlyMe->getMorphClass(),
        'followable_id' => $readOnlyMe->id,
    ]);

    $this->actingAs($readOnlyMe)->get(route('app.home'))->assertOk()
        ->assertDontSee('id="quick-reply-'.$postFromMutual->id.'"', false);
});
