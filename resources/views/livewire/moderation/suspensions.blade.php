{{--
    Locked accounts, component App\Livewire\Moderation\Suspensions (route moderation.suspensions).
    Variables: $active, $ended (Collection<AccountSuspension>), $limit (int).
--}}
<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <h1 class="o-h2 m-0 text-night">Sperren</h1>
        <p class="m-0 text-ink-2">Konten, die gerade gesperrt sind. Eine vorläufige Sperre beginnt automatisch, wenn die Prüfung einen Tropfen zurückhält. Entscheide den verknüpften Fall oder gib das Konto hier frei. Nach {{ $limit }} bestätigten Verstößen wird ein Konto dauerhaft gesperrt; das hebt nur ein Admin auf.</p>
    </div>

    <p role="status" @class(['m-0 rounded-2xl bg-dune px-[18px] py-3.5', 'sr-only' => ! $status])>{{ $status }}</p>

    @forelse ($active as $suspension)
        <article wire:key="lock-{{ $suspension->id }}" class="flex flex-col gap-3 rounded-[20px] border border-paper-line bg-paper p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-col gap-0.5">
                    <h2 class="m-0 text-lg font-extrabold text-night">{{ $suspension->user->display_name }} <span class="font-normal text-muted">{{ '@'.$suspension->user->username }}</span></h2>
                    <p class="m-0 text-sm text-ink-2">{{ $suspension->label() }} · seit {{ $suspension->started_at->timezone('Europe/Berlin')->format('d.m.Y, H:i') }} Uhr · Verstöße {{ $suspension->user->activeStrikeCount() }} von {{ $limit }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($suspension->moderationCase)
                        <a href="{{ route('moderation.cases.show', $suspension->moderationCase) }}" wire:navigate class="o-btn o-btn-sm o-btn-outline">Fall entscheiden</a>
                    @endif
                    @if ($suspension->type !== \App\Models\AccountSuspension::BAN || auth()->user()->can('administrate'))
                        <button type="button" wire:click="release({{ $suspension->id }})" wire:confirm="Konto von {{ $suspension->user->display_name }} freigeben?" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-dark">Freigeben</button>
                    @endif
                </div>
            </div>
            <p class="m-0"><strong>Vorwurf:</strong> {{ $suspension->accusation }}</p>
            @if ($suspension->excerpt)
                <blockquote class="m-0 border-l-4 border-sun pl-3 text-ink-2 whitespace-pre-line break-words">„{{ $suspension->excerpt }}“</blockquote>
            @endif
            <div class="rounded-2xl bg-dune p-4">
                <p class="m-0 text-sm font-bold text-night">Stellungnahme des Mitglieds</p>
                @if ($suspension->statement)
                    <p class="m-0 mt-1 whitespace-pre-line break-words">{{ $suspension->statement }}</p>
                    <p class="m-0 mt-1 text-sm text-muted">{{ $suspension->statement_at?->timezone('Europe/Berlin')->format('d.m.Y, H:i') }} Uhr</p>
                @else
                    <p class="m-0 mt-1 text-sm text-muted">Noch keine.</p>
                @endif
            </div>
        </article>
    @empty
        <p class="m-0 rounded-[20px] bg-paper p-5 text-ink-2">Gerade ist kein Konto gesperrt.</p>
    @endforelse

    @if ($ended->isNotEmpty())
        <section aria-labelledby="beendet-titel" class="flex flex-col gap-2">
            <h2 id="beendet-titel" class="m-0 text-lg font-extrabold text-night">Zuletzt beendet</h2>
            <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
                @foreach ($ended as $suspension)
                    <li wire:key="ended-{{ $suspension->id }}" class="rounded-xl bg-paper px-4 py-2 text-sm">
                        <strong>{{ $suspension->user->display_name }}</strong> · {{ $suspension->label() }} ·
                        {{ ['released' => 'freigegeben, kein Verstoß', 'confirmed' => 'Verstoß bestätigt, freigegeben', 'overturned' => 'nach Beschwerde aufgehoben'][$suspension->result] ?? 'beendet' }}
                        · {{ $suspension->ended_at->timezone('Europe/Berlin')->format('d.m.Y, H:i') }} Uhr
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
