<?php

namespace App\Actions\Privacy;

use App\Models\PrivacySetting;
use App\Models\User;
use App\Support\Privacy\PrivacyOptions;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Changes one privacy setting of a member. The first change stores a row; all other settings keep their
 * privacy-by-default values. Unknown keys and values outside the offered choices are rejected.
 */
class UpdatePrivacySetting
{
    /**
     * @throws InvalidArgumentException for a key the privacy center does not offer
     * @throws ValidationException for a value outside the choices
     */
    public function handle(User $member, string $key, mixed $value): PrivacySetting
    {
        if (! in_array($key, PrivacyOptions::keys(), true)) {
            throw new InvalidArgumentException('Unknown privacy setting: '.$key);
        }
        $value = Validator::make(['value' => $value], ['value' => PrivacyOptions::rule($key, $member)])->validate()['value'];

        // Through the relation, so the account is set by the server and never mass-assigned. A first row of a member
        // under 18 starts from the strict values, never from the looser column defaults.
        $settings = $member->privacySetting()->firstOrCreate([], $member->isMinor() ? PrivacySetting::MINOR_DEFAULTS : []);
        $settings->update([$key => $value]);
        $member->setRelation('privacySetting', $settings);

        return $settings;
    }
}
