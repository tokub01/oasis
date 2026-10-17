{{-- Start-list form, component App\Livewire\Oasis\WaitlistSignup (embedded in oasis.gruendung). --}}
<form wire:submit="submit" aria-labelledby="liste-titel" class="flex flex-col gap-[18px] [padding:clamp(28px,_3vw,_40px)] bg-sand text-ink rounded-[28px]">
    @include('oasis.forms._feedback')
    <h2 id="liste-titel" class="m-0 text-[26px] [line-height:1.25] font-extrabold text-night">Auf die Startliste</h2>
    <div class="flex flex-col gap-[6px]">
        <label for="gr-mail" class="text-[15px] font-bold">E-Mail-Adresse</label>
        <input id="gr-mail" type="email" autocomplete="email" placeholder="name@beispiel.tld" class="o-input min-w-0" wire:model="form.email" aria-invalid="{{ $errors->has('form.email') ? 'true' : 'false' }}" aria-describedby="gr-mail-error" required>
        <p id="gr-mail-error" class="o-error">@error('form.email'){{ $message }}@enderror</p>
    </div>
    <div class="flex flex-col gap-[6px]">
        <label for="gr-gruppe" class="text-[15px] font-bold">Mit wem willst du starten? <span class="font-medium text-muted">(optional)</span>
        </label>
        <input id="gr-gruppe" type="text" placeholder="z. B. WG oder Fachschaft" class="o-input min-w-0" wire:model="form.group" aria-invalid="{{ $errors->has('form.group') ? 'true' : 'false' }}" aria-describedby="gr-gruppe-error">
        <p id="gr-gruppe-error" class="o-error">@error('form.group'){{ $message }}@enderror</p>
    </div>
    <label class="flex gap-[12px] items-start text-[15px] [line-height:1.5] text-ink">
        <input type="checkbox" wire:model="form.consent" required class="flex-none [width:20px] [height:20px] [margin:2px_0_0] accent-night">
        <span>Ich möchte per E-Mail erfahren, wann ich reinkomme. Abmelden geht jederzeit.</span>
    </label>
    <button type="submit" wire:loading.attr="disabled" class="[min-height:56px] border-0 rounded-[999px] bg-sun text-sun-ink text-[18px] font-extrabold">Platz auf der Startliste sichern</button>
    <p class="m-0 text-[14px] [line-height:1.5] text-muted">Kostenlos und unverbindlich. Du bekommst eine E-Mail, mit der du deine Adresse bestätigst. Mehr in der <a href="{{ route('oasis.datenschutz') }}" wire:navigate class="text-ink underline">Datenschutzerklärung</a>.</p>
</form>
