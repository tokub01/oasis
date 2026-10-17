<?php

namespace App\Actions\Account;

use App\Models\Follow;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;
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

        $posts = Post::where('user_id', $user->id)->with(['images', 'oasis', 'revisions'])->orderBy('id')->get();
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
        /** @var Collection<int, Oasis&object{pivot: Pivot}> $oases */
        $oases = $user->oases()->get();

        return [
            'konto' => $user->only([
                'id', 'display_name', 'username', 'email', 'birth_date', 'email_verified_at',
                'terms_accepted_at', 'trial_ends_at', 'created_at', 'updated_at',
            ]),
            'profil' => [
                'beschreibung' => $profile->bio, 'ort' => $profile->location,
                'farbe' => $profile->avatar_color->value,
            ],
            'privatsphaere' => $user->privacy()->only([
                'profile_audience', 'default_post_visibility', 'follower_count_audience', 'location_audience',
                'oases_audience', 'mentions_audience', 'replies_audience', 'carry_audience', 'chat_invites_audience',
                'searchable', 'search_engines', 'login_alerts',
            ]) + ['verborgene_woerter' => $user->privacy()->hiddenWords()],
            'tropfen' => $posts->map(fn (Post $post) => [
                'id' => $post->id,
                'text' => $post->body,
                'sichtbarkeit' => $post->visibility->value,
                'oase' => $post->oasis?->name,
                'antwort_auf_tropfen' => $post->reply_to_id,
                'weitergetragen_von_tropfen' => $post->carried_post_id,
                'geschrieben_am' => $post->created_at->toIso8601String(),
                'bearbeitet_am' => $post->edited_at?->toIso8601String(),
                'fruehere_fassungen' => $post->revisions->map(fn ($revision) => [
                    'text' => $revision->body, 'gueltig_bis' => $revision->replaced_at->toIso8601String(),
                ])->values()->all(),
                'bilder' => $images[$post->id] ?? [],
            ])->values()->all(),
            'ich_folge' => [
                'menschen' => User::whereIn('id', $follows->where('followable_type', $user->getMorphClass())->pluck('followable_id'))->pluck('username')->values()->all(),
                'oasen' => Oasis::whereIn('id', $follows->where('followable_type', (new Oasis)->getMorphClass())->pluck('followable_id'))->pluck('name')->values()->all(),
            ],
            'oasen' => $oases->map(fn ($oasis) => ['name' => $oasis->name, 'rolle' => $oasis->pivot->getAttribute('role')])->values()->all(),
            'gemerkt' => $user->bookmarks()->pluck('post_id')->values()->all(),
            'eingeschraenkt' => User::whereIn('id', $user->restrictedUserIds())->pluck('username')->values()->all(),
            'bekannte_geraete' => $user->knownDevices()->orderBy('label')->get()
                ->map(fn ($device) => ['geraet' => $device->label, 'zuletzt' => $device->last_seen_at->toIso8601String()])->values()->all(),
            'push_geraete' => $user->pushSubscriptions()->orderBy('id')->get()
                ->map(fn ($device) => ['geraet' => $device->device, 'seit' => $device->created_at->toIso8601String()])->values()->all(),
            'hinweise' => 'Verschlüsselte Chat-Nachrichten sind nicht enthalten: Sie liegen nur verschlüsselt auf unserem Server und lassen sich nur in deinem Browser lesen.',
        ];
    }
}
