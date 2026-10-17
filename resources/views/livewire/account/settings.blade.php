{{--
    Account settings (route app.settings, /app/einstellungen, App\Livewire\Account\Settings).

    Blocks and their Livewire actions / endpoints:
      Profil                 wire:submit="updateProfile"     (display_name, email; username is fixed)
      Passwort               wire:submit="updatePassword"
      Zwei-Faktor-Anmeldung  enableTwoFactor / confirmTwoFactor / regenerateRecoveryCodes / disableTwoFactor
      Deine Daten            GET account.export              (ZIP download, GDPR Art. 15/20)
      Konto löschen          wire:submit="deleteAccount"     (requires a recent password confirmation)
--}}
@php
    $card = 'flex flex-col gap-4 rounded-[24px] border border-paper-line bg-paper p-[clamp(20px,3vw,32px)]';
    $h2 = 'm-0 text-[21px] font-extrabold text-night';
@endphp

<div class="flex flex-col gap-6">
    <x-network.page-header eyebrow="Konto" title="Einstellungen">
        Profil, Passwort, Anmeldung und deine Daten an einem Ort. Neu hier? <a href="{{ route('app.welcome') }}" wire:navigate class="text-night underline underline-offset-4">Einstieg noch einmal ansehen</a>.
    </x-network.page-header>

    @if ($status !== '')
        <p role="status" aria-live="polite" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5">{{ $status }}</p>
    @else
        <p role="status" aria-live="polite" class="sr-only"></p>
    @endif

    {{-- Two columns of cards from 1280px: account data left, security and data rights right. --}}
    <div class="grid gap-6 xl:grid-cols-2 xl:items-start">
        <div class="flex flex-col gap-6">
            <section aria-labelledby="profil-titel" class="{{ $card }}">
                <h2 id="profil-titel" class="{{ $h2 }}">Profil</h2>
                <form wire:submit="updateProfile" class="flex flex-col gap-4" novalidate>
                    <x-oasis.field name="display_name" label="Anzeigename" :value="$display_name" wire:model="display_name" autocomplete="name" maxlength="50" required />
                    <div class="flex flex-col gap-1.5">
                        <span class="text-[15px] font-bold">Benutzername</span>
                        <span class="flex min-h-[52px] items-center rounded-[14px] bg-dune px-4 text-[17px] font-bold">{{ '@'.$user->username }}</span>
                        <span class="text-sm text-muted">Dein Benutzername bleibt fest, damit Links zu deinem Profil nicht ins Leere führen.</span>
                    </div>
                    <x-oasis.field name="email" type="email" label="E-Mail-Adresse" :value="$email" wire:model="email" autocomplete="email" required
                                   hint="Wenn du sie änderst, bestätigst du die neue Adresse per Link." />
                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Profil speichern</button>
                </form>
            </section>

            <section aria-labelledby="passwort-titel" class="{{ $card }}">
                <h2 id="passwort-titel" class="{{ $h2 }}">Passwort</h2>
                <form wire:submit="updatePassword" class="flex flex-col gap-4" novalidate>
                    <x-oasis.field name="current_password" type="password" label="Aktuelles Passwort" wire:model="current_password" autocomplete="current-password" required />
                    <x-oasis.field name="password" type="password" label="Neues Passwort" wire:model="password" autocomplete="new-password" minlength="10" required hint="Mindestens 10 Zeichen" />
                    <x-oasis.field name="password_confirmation" type="password" label="Neues Passwort wiederholen" wire:model="password_confirmation" autocomplete="new-password" minlength="10" required />
                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Passwort ändern</button>
                </form>
            </section>
        </div>

        <div class="flex flex-col gap-6">
            <section aria-labelledby="zwei-faktor-titel" class="{{ $card }}">
                <h2 id="zwei-faktor-titel" class="{{ $h2 }}">Zwei-Faktor-Anmeldung</h2>
                @if ($twoFactorActive)
                    <p class="m-0 text-[15px] text-ink-2">Aktiv. Bei der Anmeldung fragen wir zusätzlich nach einem Code aus deiner Authenticator-App.</p>
                    @if ($showRecoveryCodes)
                        <div class="flex flex-col gap-2">
                            <p class="m-0 text-[15px] font-bold text-night">Bewahre diese Wiederherstellungscodes sicher auf. Jeder funktioniert einmal.</p>
                            <ul class="m-0 grid list-none grid-cols-2 gap-2 rounded-2xl bg-dune p-4 font-mono text-sm">
                                @foreach ($user->recoveryCodes() as $recoveryCode)
                                    <li wire:key="recovery-{{ $loop->index }}">{{ $recoveryCode }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-3">
                        <form wire:submit="regenerateRecoveryCodes">
                            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline">Neue Wiederherstellungscodes</button>
                        </form>
                        <form wire:submit="disableTwoFactor">
                            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline">Ausschalten</button>
                        </form>
                    </div>
                @elseif ($twoFactorPending)
                    <p class="m-0 text-[15px] text-ink-2">Scanne den QR-Code mit deiner Authenticator-App und gib den angezeigten Code ein.</p>
                    <div class="self-start rounded-2xl bg-white p-4" role="img" aria-label="QR-Code für die Authenticator-App">{!! $user->twoFactorQrCodeSvg() !!}</div>
                    <form wire:submit="confirmTwoFactor" class="flex flex-col gap-4" novalidate>
                        <x-oasis.field name="code" label="Sechsstelliger Code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" required />
                        <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Bestätigen</button>
                    </form>
                @else
                    <p class="m-0 text-[15px] text-ink-2">Schütze dein Konto zusätzlich mit einem Code aus einer Authenticator-App.</p>
                    <form wire:submit="enableTwoFactor">
                        <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline">Einschalten</button>
                    </form>
                @endif
            </section>

            <section aria-labelledby="daten-titel" class="{{ $card }}">
                <h2 id="daten-titel" class="{{ $h2 }}">Deine Daten</h2>
                <p class="m-0 text-[15px] text-ink-2">Lade deine Daten herunter: Konto, Profil, Tropfen mit Bildern, wem du folgst, Oasen und Gemerktes – als ZIP-Datei. Verschlüsselte Chat-Nachrichten können wir nicht lesen und deshalb nicht exportieren.</p>
                <a href="{{ route('account.export') }}" class="o-btn o-btn-md o-btn-outline self-start">Daten herunterladen</a>
            </section>

            <section aria-labelledby="loeschen-titel" class="{{ $card }} border-sun-text">
                <h2 id="loeschen-titel" class="{{ $h2 }}">Konto löschen</h2>
                <p class="m-0 text-[15px] text-ink-2">Dein Konto und alle Daten dazu werden endgültig gelöscht. Eine laufende Mitgliedschaft kündigst du vorher über „<a wire:navigate href="{{ route('oasis.kuendigen') }}" class="text-night underline underline-offset-4">Verträge hier kündigen</a>“.</p>
                <form wire:submit="deleteAccount">
                    <label class="flex items-start gap-3 text-[15px] leading-normal">
                        <input type="checkbox" name="confirm" value="1" wire:model="confirm" required class="mt-0.5 size-5 flex-none accent-night">
                        <span>Ja, ich möchte mein Konto endgültig löschen.</span>
                    </label>
                    @error('confirm')
                        <p role="alert" class="o-error mt-2 mb-0">{{ $message }}</p>
                    @enderror
                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md mt-4 bg-sun-text text-white">Konto endgültig löschen</button>
                </form>
            </section>
        </div>
    </div>
</div>
