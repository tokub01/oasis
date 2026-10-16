<?php

namespace App\Livewire\Account;

use App\Actions\Account\DeleteAccount;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Account settings on /app/einstellungen (route app.settings).
 *
 * Calls Fortify's profile, password and two-factor authentication actions and App\Actions\Account\DeleteAccount
 * directly without full-page reloads. Sensitive actions (2FA changes and account deletion) enforce a recent
 * password confirmation matching Laravel's `password.confirm` timeout.
 */
class Settings extends Component
{
    /** Translated German status messages for Fortify session status keys. */
    private const STATUS_MESSAGES = [
        'profile-information-updated' => 'Dein Profil ist gespeichert.',
        'password-updated' => 'Dein Passwort ist geändert.',
        'two-factor-authentication-enabled' => 'Scanne jetzt den QR-Code und bestätige mit einem Code.',
        'two-factor-authentication-confirmed' => 'Die Zwei-Faktor-Anmeldung ist aktiv.',
        'two-factor-authentication-disabled' => 'Die Zwei-Faktor-Anmeldung ist ausgeschaltet.',
        'recovery-codes-generated' => 'Du hast neue Wiederherstellungscodes.',
    ];

    /** Member's editable display name. */
    public string $display_name = '';

    /** Member's editable email address. */
    public string $email = '';

    /** Current password for password changes. */
    public string $current_password = '';

    /** New password for password changes. */
    public string $password = '';

    /** New password confirmation for password changes. */
    public string $password_confirmation = '';

    /** Six-digit TOTP code when confirming 2FA setup. */
    public string $code = '';

    /** Explicit confirmation checkbox for permanent account deletion. */
    public bool $confirm = false;

    /** Status message announced via role="status". */
    #[Locked]
    public string $status = '';

    /** Whether recovery codes should be displayed after confirming 2FA or regenerating codes. */
    #[Locked]
    public bool $showRecoveryCodes = false;

    /**
     * Populate the current profile fields and adopt any flashed Fortify status or error bags.
     */
    public function mount(): void
    {
        $user = $this->actor();
        $this->display_name = (string) $user->display_name;
        $this->email = (string) $user->email;

        $sessionStatus = session('status');
        if (is_string($sessionStatus) && isset(self::STATUS_MESSAGES[$sessionStatus])) {
            $this->status = self::STATUS_MESSAGES[$sessionStatus];
            $this->showRecoveryCodes = in_array($sessionStatus, [
                'two-factor-authentication-confirmed',
                'recovery-codes-generated',
            ], true);
        }

        $errors = session('errors');
        if ($errors instanceof ViewErrorBag) {
            foreach (['updateProfileInformation', 'updatePassword', 'confirmTwoFactorAuthentication'] as $bagName) {
                foreach ($errors->getBag($bagName)->getMessages() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->addError($field, $message);
                    }
                }
            }
        }
    }

    /**
     * Update the authenticated member's display name and email address via Fortify's UpdatesUserProfileInformation.
     */
    public function updateProfile(UpdatesUserProfileInformation $updater): void
    {
        $user = $this->actor();
        $this->resetErrorBag();

        $updater->update($user, [
            'display_name' => $this->display_name,
            'email' => $this->email,
        ]);

        $user->refresh();
        $this->display_name = (string) $user->display_name;
        $this->email = (string) $user->email;
        $this->status = self::STATUS_MESSAGES['profile-information-updated'];
    }

    /**
     * Update the authenticated member's password via Fortify's UpdatesUserPasswords.
     */
    public function updatePassword(UpdatesUserPasswords $updater): void
    {
        $user = $this->actor();
        $this->resetErrorBag();

        // The fields are cleared in every case, so no password stays in the HTML snapshot or the back-button cache.
        try {
            $updater->update($user, [
                'current_password' => $this->current_password,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);
        } finally {
            $this->reset(['current_password', 'password', 'password_confirmation']);
        }

        $this->status = self::STATUS_MESSAGES['password-updated'];
    }

    /**
     * Enable two-factor authentication (pending confirmation) after verifying recent password confirmation.
     */
    public function enableTwoFactor(EnableTwoFactorAuthentication $enable): void
    {
        $user = $this->actor();
        if (! $this->ensurePasswordIsConfirmed()) {
            return;
        }

        $enable($user);
        $this->showRecoveryCodes = false;
        $this->status = self::STATUS_MESSAGES['two-factor-authentication-enabled'];
    }

    /**
     * Confirm two-factor authentication with a six-digit authenticator code.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirm): void
    {
        $user = $this->actor();
        if (! $this->ensurePasswordIsConfirmed()) {
            return;
        }

        $this->resetErrorBag();
        $confirm($user, $this->code);

        $this->code = '';
        $this->showRecoveryCodes = true;
        $this->status = self::STATUS_MESSAGES['two-factor-authentication-confirmed'];
    }

    /**
     * Generate a fresh set of two-factor recovery codes after verifying recent password confirmation.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $user = $this->actor();
        if (! $this->ensurePasswordIsConfirmed()) {
            return;
        }

        $generate($user);
        $this->showRecoveryCodes = true;
        $this->status = self::STATUS_MESSAGES['recovery-codes-generated'];
    }

    /**
     * Disable two-factor authentication on the signed-in account after verifying recent password confirmation.
     */
    public function disableTwoFactor(DisableTwoFactorAuthentication $disable): void
    {
        $user = $this->actor();
        if (! $this->ensurePasswordIsConfirmed()) {
            return;
        }

        $disable($user);
        $this->showRecoveryCodes = false;
        $this->status = self::STATUS_MESSAGES['two-factor-authentication-disabled'];
    }

    /**
     * Permanently delete the signed-in account (GDPR Art. 17) after verifying recent password confirmation
     * and explicit checkbox consent.
     */
    public function deleteAccount(DeleteAccount $delete): void
    {
        $user = $this->actor();
        if (! $this->ensurePasswordIsConfirmed()) {
            return;
        }

        $this->resetErrorBag();
        $this->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Bitte bestätige, dass du dein Konto endgültig löschen möchtest.',
        ]);

        $delete->handle($user);

        Auth::guard('web')->forgetUser();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('status', 'Dein Konto wurde gelöscht.');

        $this->redirectRoute('oasis.home', navigate: true);
    }

    /**
     * Render the account settings page.
     */
    public function render(): View
    {
        $user = $this->actor()->fresh();

        return view('livewire.account.settings', [
            'user' => $user,
            'twoFactorPending' => $user->two_factor_secret && ! $user->two_factor_confirmed_at,
            'twoFactorActive' => $user->two_factor_secret && $user->two_factor_confirmed_at,
        ])->layout('layouts.app', ['title' => 'Einstellungen', 'active' => 'einstellungen', 'wide' => true]);
    }

    /**
     * Re-authorize the signed-in user on every request. A verified e-mail address is not required: settings and
     * account deletion must stay usable while a changed address waits for confirmation.
     */
    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Enforce Laravel's recent password confirmation window (`auth.password_timeout`), redirecting to
     * `password.confirm` when expired or missing.
     */
    private function ensurePasswordIsConfirmed(): bool
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);
        $timeout = (int) config('auth.password_timeout', 10800);

        if ((time() - $confirmedAt) < $timeout) {
            return true;
        }

        session()->put('url.intended', route('app.settings'));

        $this->redirectRoute('password.confirm', navigate: true);

        return false;
    }
}
