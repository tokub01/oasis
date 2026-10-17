<?php

use App\Actions\Network\PublishPost;
use App\Enums\Notifications\NoticeKind;
use App\Models\Block;
use App\Models\Follow;
use App\Support\Network\Mentions;
use App\Support\Notifications\HintPresentation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

it('uses the text author for mention hints and links regardless of the current reader', function (string $audience, bool $allowed) {
    $author = N::member();
    $recipient = N::member(['username' => 'mention_target']);
    $recipient->privacySetting()->create(['mentions_audience' => $audience]);
    $text = '@mention_target';
    $this->actingAs($recipient);

    app(PublishPost::class)->handle($author, ['body' => $text, 'visibility' => 'public']);
    expect($recipient->notifications()->where('data->kind', NoticeKind::Mention->value)->count())->toBe($allowed ? 1 : 0);
    $html = Blade::render('<x-network.rich-text :text="$text" :author="$author" />', compact('text', 'author'));
    expect($html)->toContain('@mention_target');
    expect(str_contains($html, route('oasis.profil', $recipient->username)))->toBe($allowed);
})->with(['everyone' => ['everyone', true], 'members' => ['members', true], 'following' => ['following', false], 'nobody' => ['nobody', false]]);

it('permits mentions by chosen people and by the owner', function (bool $self) {
    $recipient = N::member(['username' => 'chosen_mention']);
    $author = $self ? $recipient : N::member();
    $recipient->privacySetting()->create(['mentions_audience' => $self ? 'nobody' : 'following']);
    if (! $self) {
        Follow::create(['follower_id' => $recipient->id, 'followable_type' => $author->getMorphClass(), 'followable_id' => $author->id]);
    }
    $text = '@chosen_mention';

    app(PublishPost::class)->handle($author, ['body' => $text, 'visibility' => 'public']);
    expect($recipient->notifications()->count())->toBe($self ? 0 : 1);
    expect(Blade::render('<x-network.rich-text :text="$text" :author="$author" />', compact('text', 'author')))
        ->toContain(route('oasis.profil', $recipient->username));
})->with([false, true]);

it('keeps bidirectional blocks above mention permission', function (bool $reverse) {
    $author = N::member();
    $recipient = N::member(['username' => 'blocked_mention']);
    $recipient->privacySetting()->create(['mentions_audience' => 'everyone']);
    Block::create(['user_id' => $reverse ? $recipient->id : $author->id, 'blocked_user_id' => $reverse ? $author->id : $recipient->id]);
    $text = '@blocked_mention';

    app(PublishPost::class)->handle($author, ['body' => $text, 'visibility' => 'public']);
    expect($recipient->notifications()->count())->toBe(0);
    expect(Blade::render('<x-network.rich-text :text="$text" :author="$author" />', compact('text', 'author')))
        ->toContain('@blocked_mention')->not->toContain(route('oasis.profil', $recipient->username));
})->with([false, true]);

it('hides old mention hints after permission is withdrawn without hiding reply hints', function () {
    $recipient = N::member(['username' => 'old_mention']);
    $author = N::member();
    $settings = $recipient->privacySetting()->create();
    app(PublishPost::class)->handle($author, ['body' => '@old_mention', 'visibility' => 'public']);
    $hint = $recipient->notifications()->sole();
    expect(app(HintPresentation::class)->forUser($hint, $recipient))->not->toBeNull();
    $settings->update(['mentions_audience' => 'nobody']);

    expect(app(HintPresentation::class)->forUser($hint, $recipient->fresh()))->toBeNull();
    $original = N::post($recipient);
    app(PublishPost::class)->handle($author, ['body' => '@old_mention Antwort', 'visibility' => 'public', 'reply_to' => $original]);
    expect($recipient->notifications()->where('data->kind', 'reply')->count())->toBe(1);
});

it('never links mentions without a known author', function () {
    N::member(['username' => 'unknown_author']);

    expect(Blade::render('<x-network.rich-text text="@unknown_author" />'))->toContain('@unknown_author')
        ->not->toContain(route('oasis.profil', 'unknown_author'));
});

it('batches distinct mention audiences and safety edges across a full page of cards', function () {
    $posts = [];
    foreach (range(1, 12) as $index) {
        $author = N::member();
        $recipient = N::member(['username' => 'batch_mention_'.$index]);
        $recipient->privacySetting()->create(['mentions_audience' => 'following']);
        Follow::create(['follower_id' => $recipient->id, 'followable_type' => $author->getMorphClass(), 'followable_id' => $author->id]);
        $post = N::post($author, ['body' => '@'.$recipient->username]);
        $post->setRelation('author', $author);
        $posts[] = $post;
    }
    DB::enableQueryLog();
    DB::flushQueryLog();

    Mentions::preloadPosts($posts);
    expect(DB::getQueryLog())->toHaveCount(4);
    DB::flushQueryLog();
    foreach ($posts as $post) {
        expect(Blade::render('<x-network.rich-text :text="$post->body" :author="$post->author" />', compact('post')))
            ->toContain(route('oasis.profil', substr($post->body, 1)));
    }
    expect(Mentions::linkable(['batch_mention_2'], $posts[0]->author))->toBe([]);
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});
