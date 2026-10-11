<?php

namespace App\Http\Requests\Moderation;

use Illuminate\Validation\Rule;

/** Validate and authorize the AssignCaseRequest endpoint before its workflow action executes. */
class AssignCaseRequest extends ModerationRequest
{
    /** Check the route's policy before exposing validation details. */
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('case'));
    }

    /**
     * Accept only the fields needed for this workflow transition.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', ['moderator', 'admin'])]];
    }
}
