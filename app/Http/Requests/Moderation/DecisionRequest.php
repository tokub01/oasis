<?php

namespace App\Http\Requests\Moderation;

/** Validate and authorize the DecisionRequest endpoint before its workflow action executes. */
class DecisionRequest extends ModerationRequest
{
    /** Check the route's policy before exposing validation details. */
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('case'));
    }

    /**
     * Accept only the fields needed for this workflow transition.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->decisionRules();
    }
}
