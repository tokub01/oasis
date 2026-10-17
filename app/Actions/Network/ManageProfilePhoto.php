<?php

namespace App\Actions\Network;

use App\Models\User;
use App\Support\Media\WebpImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The optional profile photo (docs/oasis-network.md, "Profilbild"): a square WebP of SIZE pixels without metadata on
 * the private media disk. Replacing or removing it deletes the old file; DeleteAccount removes it with the account.
 */
class ManageProfilePhoto
{
    public const SIZE = 512;

    /** @throws ValidationException if the file is no readable image */
    public function store(User $user, UploadedFile $file): void
    {
        try {
            $image = WebpImage::encode($file, self::SIZE, square: true);
        } catch (InvalidArgumentException $failure) {
            throw ValidationException::withMessages(['photo' => $failure->getMessage()]);
        }
        $path = 'network/profiles/'.$user->id.'/'.Str::random(32).'.webp';
        Storage::disk(config('oasis.media_disk'))->put($path, $image['bytes']);

        $profile = $user->profile()->firstOrCreate([]);
        $old = $profile->photo_path;
        $profile->forceFill(['photo_path' => $path])->save();
        if ($old !== null) {
            Storage::disk(config('oasis.media_disk'))->delete($old);
        }
    }

    public function remove(User $user): void
    {
        $profile = $user->profile()->first();
        if ($profile?->photo_path === null) {
            return;
        }
        Storage::disk(config('oasis.media_disk'))->delete($profile->photo_path);
        $profile->forceFill(['photo_path' => null])->save();
    }
}
