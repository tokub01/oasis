<?php

namespace App\Support\Privacy;

use App\Enums\Network\Visibility;
use App\Enums\Privacy\Audience;
use App\Models\PrivacySetting;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * The settings of the Privatsphäre-Center as the member sees them: German title, a one-sentence explanation, the
 * choices and the validation rule. One source for the center and the guided check, so both always offer the same.
 */
final class PrivacyOptions
{
    /** Sections in display order: key => [title, settings]. */
    public const SECTIONS = [
        'sichtbarkeit' => ['Wer dich sieht', ['profile_audience', 'default_post_visibility', 'follower_count_audience', 'location_audience', 'oases_audience', 'oasis_list_audience']],
        'interaktionen' => ['Wer mit dir in Kontakt kommt', ['mentions_audience', 'replies_audience', 'carry_audience', 'chat_invites_audience', 'friend_requests', 'images_audience']],
        'auffindbarkeit' => ['Wie man dich findet', ['searchable', 'search_engines']],
        'sicherheit' => ['Wie wir dein Konto schützen', ['login_alerts']],
        'benachrichtigungen' => ['Wie wir dir neue Chat-Nachrichten melden', ['chat_email', 'chat_push']],
    ];

    /** Sections of the guided Privatsphäre-Check: who sees and reaches the member, not how they are notified. */
    public const CHECKUP_SECTIONS = ['sichtbarkeit', 'interaktionen', 'auffindbarkeit', 'sicherheit'];

    /**
     * Sections of the guided check in display order.
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    public static function checkupSections(): array
    {
        return array_values(array_intersect_key(self::SECTIONS, array_flip(self::CHECKUP_SECTIONS)));
    }

    /** Settings shown as a yes/no switch; all others are a choice of audiences. */
    public const SWITCHES = ['searchable', 'search_engines', 'login_alerts', 'chat_email', 'chat_push', 'friend_requests'];

    /** @var array<string, array{title: string, help: string}> */
    private const TEXTS = [
        'profile_audience' => ['title' => 'Wer darf dein Profil sehen?', 'help' => 'Deine Profilseite mit Beschreibung und allen deinen Tropfen. Wer einzelne Tropfen per Link lesen darf, legst du bei jedem Tropfen fest.'],
        'default_post_visibility' => ['title' => 'Wer liest neue Tropfen standardmäßig?', 'help' => 'Vorauswahl beim Schreiben – du kannst sie bei jedem Tropfen ändern.'],
        'follower_count_audience' => ['title' => 'Wer sieht, wie groß deine Karawane ist?', 'help' => 'Die Zahl der Menschen, die dir folgen. Namen zeigen wir nie öffentlich.'],
        'location_audience' => ['title' => 'Wer sieht deinen Ort?', 'help' => 'Der Ort aus deinem Profil. Alle anderen sehen dein Profil ohne ihn.'],
        'oases_audience' => ['title' => 'Wer sieht, in welchen Oasen du bist?', 'help' => 'Die Liste deiner Oasen auf deinem Profil. In der Oase selbst bleibst du als Mitglied sichtbar.'],
        'mentions_audience' => ['title' => 'Wer darf dich erwähnen?', 'help' => 'Bei allen anderen bleibt @dein-name reiner Text, ohne Link und ohne Hinweis an dich.'],
        'replies_audience' => ['title' => 'Wer darf auf deine Tropfen antworten?', 'help' => 'Reagieren und Merken bleibt für alle möglich, die den Tropfen lesen dürfen.'],
        'carry_audience' => ['title' => 'Wer darf deine Tropfen weitertragen?', 'help' => 'Weitertragen zeigt deinen Tropfen mit den Worten einer anderen Person.'],
        'chat_invites_audience' => ['title' => 'Wer darf dir schreiben?', 'help' => 'Wer einen verschlüsselten Chat mit dir beginnen oder dich in einen Gruppenchat holen darf. Bestehende Chats bleiben.'],
        'oasis_list_audience' => ['title' => 'Wer sieht dich in Mitgliederlisten von Oasen?', 'help' => 'Zu deinem eigenen Schutz ist voreingestellt, dass dich in den Mitgliederlisten deiner Oasen nur Freund:innen und Menschen sehen, denen du folgst. Alle anderen sehen dich dort nicht und können dich darüber weder anschreiben noch dein Profil öffnen. Du kannst das jederzeit umstellen.'],
        'friend_requests' => ['title' => 'Freundschaftsanfragen erhalten', 'help' => 'Ausgeschaltet kann dir niemand eine Freundschaftsanfrage schicken. Bestehende Freundschaften bleiben. Freundschaft und Folgen sind getrennt: Wer dir folgt, ist nicht automatisch befreundet.'],
        'images_audience' => ['title' => 'Wessen Bilder siehst du sofort?', 'help' => 'Alle anderen Bilder bleiben abgedeckt und werden nicht einmal geladen, bis du auf „Bild anzeigen“ tippst. So siehst du keine Bilder von Fremden, die du nicht sehen willst. Deine eigenen Bilder siehst du immer.'],
        'searchable' => ['title' => 'In der Oasis-Suche auffindbar', 'help' => 'Mitglieder finden dich über deinen Namen oder Benutzernamen. Ausgeschaltet erscheinst du nicht in der Personensuche; über deine Tropfen und Links bleibt dein Profil erreichbar.'],
        'search_engines' => ['title' => 'Öffentliches Profil in Suchmaschinen', 'help' => 'Gilt nur, solange dein Profil für alle sichtbar ist: Dann dürfen Suchmaschinen es und deine öffentlichen Tropfen anzeigen.'],
        'chat_email' => ['title' => 'E-Mail bei neuen Chat-Nachrichten', 'help' => 'Standardmäßig aus – schalte sie ein, wenn du nichts verpassen möchtest. Höchstens eine E-Mail pro Chat, bis du ihn geöffnet hast, nie in deiner Ruhezeit. Ohne Namen und ohne Text: Deine Chats bleiben verschlüsselt. In der App siehst du neue Nachrichten immer an der Zahl bei „Chats“.'],
        'chat_push' => ['title' => 'Push-Hinweis bei neuen Chat-Nachrichten', 'help' => 'Standardmäßig aus. Gilt für Geräte, auf denen du Push-Hinweise eingeschaltet hast (Hinweise einstellen). Ebenfalls ohne Namen und ohne Text, höchstens einmal pro Chat, bis du ihn geöffnet hast.'],
        'login_alerts' => ['title' => 'E-Mail bei Anmeldung von einem neuen Gerät', 'help' => 'Meldet sich jemand mit einem Browser oder System an, das wir bei dir noch nicht kennen, schicken wir dir sofort eine E-Mail.'],
    ];

    /**
     * All setting keys the member may change.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_merge(...array_map(fn (array $section) => $section[1], array_values(self::SECTIONS)));
    }

    /** @return array{title: string, help: string} */
    public static function text(string $key): array
    {
        return self::TEXTS[$key];
    }

    /** Yes/no settings render as a switch, the others as a choice of audiences. */
    public static function isSwitch(string $key): bool
    {
        return in_array($key, self::SWITCHES, true);
    }

    /**
     * Choices of a setting for $member: value => German label. Members under 18 get only the values they may choose.
     *
     * @return array<string, string>
     */
    public static function choices(string $key, ?User $member = null): array
    {
        /** @var array<string, string> $choices */
        $choices = $key === 'default_post_visibility'
            ? collect(Visibility::cases())->mapWithKeys(fn (Visibility $v) => [$v->value => $v->label()])->all()
            : collect(PrivacySetting::AUDIENCES[$key])->mapWithKeys(fn (Audience $a) => [$a->value => $a->label()])->all();

        return self::limited($key, $member)
            ? array_filter($choices, fn (string $value) => in_array($value, PrivacySetting::MINOR_CHOICES[$key], true), ARRAY_FILTER_USE_KEY)
            : $choices;
    }

    /** Whether $member is under 18 and this setting is limited for them. */
    public static function limited(string $key, ?User $member): bool
    {
        return $member?->isMinor() === true && isset(PrivacySetting::MINOR_CHOICES[$key]);
    }

    /**
     * Validation rule for one setting and member; unknown keys are rejected by the callers before this is reached.
     *
     * @return list<mixed>
     */
    public static function rule(string $key, ?User $member = null): array
    {
        if (self::isSwitch($key)) {
            return self::limited($key, $member) ? ['required', 'boolean', Rule::in(PrivacySetting::MINOR_CHOICES[$key])] : ['required', 'boolean'];
        }

        return ['required', 'string', Rule::in(array_keys(self::choices($key, $member)))];
    }

    /** German wording of a value: the audience label, or Ja/Nein for a switch. */
    public static function label(string $key, string|bool $value): string
    {
        return self::isSwitch($key) ? ($value ? 'Ja' : 'Nein') : (self::choices($key)[$value] ?? '');
    }

    /** The current value as the form shows it (enum value or bool). */
    public static function value(PrivacySetting $settings, string $key): string|bool
    {
        $value = $settings->{$key};

        return is_bool($value) ? $value : $value->value;
    }
}
