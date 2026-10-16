<?php

namespace App\Support\Network;

use App\Enums\Network\Visibility;
use Illuminate\Validation\Rule;

/** Texts and settings of an oasis (create and edit). The Livewire component authorizes each action via OasisPolicy. */
class OasisInput
{
    public const COLORS = ['sun', 'spring', 'night', 'dune'];

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:60'],
            'description' => ['required', 'string', 'max:300'],
            'house_rules' => ['required', 'string', 'max:3000'],
            'visibility' => ['required', Rule::enum(Visibility::class)],
            'color' => ['required', Rule::in(self::COLORS)],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Gib der Oase einen Namen.',
            'description.required' => 'Beschreib in einem Satz, worum es geht.',
            'house_rules.required' => 'Jede Oase braucht Hausregeln. Sie gelten zusätzlich zu den Community-Regeln.',
        ];
    }
}
