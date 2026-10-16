{{-- DSA Art. 16 notice form, component App\Livewire\Oasis\ContentNotice (embedded in oasis.legal.melden). --}}
<form wire:submit="submit" aria-label="Rechtswidrige Inhalte melden" class="flex flex-col gap-[18px] [max-width:44em]">
    @include('oasis.forms._feedback')
    <p class="m-0 text-[17px] [line-height:1.6] text-ink-2">Hier kann jede Person Inhalte melden, die sie für rechtswidrig hält, auch ohne Konto.</p>
    <div class="flex flex-col gap-[6px]">
        <label for="r-url" class="text-[15px] font-bold">Link zum Inhalt</label>
        <input id="r-url" type="url" placeholder="https://get-oasis.com/…" class="o-input min-w-0" wire:model="form.url" aria-invalid="{{ $errors->has('form.url') ? 'true' : 'false' }}" aria-describedby="r-url-error" required>
        <p id="r-url-error" class="o-error">@error('form.url'){{ $message }}@enderror</p>
    </div>
    <div class="flex flex-col gap-[6px]">
        <label for="r-grund" class="text-[15px] font-bold">Warum ist der Inhalt rechtswidrig?</label>
        <textarea id="r-grund" rows="5" class="o-input min-w-0 resize-y" wire:model="form.reason" aria-invalid="{{ $errors->has('form.reason') ? 'true' : 'false' }}" aria-describedby="r-grund-error"></textarea>
        <p id="r-grund-error" class="o-error">@error('form.reason'){{ $message }}@enderror</p>
    </div>
    <div class="o-auto-grid [--min:220px] [gap:18px_20px]">
        <div class="flex flex-col gap-[6px]">
            <label for="r-name" class="text-[15px] font-bold">Dein Name</label>
            <input id="r-name" type="text" autocomplete="name" class="o-input min-w-0" wire:model="form.name" aria-invalid="{{ $errors->has('form.name') ? 'true' : 'false' }}" aria-describedby="r-name-error" required>
            <p id="r-name-error" class="o-error">@error('form.name'){{ $message }}@enderror</p>
        </div>
        <div class="flex flex-col gap-[6px]">
            <label for="r-mail" class="text-[15px] font-bold">Deine E-Mail-Adresse</label>
            <input id="r-mail" type="email" autocomplete="email" class="o-input min-w-0" wire:model="form.email" aria-invalid="{{ $errors->has('form.email') ? 'true' : 'false' }}" aria-describedby="r-mail-error" required>
            <p id="r-mail-error" class="o-error">@error('form.email'){{ $message }}@enderror</p>
        </div>
    </div>
    <label class="flex gap-[12px] items-start text-[15px] [line-height:1.5]">
        <input type="checkbox" wire:model="form.truthful" required class="flex-none [width:20px] [height:20px] [margin:2px_0_0] accent-night">
        <span>Ich bestätige, dass meine Angaben nach bestem Wissen richtig und vollständig sind.</span>
    </label>
    <button type="submit" wire:loading.attr="disabled" class="[align-self:flex-start] [min-height:52px] [padding:0_28px] border-0 rounded-[999px] bg-night text-on-night text-[17px] font-extrabold">Meldung absenden</button>
</form>
