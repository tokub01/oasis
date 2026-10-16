{{-- Organisation enquiry, component App\Livewire\Oasis\OrganisationEnquiry (embedded in oasis.gemeinsam). --}}
<form wire:submit="submit" aria-label="Anfrage für Organisationen" class="flex flex-col gap-[18px] [padding:32px] bg-paper border border-dune-line rounded-[28px]">
    @include('oasis.forms._feedback')
    <div class="flex flex-col gap-[6px]">
        <label for="orga-name" class="text-[15px] font-bold">Name der Organisation</label>
        <input id="orga-name" type="text" placeholder="z. B. Fachschaft Biologie" class="o-input min-w-0" wire:model="form.organisation" aria-invalid="{{ $errors->has('form.organisation') ? 'true' : 'false' }}" aria-describedby="orga-name-error" required>
        <p id="orga-name-error" class="o-error">@error('form.organisation'){{ $message }}@enderror</p>
    </div>
    <div class="flex flex-col gap-[6px]">
        <label for="orga-anzahl" class="text-[15px] font-bold">Wie viele Leute ungefähr?</label>
        <input id="orga-anzahl" type="number" min="2" placeholder="z. B. 40" class="o-input min-w-0" wire:model="form.people" aria-invalid="{{ $errors->has('form.people') ? 'true' : 'false' }}" aria-describedby="orga-anzahl-error" required>
        <p id="orga-anzahl-error" class="o-error">@error('form.people'){{ $message }}@enderror</p>
    </div>
    <div class="flex flex-col gap-[6px]">
        <label for="orga-mail" class="text-[15px] font-bold">E-Mail für Rückfragen</label>
        <input id="orga-mail" type="email" placeholder="name@beispiel.tld" class="o-input min-w-0" wire:model="form.email" aria-invalid="{{ $errors->has('form.email') ? 'true' : 'false' }}" aria-describedby="orga-mail-error" required>
        <p id="orga-mail-error" class="o-error">@error('form.email'){{ $message }}@enderror</p>
    </div>
    <button type="submit" wire:loading.attr="disabled" class="[min-height:52px] border-0 rounded-[999px] bg-night text-on-night text-[17px] font-extrabold">Anfrage senden</button>
    <p class="m-0 text-[14px] [line-height:1.5] text-muted">Wir nutzen deine Angaben nur, um dir zu antworten. Mehr in der <a href="{{ route('oasis.datenschutz') }}" wire:navigate class="text-ink underline">Datenschutzerklärung</a>.</p>
</form>
