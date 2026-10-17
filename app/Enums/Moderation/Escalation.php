<?php

namespace App\Enums\Moderation;

/**
 * The two situations in which a case leaves the normal queue (docs/oasis-moderation.md, "Eskalation"). Oasis never
 * contacts an authority by itself: the checklist tells staff what to do, and staff record a report they made by hand.
 */
enum Escalation: string
{
    /** Art. 18 DSA: information giving rise to a suspicion of a criminal offence threatening life or safety. */
    case ThreatToLife = 'threat_to_life';

    /** Depictions of the sexual abuse of children (§ 184b StGB) or of youths (§ 184c StGB). */
    case ChildAbuse = 'child_abuse';

    public function label(): string
    {
        return match ($this) {
            self::ThreatToLife => 'Gefahr für Leib und Leben',
            self::ChildAbuse => 'Missbrauchsdarstellung',
        };
    }

    /** One sentence for the button and the confirmation. */
    public function description(): string
    {
        return match ($this) {
            self::ThreatToLife => 'Der Inhalt lässt eine Straftat befürchten, die das Leben oder die Sicherheit von Menschen bedroht – etwa eine Suizid-, Amok- oder Gewaltankündigung (Art. 18 DSA).',
            self::ChildAbuse => 'Der Inhalt zeigt oder beschreibt sexuellen Missbrauch von Kindern oder Jugendlichen. Der Tropfen wird sofort für alle gesperrt, auch für das Team.',
        };
    }

    /**
     * What staff do next, in order. Oasis sends nothing to any authority; a report is made by a person.
     *
     * @return list<string>
     */
    public function checklist(): array
    {
        return match ($this) {
            self::ThreatToLife => [
                'Bei akuter Gefahr sofort den Polizei-Notruf 110 anrufen und die gesicherten Angaben durchgeben.',
                'Sonst unverzüglich die zuständige Strafverfolgungsbehörde informieren (Art. 18 DSA) – in Deutschland das Bundeskriminalamt. Kontaktweg: siehe unten.',
                'Die gesicherten Beweise unten prüfen. Keine eigenen Ermittlungen, kein Kontakt zur betroffenen Person.',
                'Die Meldung unten mit Datum und Aktenzeichen vermerken.',
                'Danach den Fall wie gewohnt entscheiden (Inhalt entfernen, Konto sperren …) und begründen.',
            ],
            self::ChildAbuse => [
                'Den Inhalt nicht öffnen, nicht herunterladen und nicht weitergeben. Der Tropfen und seine Bilder sind bereits für alle gesperrt.',
                'Beweise sind gesichert (Text, Metadaten, Prüfsummen, Bilder). Herunterladen dürfen sie nur Admins, ausschließlich für eine Übergabe an die Polizei.',
                'Anzeige bei der Polizei oder der Zentralstelle der Staatsanwaltschaft erstatten. Oasis meldet nichts selbst.',
                'Die Meldung unten mit Datum und Aktenzeichen vermerken. Die Beweise werden nach der Frist automatisch gelöscht; bei Bedarf verlängern.',
                'Den Fall entscheiden: Inhalt entfernen und das Konto sperren. Die Quarantäne bleibt, bis ein Admin sie aufhebt.',
            ],
        };
    }
}
