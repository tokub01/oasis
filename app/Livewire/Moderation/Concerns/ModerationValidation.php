<?php

namespace App\Livewire\Moderation\Concerns;

/**
 * German validation vocabulary of the staff moderation area, shared by its components and the decision form.
 * Livewire picks up messages() and validationAttributes() automatically when validating.
 */
trait ModerationValidation
{
    /**
     * Keep errors German even if the application locale changes.
     *
     * @return array<string, string>
     */
    protected function messages(): array
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

    /**
     * Name the fields in the German messages.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['outcome' => 'Maßnahme', 'ground' => 'Grundlage', 'rule_reference' => 'Regel oder Rechtsvorschrift',
            'facts' => 'Tatsachen', 'explanation' => 'Begründung', 'assigned_to' => 'zuständige Person', 'result' => 'Ergebnis',
            'roles.*' => 'Rolle', 'reason' => 'Beschwerdebegründung'];
    }
}
