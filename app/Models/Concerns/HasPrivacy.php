<?php

namespace App\Models\Concerns;

use App\Models\KnownDevice;
use App\Models\PrivacySetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** The privacy settings of an account (Privatsphäre-Center), kept out of User.php. Used by {@see User}. */
trait HasPrivacy
{
    /** @return HasOne<PrivacySetting, $this> */
    public function privacySetting(): HasOne
    {
        return $this->hasOne(PrivacySetting::class);
    }

    /** The stored settings, or unsaved privacy-by-default ones for accounts that never changed anything. */
    public function privacy(): PrivacySetting
    {
        return $this->privacySetting ?? (new PrivacySetting)->forceFill(['user_id' => $this->id]);
    }

    /**
     * Browsers and systems this account signed in with (login alerts).
     *
     * @return HasMany<KnownDevice, $this>
     */
    public function knownDevices(): HasMany
    {
        return $this->hasMany(KnownDevice::class);
    }
}
