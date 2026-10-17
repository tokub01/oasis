<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One personal data breach in the register (Art. 33 Abs. 5 DSGVO), managed by admins on /admin/datenpannen.
 *
 * Risk decides the duties: none → document only; risk → report to the supervisory authority within 72 hours of
 * discovery (Art. 33); high → additionally inform the people affected without undue delay (Art. 34). Closed entries
 * are pruned after `oasis.privacy.records_years`.
 *
 * @property int $id
 * @property string $title
 * @property CarbonInterface $discovered_at
 * @property string $description
 * @property string $data_categories
 * @property int|null $affected_people
 * @property string $risk none|risk|high
 * @property string $consequences
 * @property string $measures
 * @property CarbonInterface|null $authority_reported_at
 * @property string|null $authority_reference
 * @property CarbonInterface|null $people_notified_at
 * @property CarbonInterface|null $closed_at
 * @property list<array{at: string, by: string, text: string}> $log
 */
class DataBreach extends Model
{
    use MassPrunable;

    public const RISKS = [
        'none' => 'Kein Risiko – nur dokumentieren',
        'risk' => 'Risiko – Meldung an die Aufsichtsbehörde binnen 72 Stunden',
        'high' => 'Hohes Risiko – zusätzlich Betroffene benachrichtigen',
    ];

    /** Hours after discovery within which a breach with risk must be reported (Art. 33 Abs. 1 DSGVO). */
    public const DEADLINE_HOURS = 72;

    protected $fillable = ['title', 'discovered_at', 'description', 'data_categories', 'affected_people', 'risk',
        'consequences', 'measures', 'log', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['discovered_at' => 'datetime', 'authority_reported_at' => 'datetime', 'people_notified_at' => 'datetime',
            'closed_at' => 'datetime', 'log' => 'array', 'affected_people' => 'integer'];
    }

    public function mustReport(): bool
    {
        return $this->risk !== 'none';
    }

    public function mustNotifyPeople(): bool
    {
        return $this->risk === 'high';
    }

    public function reportDueAt(): CarbonInterface
    {
        return $this->discovered_at->copy()->addHours(self::DEADLINE_HOURS);
    }

    /** A report is still owed (Art. 33 Abs. 1: a later report needs the reasons for the delay). */
    public function reportOutstanding(): bool
    {
        return $this->mustReport() && $this->authority_reported_at === null;
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        $years = config('oasis.privacy.records_years');

        return $years === null
            ? static::query()->whereRaw('1 = 0')
            : static::query()->whereNotNull('closed_at')->where('closed_at', '<', now()->subYears((int) $years));
    }
}
