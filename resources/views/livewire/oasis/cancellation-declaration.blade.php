{{-- § 312k BGB cancellation form, component App\Livewire\Oasis\CancellationDeclaration (embedded in oasis.forms._contract).
     The reason appears only for an extraordinary cancellation; the date is required only for "Zu einem bestimmten Datum". --}}
<form wire:submit="submit" aria-labelledby="k-titel" class="[flex:999_1_520px] min-w-0 flex flex-col gap-[22px] [padding:clamp(24px,_3vw,_40px)] bg-paper border border-paper-line rounded-[32px]">
    @include('oasis.forms._feedback')
    <h2 id="k-titel" class="m-0 text-[24px] [line-height:1.3] font-extrabold text-night">Mitgliedschaft kündigen</h2>
    <fieldset class="m-0 p-0 border-0 flex flex-col gap-[10px]">
        <legend class="p-0 [margin-bottom:10px] text-[16px] font-extrabold">Art der Kündigung</legend>
        <label class="flex items-center gap-[12px] [min-height:52px] [padding:0_16px] border border-dune-line rounded-[14px] bg-field text-[17px] cursor-pointer">
            <input type="radio" name="type" wire:model.live="form.type" value="ordentlich" class="[width:20px] [height:20px] m-0 flex-none accent-night">
            <span>
                <b>Ordentlich</b> zum nächstmöglichen Zeitpunkt</span>
        </label>
        <label class="flex items-center gap-[12px] [min-height:52px] [padding:0_16px] border border-dune-line rounded-[14px] bg-field text-[17px] cursor-pointer">
            <input type="radio" name="type" wire:model.live="form.type" value="ausserordentlich" class="[width:20px] [height:20px] m-0 flex-none accent-night">
            <span>
                <b>Außerordentlich</b> aus wichtigem Grund</span>
        </label>
    </fieldset>
    @if ($form->type === 'ausserordentlich')
        <div class="flex flex-col gap-[6px]">
            <label for="k-grund" class="text-[15px] font-bold">Grund für die außerordentliche Kündigung</label>
            <textarea id="k-grund" rows="4" class="o-input min-w-0 resize-y" wire:model="form.reason" required aria-invalid="{{ $errors->has('form.reason') ? 'true' : 'false' }}" @if ($errors->has('form.reason')) aria-describedby="k-grund-error" @endif></textarea>
            <p id="k-grund-error" class="o-error">@error('form.reason'){{ $message }}@enderror</p>
        </div>
    @endif
    <fieldset class="m-0 p-0 border-0 flex flex-col gap-[10px]">
        <legend class="p-0 [margin-bottom:10px] text-[16px] font-extrabold">Wann soll die Kündigung wirken?</legend>
        <label class="flex items-center gap-[12px] [min-height:52px] [padding:0_16px] border border-dune-line rounded-[14px] bg-field text-[17px] cursor-pointer">
            <input type="radio" name="timing" wire:model.live="form.timing" value="naechst" class="[width:20px] [height:20px] m-0 flex-none accent-night">Zum nächstmöglichen Zeitpunkt</label>
        <div class="flex flex-wrap items-center [gap:10px_14px] [min-height:52px] [padding:6px_16px] border border-dune-line rounded-[14px] bg-field">
            <label class="flex items-center gap-[12px] text-[17px] cursor-pointer">
                <input type="radio" name="timing" wire:model.live="form.timing" value="datum" class="[width:20px] [height:20px] m-0 flex-none accent-night">Zu einem bestimmten Datum:</label>
            <label for="k-datum" class="[position:absolute] [width:1px] [height:1px] [overflow:hidden] [clip:rect(0_0_0_0)]">Gewünschtes Kündigungsdatum</label>
            <input id="k-datum" type="date" class="o-input min-w-0" wire:model="form.effective_date" @required($form->timing === 'datum') aria-invalid="{{ $errors->has('form.effective_date') ? 'true' : 'false' }}" @if ($errors->has('form.effective_date')) aria-describedby="k-datum-error" @endif>
            <p id="k-datum-error" class="o-error">@error('form.effective_date'){{ $message }}@enderror</p>
        </div>
    </fieldset>
    <div class="o-auto-grid [--min:240px] [gap:18px_20px]">
        <div class="flex flex-col gap-[6px]">
            <label for="k-name" class="text-[15px] font-bold">Vor- und Nachname</label>
            <input id="k-name" type="text" autocomplete="name" class="o-input min-w-0" wire:model="form.name" aria-invalid="{{ $errors->has('form.name') ? 'true' : 'false' }}" @if ($errors->has('form.name')) aria-describedby="k-name-error" @endif required>
            <p id="k-name-error" class="o-error">@error('form.name'){{ $message }}@enderror</p>
        </div>
        <div class="flex flex-col gap-[6px]">
            <label for="k-mail" class="text-[15px] font-bold">E-Mail-Adresse deines Kontos</label>
            <input id="k-mail" type="email" autocomplete="email" aria-describedby="k-mail-hinweis{{ $errors->has('form.email') ? ' k-mail-error' : '' }}" class="o-input min-w-0" wire:model="form.email" aria-invalid="{{ $errors->has('form.email') ? 'true' : 'false' }}" required>
            <p id="k-mail-error" class="o-error">@error('form.email'){{ $message }}@enderror</p>
            <span id="k-mail-hinweis" class="text-[14px] text-muted">Hierhin schicken wir sofort die Bestätigung.</span>
        </div>
    </div>
    <div class="o-auto-grid [--min:240px] [gap:18px_20px]">
        <div class="flex flex-col gap-[6px]">
            <label for="k-user" class="text-[15px] font-bold">Benutzername <span class="font-medium text-muted">(optional)</span>
            </label>
            <input id="k-user" type="text" placeholder="@" class="o-input min-w-0" wire:model="form.username" aria-invalid="{{ $errors->has('form.username') ? 'true' : 'false' }}" @if ($errors->has('form.username')) aria-describedby="k-user-error" @endif>
            <p id="k-user-error" class="o-error">@error('form.username'){{ $message }}@enderror</p>
        </div>
        <div class="flex flex-col gap-[6px]">
            <span class="text-[15px] font-bold">Vertrag</span>
            <span class="flex items-center [min-height:52px] [padding:0_16px] rounded-[12px] bg-dune text-[17px] font-bold">Oasis-Mitgliedschaft</span>
        </div>
    </div>
    <button type="submit" wire:loading.attr="disabled" class="[min-height:56px] border-0 rounded-[999px] bg-night text-on-night text-[18px] font-extrabold">Jetzt kündigen</button>
    <p class="m-0 text-[15px] [line-height:1.55] text-ink-2">Kein Umweg, keine Halteangebote. Die Bestätigung mit Datum und Uhrzeit bekommst du sofort per E-Mail.</p>
</form>
