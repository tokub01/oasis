{{--
    Shared German validation summary and honeypot for all five public Oasis forms.
    Included inside the Livewire form components; $errors is the component's error bag (fields as form.<name>).
    The off-screen website field must remain empty and outside the keyboard tab sequence.
--}}
@if ($errors->any())
    <div role="alert" class="o-error rounded-[14px] border p-4">
        <p class="font-bold">Bitte überprüfe deine Angaben.</p>
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="o-hp" aria-hidden="true">
    <label for="website">Bitte dieses Feld leer lassen</label>
    <input id="website" wire:model="form.website" type="text" tabindex="-1" autocomplete="off">
</div>
