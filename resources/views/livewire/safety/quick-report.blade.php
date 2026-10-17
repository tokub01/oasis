{{-- Quick report for members, component App\Livewire\Safety\QuickReport (route app.report). Variables: $categories (ReportCategory[]). --}}
<div class="mx-auto flex w-full max-w-[640px] flex-col gap-6">
    @if ($sent)
        <section aria-labelledby="danke-titel" class="flex flex-col gap-4 rounded-[24px] bg-paper p-6">
            <h1 id="danke-titel" class="o-h2 m-0 text-night">Danke, dass du das meldest.</h1>
            <p class="m-0 text-[17px] text-ink-2">Du hast richtig gehandelt. Unser Moderationsteam schaut sich das an, in der Regel innerhalb von {{ config('oasis.moderation.review_hours') }} Stunden, und schreibt dir per E-Mail, was dabei herauskommt.</p>
            <p class="m-0 text-[17px] text-ink-2"><strong>Die gemeldete Person erfährt nicht, wer gemeldet hat.</strong> Wenn du sie nicht mehr sehen willst, kannst du sie auf ihrem Profil blockieren oder stumm schalten.</p>
            <x-oasis.support-contacts />
            <a href="{{ route('app.home') }}" wire:navigate class="o-btn o-btn-md o-btn-dark self-start">Zurück zur Quelle</a>
        </section>
    @else
        <x-network.page-header eyebrow="Melden" title="Was stimmt hier nicht?">Melden ist ausdrücklich erwünscht – auch wenn du dir nicht sicher bist. Lieber einmal zu viel als zu wenig. Die gemeldete Person erfährt nicht, wer gemeldet hat.</x-network.page-header>
        <form wire:submit="send" class="flex flex-col gap-5 rounded-[24px] bg-paper p-6" novalidate>
            <p class="m-0 break-all text-sm text-muted">Gemeldet wird: {{ $url }}</p>
            <fieldset class="m-0 flex flex-col gap-2 border-0 p-0" @error('category') aria-describedby="kategorie-fehler" @enderror>
                <legend class="mb-2 text-lg font-extrabold text-night">Was ist das Problem?</legend>
                @foreach ($categories as $option)
                    <label wire:key="kategorie-{{ $option->value }}" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-dune-line px-3 py-2 has-[:checked]:border-night has-[:checked]:bg-dune">
                        <input type="radio" name="category" value="{{ $option->value }}" wire:model="category" class="accent-night">
                        <span>{{ $option->label() }}</span>
                    </label>
                @endforeach
                <p id="kategorie-fehler" class="o-error m-0">@error('category'){{ $message }}@enderror</p>
            </fieldset>
            <div class="flex flex-col gap-1.5">
                <label for="kommentar" class="o-label">Möchtest du noch etwas dazu sagen? <span class="font-normal text-muted">(freiwillig)</span></label>
                <textarea id="kommentar" wire:model="comment" rows="4" maxlength="2000" class="o-input"
                          placeholder="Zum Beispiel: Das geht schon länger so, auch in anderen Tropfen."></textarea>
                @error('comment')<p class="o-error m-0">{{ $message }}</p>@enderror
            </div>
            <p class="m-0 text-sm text-muted">Mit dem Melden bestätigst du, dass du es ehrlich meinst. Deine E-Mail-Adresse nutzen wir nur, um dir das Ergebnis zu schicken.</p>
            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Melden</button>
        </form>
        <x-oasis.support-contacts />
    @endif
</div>
