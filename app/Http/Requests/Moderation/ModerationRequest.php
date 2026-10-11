<?php

namespace App\Http\Requests\Moderation;

use App\Enums\Moderation\Ground;
use App\Enums\Moderation\Outcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared German form vocabulary and Art. 17 decision fields for staff moderation. */
abstract class ModerationRequest extends FormRequest
{
    /**
     * Require all parts of a human statement of reasons.
     *
     * @return array<string, mixed>
     */
    protected function decisionRules(): array
    {
        return [
            'outcome' => ['required', Rule::enum(Outcome::class)],
            'ground' => ['required', Rule::enum(Ground::class)],
            'rule_reference' => ['required', 'string', 'max:255'],
            'facts' => ['required', 'string', 'max:10000'],
            'explanation' => ['required', 'string', 'max:10000'],
        ];
    }

    /** Keep errors German even if the application locale changes. */
    public function messages(): array
    {
        return [
            'required' => 'Bitte fülle das Feld :attribute aus.',
            'string' => 'Das Feld :attribute muss ein Text sein.',
            'max' => 'Das Feld :attribute darf höchstens :max Zeichen enthalten.',
            'integer' => 'Bitte wähle eine gültige Person aus.',
            'exists' => 'Bitte wähle eine Person aus dem Moderationsteam aus.',
            'enum' => 'Bitte wähle einen gültigen Wert für :attribute aus.',
            'in' => 'Bitte wähle einen gültigen Wert für :attribute aus.',
        ];
    }

    /** Name form fields in the German validation messages. */
    public function attributes(): array
    {
        return ['assigned_to' => 'zuständige Person', 'outcome' => 'Maßnahme', 'ground' => 'Grundlage',
            'rule_reference' => 'Regel oder Rechtsvorschrift', 'facts' => 'Tatsachen', 'explanation' => 'Begründung',
            'result' => 'Ergebnis', 'role' => 'Rolle', 'reason' => 'Beschwerdebegründung'];
    }
}
