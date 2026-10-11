<?php

namespace App\Enums\Moderation;

/** Values shared by moderation persistence, authorization and the German staff UI. */
enum CaseStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case Decided = 'decided';
    case ComplaintOpen = 'complaint_open';
    case Closed = 'closed';

    /** Return the German display name used in forms and notices. */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::InReview => 'In Prüfung',
            self::Decided => 'Entschieden',
            self::ComplaintOpen => 'Beschwerde offen',
            self::Closed => 'Abgeschlossen',
        };
    }
}
