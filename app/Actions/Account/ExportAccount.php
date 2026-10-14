<?php

namespace App\Actions\Account;

use App\Models\Follow;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Builds the personal data export (Art. 15/20 GDPR; AGB: "Daten und Tropfen herunterladen") as a ZIP file:
 * `oasis-daten.json` with account, profile, Tropfen, follows, oases, bookmarks and hint settings, plus the own
 * images under `bilder/`. Every field is allowlisted – passwords, 2FA secrets and other people's data never enter it.
 * Encrypted chat messages are not included: the server cannot read them (see `hinweise` in the JSON).
 */
class ExportAccount
{
    /**
     * @return string absolute path of a temporary ZIP file; the caller sends and deletes it
     */
    public function handle(User $user): string
    {
        $path = tempnam(sys_get_temp_dir(), 'oasis-export');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $posts = Post::where('user_id', $user->id)->with(['images', 'oasis'])->orderBy('id')->get();
        $disk = Storage::disk(config('oasis.media_disk'));
        $images = [];
        foreach ($posts as $post) {
            foreach ($post->images as $image) {
                $file = 'bilder/tropfen-'.$post->id.'-'.$image->position.'.webp';
                if ($disk->exists($image->path)) {
                    $zip->addFromString($file, (string) $disk->get($image->path));
                    $images[$post->id][] = ['datei' => $file, 'beschreibung' => $image->alt];
                }
            }
        }

        $zip->addFromString('oasis-daten.json', json_encode(
            $this->data($user, $posts, $images),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
        $zip->close();

        return $path;
    }

    /**
     * @param  Collection<int, Post>  $posts
     * @param  array<int, list<array{datei: string, beschreibung: string}>>  $images
     * @return array<string, mixed>
     */
    private function data(User $user, $posts, array $images): array
    {
        $profile = $user->profileOrDefault();
        $follows = Follow::where('follower_id', $user->id)->get();

        return [
            'konto' => $user->only([
                'id', 'display_name', 'username', 'email', 'birth_date', 'email_verified_at',
                'terms_accepted_at', 'trial_ends_at', 'created_at', 'updated_at',
            ]),
            'profil' => [
                'beschreibung' => $profile->bio, 'ort' => $profile->location,
                'sichtbarkeit' => $profile->visibility->value, 'farbe' => $profile->avatar_color->value,
            ],
            'tropfen' => $posts->map(fn (Post $post) => [
                'id' => $post->id,
                'text' => $post->body,
                'sichtbarkeit' => $post->visibility->value,
                'oase' => $post->oasis?->name,
                'antwort_auf_tropfen' => $post->reply_to_id,
                'weitergetragen_von_tropfen' => $post->carried_post_id,
                'geschrieben_am' => $post->created_at->toIso8601String(),
                'bilder' => $images[$post->id] ?? [],
            ])->values()->all(),
            'ich_folge' => [
                'menschen' => User::whereIn('id', $follows->where('followable_type', $user->getMorphClass())->pluck('followable_id'))->pluck('username')->values()->all(),
                'oasen' => Oasis::whereIn('id', $follows->where('followable_type', (new Oasis)->getMorphClass())->pluck('followable_id'))->pluck('name')->values()->all(),
            ],
            'oasen' => $user->oases()->get()->map(fn (Oasis $oasis) => ['name' => $oasis->name, 'rolle' => $oasis->pivot->role])->values()->all(),
            'gemerkt' => $user->bookmarks()->pluck('post_id')->values()->all(),
            'hinweise' => 'Verschlüsselte Chat-Nachrichten sind nicht enthalten: Sie liegen nur verschlüsselt auf unserem Server und lassen sich nur in deinem Browser lesen.',
        ];
    }
}
