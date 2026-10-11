<?php

namespace App\Http\Requests\Network;

use App\Enums\Network\Visibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Texts and settings of an oasis (create and edit). Authorization happens in the controller via OasisPolicy. */
class OasisRequest extends FormRequest
{
    public const COLORS = ['sun', 'spring', 'night', 'dune'];

    /** @return array<string, mixed> */
    public function rules(): array
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
    public function messages(): array
    {
        return [
            'name.required' => 'Gib der Oase einen Namen.',
            'description.required' => 'Beschreib in einem Satz, worum es geht.',
            'house_rules.required' => 'Jede Oase braucht Hausregeln. Sie gelten zusätzlich zu den Community-Regeln.',
        ];
    }
}
