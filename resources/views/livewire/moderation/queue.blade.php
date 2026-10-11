{{--
    Livewire view of App\Livewire\Moderation\Queue.

    Variables:
      $cases       LengthAwarePaginator<ModerationCase>  with assignee, source (ContentReport today), latestDecision
      $statuses    list<CaseStatus>
      $priorities  list<Priority>
    Filters are a normal GET form as well, so the queue works without JavaScript; Livewire only makes them live.
    Never render reporter name or email here.
--}}
<div class="flex flex-col gap-6">
    <form method="GET" action="{{ route('moderation.index') }}" class="flex flex-wrap items-end gap-4 rounded-[20px] border border-paper-line bg-paper p-5" aria-label="Fälle filtern">
        <div class="flex flex-col gap-1.5">
            <label for="q-status" class="text-[15px] font-bold">Status</label>
            <select id="q-status" name="status" wire:model.live="status" class="o-input min-h-11 py-2">
                <option value="active">Offen und in Arbeit</option>
                <option value="all">Alle</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="q-priority" class="text-[15px] font-bold">Priorität</label>
            <select id="q-priority" name="prioritaet" wire:model.live="priority" class="o-input min-h-11 py-2">
                <option value="all">Alle</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex min-w-[220px] flex-1 flex-col gap-1.5">
            <label for="q-search" class="text-[15px] font-bold">Gemeldeter Link enthält</label>
            <input id="q-search" type="search" name="suche" wire:model.live.debounce.400ms="search" class="o-input min-h-11 py-2">
        </div>
        <label class="flex min-h-11 items-center gap-2.5 text-[15px] font-bold">
            <input type="checkbox" name="meine" value="1" wire:model.live="mine" class="size-5 accent-night">
            Nur meine Fälle
        </label>
        <noscript><button type="submit" class="o-btn o-btn-sm o-btn-dark">Filtern</button></noscript>
    </form>

    <div class="overflow-x-auto rounded-[20px] border border-paper-line bg-paper">
        <table class="w-full border-collapse text-left text-[15px]">
            <caption class="sr-only">Moderationsfälle, dringende zuerst, dann die ältesten</caption>
            <thead class="bg-dune text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">Fall</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">Gemeldet</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">Status</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">Zuständig</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">Eingang</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cases as $case)
                    <tr wire:key="case-{{ $case->id }}" class="border-t border-paper-line align-top">
                        <td class="px-4 py-3">
                            <a href="{{ route('moderation.cases.show', $case) }}" class="font-bold text-night underline underline-offset-4">#{{ $case->id }}</a>
                            @if ($case->priority === \App\Enums\Moderation\Priority::Urgent)
                                <span class="ml-2 rounded-full bg-sun px-2 py-0.5 text-xs font-extrabold text-sun-ink">{{ $case->priority->label() }}</span>
                            @endif
                        </td>
                        <td class="max-w-[420px] px-4 py-3">
                            <span class="block truncate font-semibold" title="{{ $case->subject_url }}">{{ $case->subject_url }}</span>
                            @if ($case->source?->reason)
                                <span class="block text-sm text-ink-2">{{ \Illuminate\Support\Str::limit($case->source->reason, 120) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $case->status->label() }}</td>
                        <td class="px-4 py-3">{{ $case->assignee?->display_name ?? '–' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $case->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-ink-2">Keine Fälle für diese Auswahl. Gute Arbeit.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $cases->links() }}
</div>
