{{-- Withdrawal form, component App\Livewire\Oasis\WithdrawalDeclaration (embedded in oasis.forms._contract). --}}
<form wire:submit="submit" aria-labelledby="w-titel" class="[flex:999_1_520px] min-w-0 flex flex-col gap-[22px] [padding:clamp(24px,_3vw,_40px)] bg-paper border border-paper-line rounded-[32px]">
    @include('oasis.forms._feedback')
    <h2 id="w-titel" class="m-0 text-[24px] [line-height:1.3] font-extrabold text-night">Vertrag widerrufen</h2>
    <p class="m-0 text-[17px] [line-height:1.6] text-ink-2">Innerhalb von 14 Tagen nach Abschluss kannst du deine Mitgliedschaft ohne Angabe von Gründen widerrufen. Wie und was wir erstatten, steht in der <a href="{{ route('oasis.widerrufsbelehrung') }}" wire:navigate class="text-ink underline">Widerrufsbelehrung</a>.</p>
    <div class="o-auto-grid [--min:240px] [gap:18px_20px]">
        <div class="flex flex-col gap-[6px]">
            <label for="w-name" class="text-[15px] font-bold">Vor- und Nachname</label>
            <input id="w-name" type="text" autocomplete="name" class="o-input min-w-0" wire:model="form.name" aria-invalid="{{ $errors->has('form.name') ? 'true' : 'false' }}" @if ($errors->has('form.name')) aria-describedby="w-name-error" @endif required>
            <p id="w-name-error" class="o-error">@error('form.name'){{ $message }}@enderror</p>
        </div>
        <div class="flex flex-col gap-[6px]">
            <label for="w-mail" class="text-[15px] font-bold">E-Mail-Adresse deines Kontos</label>
            <input id="w-mail" type="email" autocomplete="email" class="o-input min-w-0" wire:model="form.email" aria-invalid="{{ $errors->has('form.email') ? 'true' : 'false' }}" @if ($errors->has('form.email')) aria-describedby="w-mail-error" @endif required>
            <p id="w-mail-error" class="o-error">@error('form.email'){{ $message }}@enderror</p>
        </div>
    </div>
    <div class="o-auto-grid [--min:240px] [gap:18px_20px]">
        <div class="flex flex-col gap-[6px]">
            <label for="w-datum" class="text-[15px] font-bold">Abgeschlossen am <span class="font-medium text-muted">(optional)</span>
            </label>
            <input id="w-datum" type="date" class="o-input min-w-0" wire:model="form.contracted_on" aria-invalid="{{ $errors->has('form.contracted_on') ? 'true' : 'false' }}" @if ($errors->has('form.contracted_on')) aria-describedby="w-datum-error" @endif>
            <p id="w-datum-error" class="o-error">@error('form.contracted_on'){{ $message }}@enderror</p>
        </div>
        <div class="flex flex-col gap-[6px]">
            <span class="text-[15px] font-bold">Vertrag</span>
            <span class="flex items-center [min-height:52px] [padding:0_16px] rounded-[12px] bg-dune text-[17px] font-bold">Oasis-Mitgliedschaft</span>
        </div>
    </div>
    <button type="submit" wire:loading.attr="disabled" class="[min-height:56px] border-0 rounded-[999px] bg-night text-on-night text-[18px] font-extrabold">Widerruf bestätigen</button>
    <p class="m-0 text-[15px] [line-height:1.55] text-ink-2">Die Eingangsbestätigung bekommst du sofort per E-Mail.</p>
</form>
