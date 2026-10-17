<?php

namespace App\Support\Privacy;

use App\Models\DataBreach;

/**
 * Drafts the report to the supervisory authority with the contents Art. 33 Abs. 3 DSGVO requires, from the register
 * entry. Staff copy it into the authority's form or letter and check it; Oasis sends nothing itself.
 */
final class BreachReport
{
    public static function text(DataBreach $breach): string
    {
        $operator = config('oasis.operator');
        $berlin = fn ($date) => $date->timezone('Europe/Berlin')->format('d.m.Y, H:i').' Uhr';
        $late = now()->greaterThan($breach->reportDueAt()) && $breach->authority_reported_at === null;

        return implode("\n\n", array_filter([
            'Meldung einer Verletzung des Schutzes personenbezogener Daten nach Art. 33 DSGVO',
            'Verantwortlicher: '.$operator['legal_name'].', '.$operator['street'].', '.$operator['city']
                ."\nKontakt für Rückfragen (kein Datenschutzbeauftragter benannt): ".config('oasis.mail.privacy').', '.$operator['phone'],
            'Bezeichnung: '.$breach->title."\nBekannt geworden am: ".$berlin($breach->discovered_at),
            "a) Art der Verletzung\n".$breach->description
                ."\nBetroffene Datenkategorien: ".$breach->data_categories
                ."\nUngefähre Zahl betroffener Personen: ".($breach->affected_people === null ? 'noch nicht bekannt' : number_format($breach->affected_people, 0, ',', '.')),
            "b) Ansprechpartner\n".config('oasis.mail.privacy'),
            "c) Wahrscheinliche Folgen\n".$breach->consequences,
            "d) Ergriffene oder vorgeschlagene Maßnahmen\n".$breach->measures
                .($breach->mustNotifyPeople() ? "\nDie betroffenen Personen werden nach Art. 34 DSGVO benachrichtigt." : ''),
            $late ? "Gründe für die Meldung nach Ablauf von 72 Stunden\n[bitte ergänzen]" : null,
        ]));
    }
}
