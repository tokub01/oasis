<?php

namespace App\Models;

use App\Enums\Network\Visibility;
use App\Enums\Privacy\Audience;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A member's privacy choices (Privatsphäre-Center). Defaults follow privacy by default (GDPR Art. 25): members-only
 * where an audience applies, not shown to search engines. Accounts without a stored row use exactly these defaults
 * ({@see User::privacy()}), so nothing is written on a read.
 *
 * Every rule is enforced on the server through {@see allows()}; the settings page only changes the values.
 *
 * @property int $user_id
 * @property Audience $profile_audience who may open the profile page
 * @property Visibility $default_post_visibility preselected audience of new Tropfen
 * @property Audience $follower_count_audience who sees the size of the Karawane on the profile
 * @property Audience $location_audience who sees the location on the profile
 * @property Audience $oases_audience who sees the oases the member belongs to on the profile
 * @property Audience $mentions_audience whose @mentions reach the member (link and hint)
 * @property Audience $replies_audience who may answer the member's Tropfen
 * @property Audience $carry_audience who may carry the member's Tropfen further
 * @property Audience $chat_invites_audience who may start a chat with the member or add them to one
 * @property bool $searchable found by name and username in the Oasis search
 * @property bool $search_engines public profile and Tropfen may be indexed by search engines
 * @property bool $login_alerts mail when someone signs in from a browser or system not seen before
 * @property array<int, string>|null $hidden_words words hidden from the member's lists, hints and replies ({@see hides()})
 */
class PrivacySetting extends Model
{
    /** Audiences each setting offers, in the order the privacy center shows them. */
    public const AUDIENCES = [
        'profile_audience' => [Audience::Everyone, Audience::Members, Audience::Following],
        'follower_count_audience' => [Audience::Everyone, Audience::Members, Audience::Following, Audience::Nobody],
        'location_audience' => [Audience::Everyone, Audience::Members, Audience::Following, Audience::Nobody],
        'oases_audience' => [Audience::Everyone, Audience::Members, Audience::Following, Audience::Nobody],
        'mentions_audience' => [Audience::Members, Audience::Following, Audience::Nobody],
        'replies_audience' => [Audience::Members, Audience::Following, Audience::Nobody],
        'carry_audience' => [Audience::Members, Audience::Following, Audience::Nobody],
        'chat_invites_audience' => [Audience::Members, Audience::Following, Audience::Nobody],
    ];

    /** @var array<int, bool> Follow results only for explicitly checked viewer IDs. */
    private array $knownFollows = [];

    /** The account is never mass-assigned: rows are created through $user->privacySetting() (UpdatePrivacySetting). */
    protected $fillable = [
        'profile_audience', 'default_post_visibility', 'follower_count_audience', 'location_audience', 'oases_audience',
        'mentions_audience', 'replies_audience', 'carry_audience', 'chat_invites_audience', 'searchable',
        'search_engines', 'login_alerts', 'hidden_words',
    ];

    /** Privacy by default for accounts that never changed anything. */
    protected $attributes = [
        'profile_audience' => 'members', 'default_post_visibility' => 'members', 'follower_count_audience' => 'members',
        'location_audience' => 'members', 'oases_audience' => 'members',
        'mentions_audience' => 'members', 'replies_audience' => 'members', 'carry_audience' => 'members',
        'chat_invites_audience' => 'members', 'searchable' => true, 'search_engines' => false, 'login_alerts' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'profile_audience' => Audience::class, 'default_post_visibility' => Visibility::class,
            'follower_count_audience' => Audience::class, 'location_audience' => Audience::class,
            'oases_audience' => Audience::class, 'mentions_audience' => Audience::class,
            'replies_audience' => Audience::class, 'carry_audience' => Audience::class,
            'chat_invites_audience' => Audience::class, 'searchable' => 'boolean', 'search_engines' => 'boolean',
            'login_alerts' => 'boolean', 'hidden_words' => 'array', 'user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether $viewer belongs to the audience of one setting. The member always passes their own settings.
     * Blocks are not part of this: callers keep checking them separately, they always win.
     *
     * @param  string  $setting  a key of AUDIENCES; anything else is refused at runtime
     *
     * @throws InvalidArgumentException for a setting that is not an audience (e.g. searchable)
     */
    public function allows(string $setting, ?User $viewer): bool
    {
        if (! isset(self::AUDIENCES[$setting])) {
            throw new InvalidArgumentException('Not an audience setting: '.$setting);
        }
        $owner = $this->user_id;
        if ($viewer !== null && $viewer->id === $owner) {
            return true;
        }

        return match ($this->{$setting}) {
            Audience::Everyone => true,
            Audience::Members => $viewer?->hasVerifiedEmail() === true,
            Audience::Following => $viewer?->hasVerifiedEmail() === true && $this->ownerFollows($viewer),
            Audience::Nobody => false,
        };
    }

    /**
     * The member's hidden words, lower case.
     *
     * @return list<string>
     */
    public function hiddenWords(): array
    {
        return array_values($this->hidden_words ?? []);
    }

    /** Whether $text contains one of the hidden words (case-insensitive, also inside longer words, like the search). */
    public function hides(string $text): bool
    {
        $text = mb_strtolower($text);
        foreach ($this->hiddenWords() as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    /** Search engines may index public guest pages only when the profile is public and the member opts in. */
    public function indexable(): bool
    {
        return $this->search_engines && $this->profile_audience === Audience::Everyone;
    }

    /**
     * Prepare a request-local copy; unchecked viewers still use the database fallback.
     *
     * @param  list<int>  $checkedIds
     * @param  list<int>  $followedIds
     */
    public function withKnownFollows(array $checkedIds, array $followedIds): static
    {
        $settings = clone $this;
        $settings->knownFollows = array_fill_keys($checkedIds, false);
        foreach ($followedIds as $id) {
            if (array_key_exists($id, $settings->knownFollows)) {
                $settings->knownFollows[$id] = true;
            }
        }

        return $settings;
    }

    /**
     * Batch one checked circle for freshly queried owners or a caller-owned copy of the current user.
     *
     * @param  Collection<int, User>  $owners
     * @param  list<int>  $checkedIds
     */
    public static function preloadFollows(Collection $owners, array $checkedIds): void
    {
        if ($owners->isEmpty() || $checkedIds === []) {
            return;
        }
        $owners->loadMissing('privacySetting');
        $follows = Follow::whereIn('follower_id', $owners->modelKeys())
            ->where('followable_type', (new User)->getMorphClass())->whereIn('followable_id', $checkedIds)
            ->get()->groupBy('follower_id');
        foreach ($owners as $owner) {
            $followedIds = $follows->get($owner->id, new Collection)->pluck('followable_id')
                ->map(fn ($id) => (int) $id)->values()->all();
            $owner->setRelation('privacySetting', $owner->privacy()->withKnownFollows($checkedIds, array_values($followedIds)));
        }
    }

    /** The owner follows $viewer (the "Menschen, denen du folgst" circle). */
    private function ownerFollows(User $viewer): bool
    {
        if (array_key_exists($viewer->id, $this->knownFollows)) {
            return $this->knownFollows[$viewer->id];
        }

        return Follow::where('follower_id', $this->user_id)
            ->where('followable_type', $viewer->getMorphClass())
            ->where('followable_id', $viewer->id)
            ->exists();
    }
}
