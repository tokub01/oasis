<?php

namespace App\Http\Requests\Moderation;

use App\Enums\Role;
use Illuminate\Validation\Rule;

/** Validate and authorize the RoleRequest endpoint before its workflow action executes. */
class RoleRequest extends ModerationRequest
{
    /** Check the route's policy before exposing validation details. */
    public function authorize(): bool
    {
        return $this->user()->can('manageRoles');
    }

    /**
     * Accept only the fields needed for this workflow transition.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(Role::class)]];
    }
}
