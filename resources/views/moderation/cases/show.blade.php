{{--
    One moderation case (route moderation.cases.show).

    Variables (see docs/oasis-moderation.md):
      $case      ModerationCase with source (ContentReport, ChatReport or ContentCheck), subjectUser, assignee, decisions.decider,
                 complaints.reviewer, events.actor
      $staff     Collection<User>  staff members for the assignment select
      $outcomes  list<Outcome>, $grounds list<Ground>  for the decision form
    Privacy: the reporter's name and email are never shown to staff. Only the reported URL/content and the reason are.
--}}
@extends('layouts.staff', ['title' => 'Fall #'.$case->id, 'active' => 'queue'])

@php
    $card = 'flex flex-col gap-4 rounded-[24px] border border-paper-line bg-paper p-[clamp(20px,3vw,28px)]';
    $h2 = 'm-0 text-[20px] font-extrabold text-night';
    $berlin = fn ($date) => $date?->timezone('Europe/Berlin')->format('d.m.Y H:i');
    $canDecide = in_array($case->status, [\App\Enums\Moderation\CaseStatus::Open, \App\Enums\Moderation\CaseStatus::InReview], true);
@endphp

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <div class="flex flex-col gap-1">
                <a href="{{ route('moderation.index') }}" class="o-link self-start text-[15px] text-night">← Warteschlange</a>
                <h1 class="o-h2 m-0 text-night">Fall #{{ $case->id }}</h1>
            </div>
            <p class="m-0 flex flex-wrap gap-2 text-sm">
                <span class="rounded-full bg-night px-3 py-1 font-bold text-on-night">{{ $case->status->label() }}</span>
                <span class="rounded-full border border-dune-line px-3 py-1 font-bold">{{ $case->priority->label() }}</span>
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="flex min-w-0 flex-col gap-6">
                <section aria-labelledby="meldung-titel" class="{{ $card }}">
                    <h2 id="meldung-titel" class="{{ $h2 }}">Meldung</h2>
                    <dl class="m-0 grid gap-3 sm:grid-cols-[160px_1fr]">
                        <dt class="font-bold">Gemeldeter Inhalt</dt>
                        <dd class="m-0 break-all"><a href="{{ $case->subject_url }}" rel="noopener noreferrer nofollow" target="_blank" class="text-night underline underline-offset-4">{{ $case->subject_url }}</a></dd>
                        @if ($case->source instanceof \App\Models\ChatReport)
                            <dt class="font-bold">Gemeldeter Klartext</dt>
                            <dd class="m-0 whitespace-pre-wrap rounded-xl bg-sand p-3 font-mono text-sm">{{ $case->source->plaintext }}</dd>
                            <dt class="font-bold">Hinweis zur Prüfung</dt>
                            <dd class="m-0 text-sm text-muted">Vom meldenden Mitglied übermittelt. Absender, Chat und Zeit sind per Signatur geprüft; ob der Text zur verschlüsselten Nachricht passt, kann der Server nicht prüfen.</dd>
                        @endif
                        @if ($case->source instanceof \App\Models\ContentCheck)
                            @php
                                $check = $case->source;
                                $checked = $check->checkable;
                                $checkedText = match (true) {
                                    $checked instanceof \App\Models\Post => $checked->body,
                                    $checked instanceof \App\Models\Profile => trim($checked->bio.' · '.$checked->location, ' ·'),
                                    $checked instanceof \App\Models\Oasis => $checked->name."\n".$checked->description."\n".$checked->house_rules,
                                    default => null,
                                };
                            @endphp
                            <dt class="font-bold">Quelle</dt>
                            <dd class="m-0">Automatische Erkennung ({{ $check->source === 'wordlist' ? 'Wortliste – KI war nicht erreichbar' : 'Sprachmodell, Mistral, EU' }}) · {{ $check->checked_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</dd>
                            <dt class="font-bold">Geprüfter Text</dt>
                            <dd class="m-0 whitespace-pre-wrap rounded-xl bg-sand p-3 text-sm">{{ $checkedText ?? 'Inhalt existiert nicht mehr.' }}</dd>
                            <dt class="font-bold">Einschätzung</dt>
                            <dd class="m-0">
                                <ul class="m-0 flex list-none flex-wrap gap-2 p-0">
                                    @foreach (\App\Support\Moderation\ContentVerdict::CATEGORIES as $key => $label)
                                        @php($score = $check->scores[$key] ?? 0)
                                        <li @class(['rounded-full px-3 py-1 text-sm', 'bg-sun text-sun-ink font-bold' => $score >= config('oasis.moderation.report_threshold'), 'bg-paper text-ink-2' => $score < config('oasis.moderation.report_threshold')])>{{ $label }}: {{ number_format($score * 100) }} %</li>
                                    @endforeach
                                </ul>
                                @if ($check->reason)
                                    <p class="mt-2 mb-0 text-sm text-ink-2">{{ $check->reason }}</p>
                                @endif
                                @if ($checked instanceof \App\Models\Post && $checked->held_at)
                                    <p class="mt-2 mb-0 text-sm font-bold text-sun-text">Vorläufig ausgeblendet seit {{ $checked->held_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}. „Keine Maßnahme“ macht ihn wieder sichtbar.</p>
                                @endif
                            </dd>
                        @endif
                        <dt class="font-bold">Begründung der Meldung</dt>
                        <dd class="m-0 whitespace-pre-wrap">{{ $case->source?->reason ?? '–' }}</dd>
                        <dt class="font-bold">Betroffenes Konto</dt>
                        <dd class="m-0">{{ $case->subjectUser ? $case->subjectUser->display_name.' (@'.$case->subjectUser->username.')' : 'Nicht zugeordnet' }}</dd>
                        <dt class="font-bold">Eingang</dt>
                        <dd class="m-0">{{ $berlin($case->created_at) }}</dd>
                    </dl>
                    <p class="m-0 text-sm text-muted">Wer gemeldet hat, ist im Moderationsbereich bewusst nicht sichtbar.</p>
                </section>

                @can('decide', $case)
                    @if ($canDecide)
                        <section aria-labelledby="entscheidung-titel" class="{{ $card }}">
                            <h2 id="entscheidung-titel" class="{{ $h2 }}">Entscheidung</h2>
                            <form method="POST" action="{{ route('moderation.cases.decide', $case) }}" class="flex flex-col gap-5" novalidate>
                                @csrf
                                @include('moderation._decision-fields', ['prefix' => 'case'])
                                <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">Entscheidung speichern und mitteilen</button>
                            </form>
                        </section>
                    @endif
                @endcan

                @if ($case->decisions->isNotEmpty())
                    <section aria-labelledby="entscheidungen-titel" class="{{ $card }}">
                        <h2 id="entscheidungen-titel" class="{{ $h2 }}">Entscheidungen</h2>
                        @foreach ($case->decisions->sortByDesc('decided_at') as $decision)
                            <article class="flex flex-col gap-2 rounded-2xl bg-sand p-4">
                                <p class="m-0 font-extrabold">
                                    {{ $decision->outcome->label() }} · {{ $decision->ground->label() }}
                                    @if ($decision->is_complaint_review)
                                        <span class="ml-1 rounded-full bg-dune px-2 py-0.5 text-xs">nach Widerspruch</span>
                                    @endif
                                </p>
                                <p class="m-0 text-sm text-ink-2">{{ $decision->decider?->display_name ?? 'Gelöschtes Konto' }} · {{ $berlin($decision->decided_at) }} · Mitgeteilt: {{ $berlin($decision->notified_at) ?? 'noch nicht' }}</p>
                                <p class="m-0"><b>Regel:</b> {{ $decision->rule_reference }}</p>
                                <p class="m-0 whitespace-pre-wrap"><b>Tatsachen:</b> {{ $decision->facts }}</p>
                                <p class="m-0 whitespace-pre-wrap"><b>Begründung:</b> {{ $decision->explanation }}</p>
                            </article>
                        @endforeach
                    </section>
                @endif

                @if ($case->complaints->isNotEmpty())
                    <section aria-labelledby="widersprueche-titel" class="{{ $card }}">
                        <h2 id="widersprueche-titel" class="{{ $h2 }}">Widersprüche</h2>
                        <ul class="m-0 flex list-none flex-col gap-2 p-0">
                            @foreach ($case->complaints as $complaint)
                                <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-sand p-4">
                                    <span>{{ $complaint->status->label() }} · {{ $berlin($complaint->submitted_at) }}{{ $complaint->reviewer ? ' · geprüft von '.$complaint->reviewer->display_name : '' }}</span>
                                    <a href="{{ route('moderation.complaints.show', $complaint) }}" class="o-link text-night">Ansehen</a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <aside class="flex flex-col gap-6">
                @can('assign', $case)
                    <section aria-labelledby="zustaendig-titel" class="{{ $card }}">
                        <h2 id="zustaendig-titel" class="{{ $h2 }}">Zuständig</h2>
                        <form method="POST" action="{{ route('moderation.cases.assign', $case) }}" class="flex flex-col gap-3">
                            @csrf
                            <label for="assign" class="sr-only">Zuständige Person</label>
                            <select id="assign" name="assigned_to" class="o-input min-h-11 py-2">
                                @foreach ($staff as $member)
                                    <option value="{{ $member->id }}" @selected($case->assigned_to === $member->id)>{{ $member->display_name }}{{ $member->id === auth()->id() ? ' (ich)' : '' }}</option>
                                @endforeach
                            </select>
                            @error('assigned_to')<p class="o-error m-0">{{ $message }}</p>@enderror
                            <button type="submit" class="o-btn o-btn-sm o-btn-outline">Zuweisen</button>
                        </form>
                    </section>
                @endcan

                <section aria-labelledby="verlauf-titel" class="{{ $card }}">
                    <h2 id="verlauf-titel" class="{{ $h2 }}">Verlauf</h2>
                    <ol class="m-0 flex list-none flex-col gap-3 border-l-2 border-dune-line p-0 pl-4">
                        @foreach ($case->events->sortBy('created_at') as $event)
                            <li class="flex flex-col">
                                <span class="font-bold">{{ $event->type->label() }}</span>
                                <span class="text-sm text-ink-2">{{ $event->actor?->display_name ?? 'System' }} · {{ $berlin($event->created_at) }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="m-0 text-sm text-muted">Der Verlauf lässt sich nicht ändern oder löschen.</p>
                </section>
            </aside>
        </div>
    </div>
@endsection
