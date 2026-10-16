<?php

namespace App\Livewire\Moderation\Forms;

use App\Enums\Moderation\Ground;
use App\Enums\Moderation\Outcome;
use App\Livewire\Moderation\Concerns\ModerationValidation;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * A human statement of reasons under DSA Art. 17: outcome, ground with the rule or legal provision, the facts and
 * the explanation the affected person reads. Used for a case's first decision (CaseDetail) and for a decision that
 * replaces it after a complaint (ComplaintReview).
 */
class DecisionForm extends Form
{
    use ModerationValidation;

    /** An App\Enums\Moderation\Outcome value. */
    public string $outcome = '';

    /** An App\Enums\Moderation\Ground value. */
    public string $ground = '';

    public string $rule_reference = '';

    public string $facts = '';

    public string $explanation = '';

    /**
     * All five parts are required; nothing else is accepted.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'outcome' => ['required', Rule::enum(Outcome::class)],
            'ground' => ['required', Rule::enum(Ground::class)],
            'rule_reference' => ['required', 'string', 'max:255'],
            'facts' => ['required', 'string', 'max:10000'],
            'explanation' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * The validated statement in the shape DecideCase and ReviewComplaint expect.
     *
     * @return array{outcome: string, ground: string, rule_reference: string, facts: string, explanation: string}
     */
    public function statement(): array
    {
        return $this->validate();
    }
}
