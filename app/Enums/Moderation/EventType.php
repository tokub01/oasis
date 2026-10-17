<?php

namespace App\Enums\Moderation;

/** Values shared by moderation persistence, authorization and the German staff UI. */
enum EventType: string
{
    case Opened = 'opened';
    case Assigned = 'assigned';
    case Decided = 'decided';
    case Notified = 'notified';
    case ComplaintReceived = 'complaint_received';
    case ComplaintDecided = 'complaint_decided';
    case Note = 'note';
    case Escalated = 'escalated';
    case AuthorityReported = 'authority_reported';
    case EvidenceExtended = 'evidence_extended';
    case EvidenceDownloaded = 'evidence_downloaded';
    case EvidenceDeleted = 'evidence_deleted';
    case QuarantineLifted = 'quarantine_lifted';

    /** Return the German display name used in forms and notices. */
    public function label(): string
    {
        return match ($this) {
            self::Opened => 'Eröffnet',
            self::Assigned => 'Zugewiesen',
            self::Decided => 'Entschieden',
            self::Notified => 'Benachrichtigt',
            self::ComplaintReceived => 'Beschwerde eingegangen',
            self::ComplaintDecided => 'Beschwerde entschieden',
            self::Note => 'Notiz',
            self::Escalated => 'Eskaliert, Beweise gesichert',
            self::AuthorityReported => 'Behördenmeldung vermerkt',
            self::EvidenceExtended => 'Beweis-Aufbewahrung verlängert',
            self::EvidenceDownloaded => 'Beweise heruntergeladen',
            self::EvidenceDeleted => 'Beweise gelöscht',
            self::QuarantineLifted => 'Quarantäne aufgehoben',
        };
    }
}
