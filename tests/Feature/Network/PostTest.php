<?php

use App\Livewire\Network\Compose;
use App\Livewire\Oasis\ContentNotice;
use App\Models\Post;
use App\Models\Reaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as N;

/* Tropfen: writing, reading by visibility, images, replies, carrying, reactions and deleting. */
beforeEach(function () {
    $this->withoutVite();
    Storage::fake(config('oasis.media_disk'));
});

it('publishes a Tropfen from the Quelle and shows it there and on the profile', function () {
    $author = N::member();

    NetworkActions::publish(['body' => 'Erster Tropfen', 'visibility' => 'public'], $author)->assertHasNoErrors()->assertSet('status', 'Dein Tropfen ist da.');

    $this->get(route('app.home'))->assertOk()->assertSee('Erster Tropfen')->assertSee('Du bist auf dem neuesten Stand.');
    $this->get(route('oasis.profil', $author->username))->assertOk()->assertSee('Erster Tropfen');
});

it('validates body length and requires a description for every image', function () {
    $this->actingAs(N::member());

    NetworkActions::publish(['body' => '', 'visibility' => 'public'])->assertHasErrors('body');
    NetworkActions::publish(['body' => str_repeat('a', 501), 'visibility' => 'public'])->assertHasErrors('body');
    NetworkActions::publish(['body' => 'Bild', 'visibility' => 'public', 'images' => [UploadedFile::fake()->image('a.jpg')]])->assertHasErrors('alts.0');
    // A PDF is refused as soon as it is uploaded (config livewire.temporary_file_upload.rules) and never attached.
    Livewire::test(Compose::class)->set('images', [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
        ->assertHasErrors('images.0')->assertSet('images', []);
    expect(Post::count())->toBe(0);
});

it('re-encodes images as WebP and serves them with the visibility of the Tropfen', function () {
    $author = N::member();
    NetworkActions::publish([
        'body' => 'Mit Bild', 'visibility' => 'members',
        'images' => [2 => UploadedFile::fake()->image('foto.jpg', 3000, 1500)], 'alts' => [2 => 'Ein Feld im Morgenlicht'],
    ], $author)->assertHasNoErrors();

    $image = Post::sole()->images()->sole();
    expect($image->alt)->toBe('Ein Feld im Morgenlicht')
        ->and([$image->width, $image->height])->toBe([2048, 1024])
        ->and(str_ends_with($image->path, '.webp'))->toBeTrue()
        ->and(substr(Storage::disk(config('oasis.media_disk'))->get($image->path), 8, 4))->toBe('WEBP');

    $url = route('network.images.show', [$image->post_id, $image->id]);
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    auth()->logout();
    $this->get($url)->assertNotFound();
});

it('rejects files that are not decodable images without leaving a half-published Tropfen', function () {
    $file = UploadedFile::fake()->createWithContent('kaputt.png', 'not really a png');
    NetworkActions::publish([
        'body' => 'Kaputt', 'visibility' => 'public', 'images' => [$file], 'alts' => ['x'],
    ], N::member())->assertHasErrors('images.0');
    expect(Post::count())->toBe(0);
});

it('lets guests read public Tropfen only; members-only and unknown ones are 404', function () {
    $author = N::member();
    $public = N::post($author, ['body' => 'Für alle']);
    $members = N::post($author, ['body' => 'Nur Mitglieder', 'visibility' => 'members']);

    $this->get(route('network.posts.show', $public))->assertOk()->assertSee('Für alle')->assertSee('Du liest als Gast.');
    $this->get(route('network.posts.show', $members))->assertNotFound();
    $this->get(route('network.posts.show', 99999))->assertNotFound();
    $this->actingAs(N::member())->get(route('network.posts.show', $members))->assertOk()->assertSee('Nur Mitglieder');
});

it('keeps replies and carries of members-only Tropfen members-only', function () {
    $author = N::member();
    $original = N::post($author, ['visibility' => 'members']);
    $this->actingAs(N::member());

    NetworkActions::publish(['body' => 'Antwort', 'visibility' => 'public', 'reply_to' => $original->id])->assertHasNoErrors();
    NetworkActions::publish(['body' => 'Weiter', 'visibility' => 'public', 'carried' => $original->id]);

    expect(Post::where('reply_to_id', $original->id)->sole()->visibility->value)->toBe('members')
        ->and(Post::where('carried_post_id', $original->id)->sole()->visibility->value)->toBe('members');
    $this->get(route('network.posts.show', $original))->assertSee('Antwort');
    $this->get(route('network.posts.carry', $original))->assertOk()->assertSee('Weitertragen');
});

it('refuses replying to Tropfen the member cannot read and carrying with a reply at once', function () {
    $hidden = N::post(N::member(), ['visibility' => 'members']);
    $hidden->forceFill(['removed_from_oasis_at' => now()])->save();
    $this->actingAs(N::member());

    NetworkActions::publish(['body' => 'x', 'visibility' => 'public', 'reply_to' => $hidden->id])->assertForbidden();
    $visible = N::post(N::member());
    NetworkActions::publish(['body' => 'x', 'visibility' => 'public', 'reply_to' => $visible->id, 'carried' => $visible->id])->assertStatus(422);
});

it('shows reaction and reply counts only to the author and lets members react once and take it back', function () {
    $author = N::member();
    $post = N::post($author);
    $reader = N::member();

    NetworkActions::post('react', $post->id, $reader)->assertHasNoErrors();
    NetworkActions::post('react', $post->id);
    expect(Reaction::count())->toBe(1);
    $this->get(route('network.posts.show', $post))->assertDontSee('Nur für dich sichtbar')->assertSee('Reagiert');

    $this->actingAs($author)->get(route('network.posts.show', $post))->assertSee('Nur für dich sichtbar:')->assertSee('1 Reaktion,');

    NetworkActions::post('unreact', $post->id, $reader);
    expect(Reaction::count())->toBe(0);
});

it('lets authors delete their Tropfen with images; others get 403 and see a report link', function () {
    $author = N::member();
    NetworkActions::publish([
        'body' => 'Weg damit', 'visibility' => 'public', 'images' => [UploadedFile::fake()->image('a.png')], 'alts' => ['A'],
    ], $author);
    $post = Post::sole();
    $other = N::member();

    $this->actingAs($other)->get(route('network.posts.show', $post))
        ->assertSee(route('app.report', ['url' => route('network.posts.show', $post)]), false);
    NetworkActions::post('deletePost', $post->id)->assertForbidden();

    NetworkActions::post('deletePost', $post->id, $author)->assertHasNoErrors();
    expect(Post::count())->toBe(0)->and(Storage::disk(config('oasis.media_disk'))->allFiles())->toBe([]);
});

it('keeps carries of a deleted Tropfen and says the original is gone', function () {
    $original = N::post(N::member());
    $carry = N::post(N::member(), ['carried_post_id' => $original->id, 'body' => 'Meine Worte']);
    $original->delete();

    $this->actingAs(N::member())->get(route('network.posts.show', $carry))
        ->assertSee('Meine Worte')->assertSee('Dieser Tropfen ist nicht mehr verfügbar.');
});

it('makes read-only accounts read, delete and unreact – but not write or react', function () {
    $user = N::readOnlyMember();
    $own = N::post($user);
    $other = N::post(N::member());
    Reaction::create(['post_id' => $other->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $this->get(route('app.home'))->assertOk()->assertSee('Dein Konto ist gerade nur zum Lesen.');
    NetworkActions::publish(['body' => 'x', 'visibility' => 'public'])->assertForbidden();
    NetworkActions::post('react', $other->id)->assertForbidden();
    NetworkActions::post('unreact', $other->id)->assertHasNoErrors();
    NetworkActions::post('deletePost', $own->id)->assertHasNoErrors();
    expect(Post::whereKey($own->id)->exists())->toBeFalse()->and(Reaction::count())->toBe(0);
});

it('prefills the report form with the Tropfen link', function () {
    $this->get(route('oasis.melden', ['url' => 'https://oasis.tld/tropfen/5']))->assertOk()
        ->assertSeeLivewire(ContentNotice::class);
    Livewire::withQueryParams(['url' => 'https://oasis.tld/tropfen/5'])->test(ContentNotice::class)
        ->assertSet('form.url', 'https://oasis.tld/tropfen/5');
});

it('keeps the shorter edge at one pixel when scaling extremely narrow images', function (int $width, int $height, array $expectedSize) {
    $author = N::member();

    NetworkActions::publish([
        'body' => 'Schmales Bild', 'visibility' => 'public',
        'images' => [UploadedFile::fake()->image('schmal.png', $width, $height)], 'alts' => ['Ein schmaler Streifen'],
    ], $author)->assertHasNoErrors();

    $image = Post::sole()->images()->sole();
    expect([$image->width, $image->height])->toBe($expectedSize);
    $bytes = Storage::disk(config('oasis.media_disk'))->get($image->path);
    expect(array_slice(getimagesizefromstring($bytes), 0, 2))->toBe($expectedSize);
})->with([
    'portrait' => [1, 12000, [1, 2048]],
    'landscape' => [12000, 1, [2048, 1]],
]);
