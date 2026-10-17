<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a profile photo from the private media disk after the same checks as the profile page: visible to the
 * viewer (privacy profile_audience), nobody blocked. The URL carries the file name, so a new photo is a new URL and
 * browsers may cache privately.
 */
class ProfilePhotoController extends Controller
{
    public function __invoke(Request $request, string $handle, string $file): StreamedResponse
    {
        $user = User::where('username', mb_strtolower($handle))->whereNotNull('email_verified_at')->with('profile')->firstOrFail();
        $path = $user->profile?->photo_path;
        abort_unless($path !== null && basename($path) === $file, 404);
        abort_if($request->user()?->isBlockedWith($user) ?? false, 404);
        abort_unless($user->profileOrDefault()->isVisibleTo($request->user()), 404);

        return Storage::disk(config('oasis.media_disk'))->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
