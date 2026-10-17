<?php

namespace Database\Seeders;

use App\Actions\Governance\ManageVotes;
use App\Actions\Moderation\AssignCase;
use App\Actions\Moderation\DecideCase;
use App\Actions\Network\ManageFollows;
use App\Actions\Network\ManageOasis;
use App\Actions\Network\StorePostImage;
use App\Enums\Role;
use App\Models\ContentReport;
use App\Models\ModerationCase;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Local demo content so the app can be tried out without an empty feed: sample members (emails end in
 * `@demo.oasis.tld`), the four oases from the design, Tropfen with replies, carries, reactions and a few generated
 * images and two votes. Every existing account follows the sample members and oases.
 *
 * Sample members sign in with their e-mail or username and the password `oasis-demo-2026` (self::PASSWORD).
 * Run: `sail artisan db:seed --class=DemoContentSeeder`. Refuses to run in production: the figures and people are
 * invented and must never appear on the live site. Running it twice adds nothing twice (keyed by email/slug).
 */
class DemoContentSeeder extends Seeder
{
    /** Shared password of the sample members, so they can be tried out locally (this seeder never runs in production). */
    public const PASSWORD = 'oasis-demo-2026';

    private const PEOPLE = [
        'mira' => ['Mira Sol', 'Fotografiert analog, meistens zu früh am Morgen. Gastgeberin von Korn & Licht.', 'Leipzig', 'sun'],
        'jonas' => ['Jonas Weber', 'Gärtnert auf 12 Quadratmetern Balkon und tauscht Ableger gegen gute Geschichten.', 'Köln', 'spring'],
        'aylin' => ['Aylin Demir', 'Liest langsam, schreibt Randnotizen. Ein Buch im Monat reicht.', 'Hamburg', 'night'],
        'lukas' => ['Lukas Brandt', 'Repariert Fahrräder im Hinterhof. Kettenöl ist mein Parfum.', 'Krefeld', 'dune'],
        'sara' => ['Sara Okafor', 'Kocht für zu viele Leute und teilt die Rezepte gleich mit.', 'Berlin', 'sun'],
        'ben' => ['Ben Hoffmann', 'Lehrer, Hobby-Imker, sammelt Sonnenaufgänge.', 'Freiburg', 'spring'],
        'lea' => ['Lea Marquardt', 'Illustriert Kinderbücher und Pflanzen. Meistens beides gleichzeitig.', 'Dresden', 'night'],
        'emil' => ['Emil Novak', 'Läuft morgens, schreibt abends. Dazwischen Software.', 'München', 'dune'],
    ];

    private const OASES = [
        'mira' => ['Korn & Licht', 'Analoge Fotografie: Filme, Kameras, Dunkelkammer und die Geduld dazwischen.', 'sun',
            "Zeigt eigene Bilder und nennt den Film, wenn ihr ihn wisst.\nKritik nur, wenn jemand darum bittet – dann ehrlich und freundlich.\nKeine Kaufberatung per Direktnachricht, fragt hier offen."],
        'jonas' => ['Stadtgarten', 'Gärtnern, tauschen, ernten – vom Balkon bis zum Gemeinschaftsbeet.', 'spring',
            "Tauschangebote mit Stadt angeben.\nKeine Pestizid-Werbung.\nAnfängerfragen sind ausdrücklich willkommen."],
        'aylin' => ['Lesekreis', 'Ein Buch im Monat, gemeinsam gelesen. Spoiler nur mit Ansage.', 'night',
            "Spoiler kennzeichnen: [Spoiler] am Anfang.\nBuchvorschläge bis zum 25. für den Folgemonat.\nJede Lesart ist erlaubt."],
        'lukas' => ['Rad & Werkstatt', 'Schrauben, flicken, fahren. Hilfe zur Selbsthilfe für alles mit Pedalen.', 'dune',
            "Fotos vom Problem helfen mehr als lange Beschreibungen.\nSicherheit geht vor: Bremsen im Zweifel in die Werkstatt.\nKeine Verkaufsanzeigen."],
    ];

    /** [author, oasis owner key or null, text, hours ago, image colour or null] */
    private const POSTS = [
        ['mira', 'mira', 'Heute um 5:40 Uhr am Kanal. Ilford HP5, auf 1600 gepusht. Das Korn ist genau so, wie ich es mag.', 2, 'dawn'],
        ['jonas', 'jonas', 'Die ersten Tomaten sind rot! Wer hat noch Saatgut von der Sorte "Ochsenherz" übrig? Ich tausche gegen Basilikum-Ableger.', 3, 'garden'],
        ['aylin', 'aylin', 'Oktober-Buch: "Die Wand" von Marlen Haushofer. Wir treffen uns am 30. im Thread. Wer liest mit?', 5, null],
        ['lukas', 'lukas', 'Kleiner Tipp für den Herbst: Kette jetzt reinigen und ölen, nicht erst wenn sie quietscht. Zwei Minuten, die sich lohnen.', 6, null],
        ['sara', null, 'Kürbissuppe für 14 Leute, und am Ende war trotzdem nichts übrig. Rezept: viel Ingwer, ein Schuss Kokosmilch, Geduld.', 8, 'soup'],
        ['ben', null, 'Die Bienen sind ruhiger geworden, sie bereiten sich auf den Winter vor. Ich lerne jedes Jahr etwas Neues von ihnen.', 11, null],
        ['lea', null, 'Neue Skizze fertig: ein Farn, der aussieht, als würde er gleich weglaufen. Manchmal zeichnen sich Pflanzen selbst.', 14, null],
        ['emil', null, '10 km heute Morgen, ohne Uhr. Einfach laufen, bis es reicht. Erstaunlich, wie ruhig der Kopf danach ist.', 20, null],
        ['mira', 'mira', 'Frage an die Runde: Entwickelt ihr selbst oder gebt ihr ab? Ich überlege, mir einen Wechselsack zu holen.', 26, null],
        ['jonas', null, 'Gemeinschaftsbeet in Ehrenfeld sucht noch Leute für den Gießplan im November. Zwei Stunden pro Woche, gute Gesellschaft garantiert.', 30, null],
        ['aylin', null, 'Ein Satz, der mich heute begleitet: "Man muss sich Sisyphos als einen glücklichen Menschen vorstellen."', 34, null],
        ['lukas', 'lukas', 'Samstag offene Werkstatt im Hinterhof, 10–14 Uhr. Bringt Platten, Schaltungen und Kuchen mit.', 40, 'workshop'],
        ['sara', null, 'Was ich an Oasis mag: Ich sehe einfach, was meine Leute schreiben. Keine Werbung dazwischen. Das ist alles.', 46, null],
        ['ben', null, 'Sonnenaufgang Nummer 214 in meiner Sammlung. Heute mit Nebel über dem Feld.', 52, 'dawn'],
        ['emil', null, 'Habe das Handy abends in eine Schublade gelegt. Drei Tage lang. Ich empfehle es weiter.', 60, null],
        ['lea', null, 'Kleine Freude: ein Kind hat in der Bibliothek mein Buch ausgeliehen und es seiner Oma vorgelesen.', 70, null],
    ];

    /** [author, reply to post index, text] */
    private const REPLIES = [
        ['ben', 0, 'Das Licht! Ich stehe auch immer zu früh auf, aber nie mit der Kamera.'],
        ['lea', 0, 'Wunderschön. Darf ich das als Vorlage für eine Zeichnung nehmen?'],
        ['sara', 1, 'Ich habe noch Ochsenherz-Samen, schreib mir!'],
        ['emil', 2, 'Bin dabei. Das Buch liegt schon seit Jahren auf meinem Stapel.'],
        ['aylin', 4, 'Rezept bitte ausführlicher, ich will das nachkochen.'],
        ['jonas', 11, 'Ich komme mit meinem quietschenden Hollandrad vorbei.'],
        ['mira', 13, 'Nebel ist das beste Licht. Wunderbar.'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoContentSeeder only runs outside production.');
        }

        $people = collect(self::PEOPLE)->map(fn (array $person, string $handle) => $this->person($handle, ...$person));
        $oases = collect(self::OASES)->map(fn (array $oasis, string $host) => $this->oasis($people[$host], ...$oasis));
        $this->connect($people, $oases);

        if (! Post::whereIn('user_id', $people->pluck('id'))->exists()) {
            $posts = $this->posts($people, $oases);
            $this->conversation($people, $posts);
            $this->governance();
        }
        if (! ModerationCase::exists()) {
            $this->moderation($people);
        }
    }

    /**
     * Sample moderation work: reports on demo Tropfen in every state (open, urgent, assigned, decided with a
     * complaint). A demo moderator with a dummy confirmed 2FA acts in it; nobody can sign in as that account.
     */
    private function moderation($people): void
    {
        $moderator = User::firstOrCreate(['email' => 'moderation@demo.oasis.tld'], [
            'display_name' => 'Moderationsteam (Beispiel)', 'username' => 'moderation_demo', 'birth_date' => '1990-01-01',
            'password' => str()->random(32), 'terms_accepted_at' => now(),
        ]);
        $moderator->forceFill([
            'email_verified_at' => now(), 'role' => Role::Moderator,
            'two_factor_secret' => encrypt(str()->random(32)), 'two_factor_confirmed_at' => now(),
        ])->save();

        $reports = [
            ['sara', 'Das ist Werbung für einen Lieferdienst, keine Geschichte.', 30, 'normal'],
            ['emil', 'Der Tropfen enthält eine Telefonnummer einer anderen Person.', 6, 'urgent'],
            ['lukas', 'Verkaufsanzeige trotz Hausregeln der Oase.', 50, 'normal'],
            ['ben', 'Beleidigung in der Antwort darunter.', 70, 'normal'],
        ];
        $cases = [];
        foreach ($reports as [$author, $reason, $hoursAgo, $priority]) {
            $post = Post::where('user_id', $people[$author]->id)->whereNull('reply_to_id')->first();
            $report = $this->at(now()->subHours($hoursAgo), fn () => ContentReport::create([
                'url' => route('network.posts.show', $post), 'reason' => $reason, 'name' => 'Beispiel Meldende Person',
                'email' => 'meldung@demo.oasis.tld', 'truthful_confirmed_at' => now(),
            ]));
            $case = ModerationCase::where('source_type', $report->getMorphClass())->where('source_id', $report->id)->sole();
            $case->forceFill(['priority' => $priority, 'subject_user_id' => $post->user_id])->save();
            $cases[] = $case;
        }

        app(AssignCase::class)->handle($cases[2], $moderator, $moderator);
        Mail::fake(); // the sample decision must not send notices to the demo addresses
        app(AssignCase::class)->handle($cases[3], $moderator, $moderator);
        app(DecideCase::class)->handle($cases[3], $moderator, [
            'outcome' => 'no_action', 'ground' => 'community_rules', 'rule_reference' => 'Community-Regeln, Abschnitt 2',
            'facts' => 'Die gemeldete Antwort ist deutlich formuliert, enthält aber keine Beleidigung.',
            'explanation' => 'Kritik am Inhalt ist erlaubt. Wir lassen den Tropfen stehen.',
        ]);
    }

    private function person(string $handle, string $name, string $bio, string $location, string $color): User
    {
        $user = User::firstOrCreate(['email' => $handle.'@demo.oasis.tld'], [
            'display_name' => $name, 'username' => $handle, 'birth_date' => '1990-01-01', 'password' => self::PASSWORD,
            'terms_accepted_at' => now()->subMonths(2), 'trial_ends_at' => now()->addDays(20),
        ]);
        $user->forceFill(['email_verified_at' => now()->subMonths(2), 'created_at' => now()->subMonths(rand(1, 6))])->save();
        $user->profile()->updateOrCreate([], ['bio' => $bio, 'location' => $location, 'visibility' => 'public', 'avatar_color' => $color]);

        return $user;
    }

    private function oasis(User $host, string $name, string $description, string $color, string $rules): Oasis
    {
        return Oasis::where('name', $name)->first() ?? app(ManageOasis::class)->create($host, [
            'name' => $name, 'description' => $description, 'house_rules' => $rules, 'visibility' => 'public', 'color' => $color,
        ]);
    }

    /** Sample members follow each other and two oases each; every real account follows everyone and every oasis. */
    private function connect($people, $oases): void
    {
        $follows = app(ManageFollows::class);
        $list = $people->values();
        foreach ($list as $index => $person) {
            foreach ([1, 2, 3] as $offset) {
                $follows->follow($person, $list[($index + $offset) % $list->count()]);
            }
            $follows->follow($person, $oases->values()[$index % 4]);
            $follows->follow($person, $oases->values()[($index + 1) % 4]);
        }
        foreach (User::where('email', 'not like', '%@demo.oasis.tld')->whereNotNull('email_verified_at')->get() as $member) {
            $people->each(fn (User $person) => $follows->follow($member, $person));
            $oases->each(fn (Oasis $oasis) => $follows->follow($member, $oasis));
            // Others follow back, so the Karawane and "Deine Zahlen" are not empty either.
            $people->take(5)->each(fn (User $person) => $follows->follow($person, $member));
        }
    }

    /** @return list<Post> */
    private function posts($people, $oases): array
    {
        $posts = [];
        foreach (self::POSTS as [$author, $oasisHost, $text, $hoursAgo, $image]) {
            $post = $this->at(Carbon::now()->subHours($hoursAgo), fn () => Post::create([
                'user_id' => $people[$author]->id, 'oasis_id' => $oasisHost ? $oases[$oasisHost]->id : null,
                'body' => $text, 'visibility' => 'public',
            ]));
            if ($image) {
                $this->attachImage($post, $image);
            }
            $posts[] = $post;
        }
        // Carried further with own words.
        $this->at(Carbon::now()->subHour(), fn () => Post::create([
            'user_id' => $people['ben']->id, 'carried_post_id' => $posts[9]->id, 'visibility' => 'public',
            'body' => 'Weitergetragen, weil Gemeinschaftsbeete die besten Orte der Stadt sind. Wer aus Köln ist: hin da!',
        ]));

        return $posts;
    }

    private function conversation($people, array $posts): void
    {
        foreach (self::REPLIES as $index => [$author, $postIndex, $text]) {
            $parent = $posts[$postIndex];
            $this->at($parent->created_at->copy()->addMinutes(20 + $index * 7), fn () => Post::create([
                'user_id' => $people[$author]->id, 'reply_to_id' => $parent->id, 'oasis_id' => $parent->oasis_id,
                'body' => $text, 'visibility' => 'public',
            ]));
        }
        foreach ($posts as $index => $post) {
            foreach ($people->values()->take(2 + $index % 6) as $person) {
                if ($person->id !== $post->user_id) {
                    Reaction::firstOrCreate(['post_id' => $post->id, 'user_id' => $person->id]);
                }
            }
        }
    }

    private function governance(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();
        $votes = app(ManageVotes::class);
        $votes->create($admin, [
            'title' => 'Wohin mit einem Überschuss?', 'description' => "Wenn in einem Monat mehr hereinkommt als hinausgeht:\nWofür sollen wir es verwenden?",
            'starts_at' => now()->subDays(2), 'ends_at' => now()->addDays(12), 'options' => ['Mehr Patenplätze', 'Eine Rücklage', 'Neue Funktionen'],
        ]);
        $ended = $votes->create($admin, [
            'title' => 'Soll die Quelle eine Suchfunktion bekommen?', 'description' => 'Eine Suche nach Tropfen und Oasen, ohne Ranking nach Klicks.',
            'starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(26), 'options' => ['Ja', 'Nein', 'Später'],
        ]);
        foreach ([[0, 412], [1, 96], [2, 151]] as [$position, $count]) {
            $ended->options[$position]->forceFill(['votes_count' => $count])->save();
        }
    }

    /** Draw a small abstract picture with GD and attach it like an upload (re-encoded, no metadata). */
    private function attachImage(Post $post, string $motif): void
    {
        [$top, $bottom, $alt] = match ($motif) {
            'dawn' => [[242, 144, 107], [14, 43, 47], 'Sonnenaufgang über einem Kanal, körnig, in warmen Farben'],
            'garden' => [[46, 158, 149], [234, 223, 204], 'Tomatenpflanzen in Kübeln auf einem Balkon'],
            'soup' => [[224, 106, 67], [251, 248, 242], 'Ein großer Topf Kürbissuppe auf einem Holztisch'],
            default => [[214, 200, 176], [14, 43, 47], 'Ein Fahrrad kopfüber im Hinterhof, daneben Werkzeug'],
        };
        $image = imagecreatetruecolor(1200, 800);
        for ($y = 0; $y < 800; $y++) {
            $mix = $y / 799;
            $color = imagecolorallocate($image, ...array_map(fn ($a, $b) => (int) ($a + ($b - $a) * $mix), $top, $bottom));
            imageline($image, 0, $y, 1199, $y, $color);
        }
        imagefilledellipse($image, 860, 300, 220, 220, imagecolorallocate($image, 251, 248, 242));
        $path = tempnam(sys_get_temp_dir(), 'oasis-demo');
        imagepng($image, $path);
        app(StorePostImage::class)->handle($post, new UploadedFile($path, $motif.'.png', 'image/png', null, true), $alt, 0);
        @unlink($path);
    }

    /** Create a record "at" a past time so the feed shows realistic ages. */
    private function at(CarbonInterface $time, callable $create): mixed
    {
        Carbon::setTestNow($time);
        try {
            return $create();
        } finally {
            Carbon::setTestNow();
        }
    }
}
