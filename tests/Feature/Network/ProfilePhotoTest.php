<?php

/* Profilbild (docs/oasis-network.md): optional, square WebP without metadata, shown only where allowed. */

use App\Actions\Account\DeleteAccount;
use App\Actions\Account\ExportAccount;
use App\Livewire\Network\ProfileEdit;
use App\Models\Follow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake(config('oasis.media_disk'));
});

it('stores a square WebP photo, replaces and removes it', function () {
    $me = N::member();

    Livewire::actingAs($me)->test(ProfileEdit::class)
        ->set('photo', UploadedFile::fake()->image('ich.jpg', 900, 600))->assertHasNoErrors()
        ->assertSet('postStatus', 'Dein Profilbild ist gespeichert.');
    $path = $me->fresh()->profile->photo_path;
    expect($path)->toStartWith('network/profiles/'.$me->id.'/')->toEndWith('.webp');
    [$width, $height] = getimagesizefromstring(Storage::disk(config('oasis.media_disk'))->get($path));
    expect([$width, $height])->toBe([512, 512]);

    Livewire::actingAs($me)->test(ProfileEdit::class)->set('photo', UploadedFile::fake()->image('neu.png', 300, 300));
    Storage::disk(config('oasis.media_disk'))->assertMissing($path);

    Livewire::actingAs($me)->test(ProfileEdit::class)->call('removePhoto');
    expect($me->fresh()->profile->photo_path)->toBeNull();
    Storage::disk(config('oasis.media_disk'))->assertDirectoryEmpty('network/profiles/'.$me->id);
});

it('shows the photo only where the profile is visible and images of this person are allowed', function () {
    $owner = N::member();
    Livewire::actingAs($owner)->test(ProfileEdit::class)->set('photo', UploadedFile::fake()->image('ich.jpg', 400, 400));
    $url = $owner->fresh()->profile->photoUrl();

    $stranger = N::member();
    $this->actingAs($stranger)->get(route('oasis.profil', $owner->username))->assertOk()->assertDontSee($url, false);
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');

    Follow::create(['follower_id' => $stranger->id, 'followable_type' => $owner->getMorphClass(), 'followable_id' => $owner->id]);
    $this->actingAs($stranger->fresh())->get(route('oasis.profil', $owner->username))->assertSee($url, false);

    auth()->logout();
    $this->get($url)->assertNotFound();
});

it('deletes the photo with the account and puts it into the data export', function () {
    $me = N::member();
    Livewire::actingAs($me)->test(ProfileEdit::class)->set('photo', UploadedFile::fake()->image('ich.jpg', 400, 400));

    $zipPath = app(ExportAccount::class)->handle($me->fresh());
    $zip = new ZipArchive;
    $zip->open($zipPath);
    expect($zip->locateName('bilder/profilbild.webp'))->not->toBeFalse();
    $zip->close();
    unlink($zipPath);

    app(DeleteAccount::class)->handle($me->fresh());
    Storage::disk(config('oasis.media_disk'))->assertMissing('network/profiles/'.$me->id);
});
