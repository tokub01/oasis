<?php

namespace App\Support\Moderation;

/**
 * Result of an automated content check: one score (0–1) per category, a short reason and where it came from.
 * Thresholds live in `config('oasis.moderation')` so they can be tuned without code changes.
 */
final class ContentVerdict
{
    /** Categories in display order with German labels for the moderation view. */
    public const CATEGORIES = [
        'insult' => 'Beleidigung',
        'hate' => 'Hass/Hetze',
        'threat' => 'Drohung',
        'sexual' => 'Sexueller Inhalt',
        'sexual_minors' => 'Sexueller Bezug zu Minderjährigen',
    ];

    /**
     * @param  array<string, float>  $scores  category => 0..1
     * @param  string  $source  'ai' (language model) or 'wordlist' (fallback)
     */
    public function __construct(
        public readonly array $scores,
        public readonly string $reason,
        public readonly string $source,
    ) {}

    /** @param array<string, mixed> $data structured agent output or stored JSON */
    public static function fromArray(array $data, string $source): self
    {
        $scores = [];
        foreach (array_keys(self::CATEGORIES) as $category) {
            $scores[$category] = max(0.0, min(1.0, (float) ($data[$category] ?? 0)));
        }

        return new self($scores, mb_substr(trim((string) ($data['reason'] ?? '')), 0, 500), $source);
    }

    public static function clean(string $source): self
    {
        return self::fromArray([], $source);
    }

    public function highest(): float
    {
        return max($this->scores);
    }

    /** Worth a moderation case. */
    public function shouldReport(): bool
    {
        return $this->highest() >= (float) config('oasis.moderation.report_threshold');
    }

    /** Clearly sexual (or any sexual reference to minors): hidden until a human reviewed it. */
    public function shouldHold(): bool
    {
        return $this->scores['sexual'] >= (float) config('oasis.moderation.hold_threshold')
            || $this->scores['sexual_minors'] >= (float) config('oasis.moderation.report_threshold');
    }

    /** Hostile tone worth a "Nochmal lesen?" hint before sending. */
    public function shouldNudge(): bool
    {
        return max($this->scores['insult'], $this->scores['hate'], $this->scores['threat'])
            >= (float) config('oasis.moderation.nudge_threshold');
    }

    /** Sexual content or threats are urgent for the moderation queue. */
    public function isUrgent(): bool
    {
        return $this->shouldHold() || $this->scores['threat'] >= (float) config('oasis.moderation.report_threshold');
    }

    /** @return array{scores: array<string, float>, reason: string, source: string} */
    public function toArray(): array
    {
        return ['scores' => $this->scores, 'reason' => $this->reason, 'source' => $this->source];
    }
}
