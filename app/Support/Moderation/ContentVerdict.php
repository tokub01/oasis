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
        'extremism' => 'Extremismus/Volksverhetzung',
        'harassment' => 'Mobbing',
        'sexual' => 'Sexueller Inhalt',
        'sexual_minors' => 'Sexueller Bezug zu Minderjährigen',
    ];

    /** Hostile categories: nudged before sending, held when clear (hold_abuse_threshold). */
    private const ABUSE = ['insult', 'hate', 'threat', 'extremism', 'harassment'];

    /**
     * @param  non-empty-array<string, float>  $scores  category => 0..1
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

    /** Hidden until a human reviewed it: clearly sexual (or any sexual reference to minors), or clearly abusive. */
    public function shouldHold(): bool
    {
        return $this->holdsSexual() || $this->holdsAbuse();
    }

    /**
     * Why a Tropfen is held, for its author (post card, ContentHeldNotice), fitting "… hat möglicherweise {reason}
     * erkannt"; null when nothing is held.
     */
    public function holdReason(): ?string
    {
        if ($this->holdsSexual()) {
            return 'sexuelle Inhalte';
        }
        if (! $this->holdsAbuse()) {
            return null;
        }
        $phrases = ['insult' => 'eine Beleidigung', 'hate' => 'Hass oder Hetze gegen Menschen', 'threat' => 'eine Drohung',
            'extremism' => 'extremistische oder volksverhetzende Inhalte', 'harassment' => 'Mobbing gegen eine Person'];
        $strongest = 'insult';
        foreach (self::ABUSE as $category) {
            if ($this->scores[$category] > $this->scores[$strongest]) {
                $strongest = $category;
            }
        }

        return $phrases[$strongest];
    }

    private function holdsSexual(): bool
    {
        return $this->scores['sexual'] >= (float) config('oasis.moderation.hold_threshold')
            || $this->scores['sexual_minors'] >= (float) config('oasis.moderation.report_threshold');
    }

    private function holdsAbuse(): bool
    {
        return $this->abuseScore() >= (float) config('oasis.moderation.hold_abuse_threshold');
    }

    private function abuseScore(): float
    {
        return max(array_map(fn (string $category) => $this->scores[$category], self::ABUSE));
    }

    /** Hostile tone worth a "Nochmal lesen?" hint before sending. */
    public function shouldNudge(): bool
    {
        return $this->abuseScore() >= (float) config('oasis.moderation.nudge_threshold');
    }

    /** Sexual content, threats and extremism (possibly criminal, §§ 86a, 130, 241 StGB) are urgent for the queue. */
    public function isUrgent(): bool
    {
        $report = (float) config('oasis.moderation.report_threshold');

        return $this->holdsSexual() || $this->scores['threat'] >= $report || $this->scores['extremism'] >= $report;
    }

    /** @return array{scores: non-empty-array<string, float>, reason: string, source: string} */
    public function toArray(): array
    {
        return ['scores' => $this->scores, 'reason' => $this->reason, 'source' => $this->source];
    }
}
