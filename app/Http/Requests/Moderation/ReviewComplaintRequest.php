<?php

namespace App\Http\Requests\Moderation;

use Illuminate\Validation\Rule;

/** Validate and authorize the ReviewComplaintRequest endpoint before its workflow action executes. */
class ReviewComplaintRequest extends ModerationRequest
{
    /** Check the route's policy before exposing validation details. */
    public function authorize(): bool
    {
        return $this->user()->can('reviewComplaint', $this->route('complaint'));
    }

    /**
     * Accept only the fields needed for this workflow transition.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['result' => ['required', Rule::in(['upheld', 'overturned'])], 'explanation' => ['required', 'string', 'max:10000']] + ($this->input('result') === 'overturned' ? $this->decisionRules() : []);
    }
}
