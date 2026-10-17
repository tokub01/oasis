<?php

/* Editing Tropfen (docs/oasis-network.md, "Bearbeiten"): own Tropfen, one hour, versions visible, checked again. */

use App\Actions\Network\DeletePost;
use App\Actions\Network\EditPost;
use App\Actions\Network\PublishPost;
use App\Livewire\Network\PostEdit;
use App\Livewire\Network\PostShow;
use App\Models\ContentCheck;
use App\Models\PostRevision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

it('lets the author change the text within an hour and keeps the earlier version visible', function () {
    $author = N::member();
    $post = app(PublishPost::class)->handle($author, ['body' => 'Erste Fassung mit Tippfeler', 'visibility' => 'public']);

    Livewire::actingAs($author)->test(PostEdit::class, ['post' => $post])->assertSee('Tropfen bearbeiten')
        ->set('body', 'Erste Fassung ohne Tippfehler')->call('save')
        ->assertRedirect(route('network.posts.show', $post));

    $post->refresh();
    expect($post->body)->toBe('Erste Fassung ohne Tippfehler')->and($post->edited_at)->not->toBeNull()
        ->and($post->revisions()->sole()->body)->toBe('Erste Fassung mit Tippfeler');

    Livewire::actingAs(N::member())->test(PostShow::class, ['post' => $post])
        ->assertSee('bearbeitet')->assertSee('Frühere Fassungen (1)')->assertSee('Erste Fassung mit Tippfeler');
    $this->get(route('network.posts.show', $post))->assertOk()->assertSee('Erste Fassung mit Tippfeler');
});

it('offers editing only to the author, only for an hour and only while the Tropfen is in place', function () {
    $author = N::member();
    $post = N::post($author, ['body' => 'Mein Tropfen']);

    expect($author->can('update', $post))->toBeTrue()
        ->and(N::member()->can('update', $post))->toBeFalse();
    Livewire::actingAs($author)->test(PostShow::class, ['post' => $post])->assertSee(route('network.posts.edit', $post), false);
    Livewire::actingAs(N::member())->test(PostShow::class, ['post' => $post])->assertDontSee(route('network.posts.edit', $post), false);

    $held = N::post($author, ['body' => 'Ausgeblendet', 'held_at' => now()]);
    expect($author->can('update', $held))->toBeFalse();

    $this->travel(61)->minutes();
    expect($author->fresh()->can('update', $post->fresh()))->toBeFalse();
    expect(fn () => app(EditPost::class)->handle($author->fresh(), $post->fresh(), 'Zu spät'))->toThrow(AuthorizationException::class);
    $this->actingAs($author)->get(route('network.posts.edit', $post))->assertForbidden();
});

it('validates the text, ignores unchanged text and checks a changed one again', function () {
    $author = N::member();
    $post = N::post($author, ['body' => 'Ruhiger Text']);
    $edit = app(EditPost::class);

    expect(fn () => $edit->handle($author, $post, ''))->toThrow(ValidationException::class)
        ->and(fn () => $edit->handle($author, $post, str_repeat('x', 501)))->toThrow(ValidationException::class);
    $edit->handle($author, $post, '  Ruhiger Text  ');
    expect($post->fresh()->edited_at)->toBeNull()->and($post->revisions()->count())->toBe(0);

    expect(ContentCheck::where('checkable_type', $post->getMorphClass())->where('checkable_id', $post->id)->exists())->toBeFalse();
    $edit->handle($author, $post, 'Geänderter Text');
    // The queue runs synchronously in tests, so the new check has already run.
    expect(ContentCheck::where('checkable_type', $post->getMorphClass())->where('checkable_id', $post->id)->exists())->toBeTrue();
});

it('does not let new accounts add links later and sends no hints for new mentions', function () {
    config(['oasis.bot_protection.new_account_hours' => 24]);
    $author = N::member();
    $mentioned = N::member(['username' => 'erwaehnt']);
    $post = N::post($author, ['body' => 'Harmlos']);

    expect(fn () => app(EditPost::class)->handle($author, $post, 'https://a.tld https://b.tld https://c.tld'))->toThrow(ValidationException::class);
    app(EditPost::class)->handle($author, $post, 'Hallo @erwaehnt');
    expect($mentioned->notifications()->count())->toBe(0);
});

it('exports earlier versions and deletes them with the Tropfen', function () {
    $author = N::member();
    $post = N::post($author, ['body' => 'Alt']);
    app(EditPost::class)->handle($author, $post, 'Neu');

    $zip = new ZipArchive;
    $zip->open($this->actingAs($author)->get(route('account.export'))->getFile()->getPathname());
    $export = json_decode($zip->getFromName('oasis-daten.json'), true);
    expect($export['tropfen'][0]['fruehere_fassungen'][0]['text'])->toBe('Alt');

    app(DeletePost::class)->handle($post);
    expect(PostRevision::count())->toBe(0);
});
