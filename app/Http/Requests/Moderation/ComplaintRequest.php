<?php

namespace App\Http\Requests\Moderation;

use App\Models\ModerationDecision;
use App\Support\Moderation\ComplaintAccess;
use Illuminate\Support\Facades\Gate;

/** Validate and authorize the ComplaintRequest endpoint before its workflow action executes. */
class ComplaintRequest extends ModerationRequest
{
    /** Check the route's policy before exposing validation details. */
    public function authorize(): bool
    {
        /** @var ModerationDecision $decision */
        $decision = $this->route('decision');
        $recipient = app(ComplaintAccess::class)->recipient($this, $decision);

        return Gate::allows('submitComplaint', [$decision, $recipient]);
    }

    /**
     * Accept only the fields needed for this workflow transition.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:10000']];
    }
}
