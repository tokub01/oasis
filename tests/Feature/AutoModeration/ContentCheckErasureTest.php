<?php

/* Art. 17 GDPR: automated checks disappear with their text, unless a moderation case is based on them. */

use App\Actions\Account\DeleteAccount;
use App\Actions\Network\DeletePost;
use App\Actions\Network\ManageOasis;
use App\Models\ContentCheck;
use App\Models\ModerationCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\NetworkFixtures as N;

function checkOf(Model $checkable): ContentCheck
{
    return ContentCheck::firstOrCreate(
        ['checkable_type' => $checkable->getMorphClass(), 'checkable_id' => $checkable->getKey()],
        ['status' => 'checked'],
    );
}

function caseFor(ContentCheck $check): ModerationCase
{
    return ModerationCase::create(['source_type' => $check->getMorphClass(), 'source_id' => $check->id, 'subject_url' => 'https://oasis.tld']);
}

it('deletes the checks of all texts of a deleted account but keeps those a case is based on', function () {
    $user = N::member();
    $other = N::member();
    $profile = checkOf($user->profile()->create(['bio' => 'Ich fotografiere.']));
    $post = checkOf(N::post($user));
    $reported = checkOf(N::post($user));
    $oasis = checkOf(N::oasis($user));
    $foreign = checkOf(N::post($other));
    caseFor($reported);

    app(DeleteAccount::class)->handle($user);

    expect(ContentCheck::pluck('id')->sort()->values()->all())->toBe([$reported->id, $foreign->id]);
    $this->assertModelMissing($profile);
    $this->assertModelMissing($post);
    $this->assertModelMissing($oasis);
});

it('deletes the check of a deleted Tropfen and of a closed oasis', function () {
    $user = N::member();
    $post = N::post($user);
    $oasis = N::oasis($user);
    $postCheck = checkOf($post);
    $oasisCheck = checkOf($oasis);
    $kept = checkOf(N::post($user));

    app(DeletePost::class)->handle($post);
    app(ManageOasis::class)->close($oasis);

    $this->assertModelMissing($postCheck);
    $this->assertModelMissing($oasisCheck);
    $this->assertModelExists($kept);
});

it('prunes orphaned checks left by foreign-key cascades daily, but not case sources', function () {
    config(['oasis.privacy.records_years' => null]);
    $user = N::member();
    $orphan = checkOf(N::post($user));
    $reported = checkOf(N::post($user));
    caseFor($reported);
    $other = checkOf(N::post(N::member()));

    User::whereKey($user->id)->delete();
    $this->artisan('model:prune', ['--model' => [ContentCheck::class]])->assertSuccessful();

    $this->assertModelMissing($orphan);
    $this->assertModelExists($reported);
    $this->assertModelExists($other);
});
