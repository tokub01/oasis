{{--
    Review of an internal complaint, component App\Livewire\Moderation\ComplaintReview (route moderation.complaints.show).

    Variables:
      $complaint  ModerationComplaint with case, decision.decider, submitter, reviewer
      $outcomes   list<Outcome>, $grounds list<Ground>  for the "overturn" decision fields
    The form is shown only to staff who may decide (policy reviewComplaint: not the original decider).
--}}
@php
    $decision = $complaint->decision;
    $card = 'flex flex-col gap-4 rounded-[24px] border border-paper-line bg-paper p-[clamp(20px,3vw,28px)]';
    $h2 = 'm-0 text-[20px] font-extrabold text-night';
    $berlin = fn ($date) => $date?->timezone('Europe/Berlin')->format('d.m.Y H:i');
@endphp

<div class="flex max-w-[860px] flex-col gap-6">
    <div class="flex flex-col gap-1">
        <a href="{{ route('moderation.cases.show', $complaint->case) }}" wire:navigate class="o-link self-start text-[15px] text-night">← Fall #{{ $complaint->case->id }}</a>
        <h1 class="o-h2 m-0 text-night">Widerspruch</h1>
        <p class="m-0 text-ink-2">{{ $complaint->status->label() }} · eingegangen am {{ $berlin($complaint->submitted_at) }}</p>
    </div>

    <p role="status" @class(['m-0 rounded-2xl bg-dune px-[18px] py-3.5', 'sr-only' => ! $status])>{{ $status }}</p>

    <section aria-labelledby="ursprung-titel" class="{{ $card }}">
        <h2 id="ursprung-titel" class="{{ $h2 }}">Ursprüngliche Entscheidung</h2>
        <p class="m-0 font-extrabold">{{ $decision->outcome->label() }} · {{ $decision->ground->label() }}</p>
        <p class="m-0 text-sm text-ink-2">Entschieden von {{ $decision->decider?->display_name ?? 'Gelöschtes Konto' }} am {{ $berlin($decision->decided_at) }}</p>
        <p class="m-0"><b>Regel:</b> {{ $decision->rule_reference }}</p>
        <p class="m-0 whitespace-pre-wrap"><b>Begründung:</b> {{ $decision->explanation }}</p>
    </section>

    <section aria-labelledby="widerspruch-titel" class="{{ $card }}">
        <h2 id="widerspruch-titel" class="{{ $h2 }}">Begründung des Widerspruchs</h2>
        <p class="m-0 whitespace-pre-wrap">{{ $complaint->reason }}</p>
    </section>

    @if ($complaint->status === \App\Enums\Moderation\ComplaintStatus::Open)
        @can('reviewComplaint', $complaint)
            <section aria-labelledby="pruefung-titel" class="{{ $card }}">
                <h2 id="pruefung-titel" class="{{ $h2 }}">Prüfung</h2>
                <form wire:submit="review" class="flex flex-col gap-5" novalidate>
                    <fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
                        <legend class="mb-2 p-0 text-[16px] font-extrabold">Ergebnis</legend>
                        <label class="flex min-h-11 items-center gap-3"><input type="radio" name="result" wire:model.live="result" value="upheld" required class="size-5 accent-night"> Entscheidung bleibt bestehen</label>
                        <label class="flex min-h-11 items-center gap-3"><input type="radio" name="result" wire:model.live="result" value="overturned" class="size-5 accent-night"> Entscheidung wird geändert</label>
                        @error('result')<p role="alert" class="o-error m-0">{{ $message }}</p>@enderror
                    </fieldset>
                    @if ($result === 'overturned')
                        <div class="flex flex-col gap-5 rounded-2xl border border-dune-line p-4">
                            <p class="m-0 font-extrabold">Neue Entscheidung</p>
                            <p class="m-0 text-sm text-muted">Die Begründung für die Betroffenen ist zugleich die Begründung des Ergebnisses.</p>
                            @include('livewire.moderation.partials.decision-fields', ['prefix' => 'review'])
                        </div>
                    @else
                        <div class="flex flex-col gap-1.5">
                            <label for="r-explanation" class="text-[15px] font-bold">Begründung des Ergebnisses</label>
                            <textarea id="r-explanation" wire:model="explanation" rows="5" maxlength="10000" required class="o-input resize-y"
                                      @error('explanation') aria-describedby="r-explanation-error" aria-invalid="true" @enderror></textarea>
                            @error('explanation')<p id="r-explanation-error" class="o-error m-0">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Ergebnis speichern und mitteilen</button>
                </form>
            </section>
        @else
            <p class="m-0 rounded-2xl bg-dune px-[18px] py-3.5">Diesen Widerspruch prüft eine andere Person aus dem Team, weil du die ursprüngliche Entscheidung getroffen hast.</p>
        @endcan
    @else
        <section aria-labelledby="ergebnis-titel" class="{{ $card }}">
            <h2 id="ergebnis-titel" class="{{ $h2 }}">Ergebnis</h2>
            <p class="m-0">{{ $complaint->status->label() }} · geprüft von {{ $complaint->reviewer?->display_name ?? 'Gelöschtes Konto' }} am {{ $berlin($complaint->reviewed_at) }}</p>
            <p class="m-0 whitespace-pre-wrap">{{ $complaint->review_explanation }}</p>
        </section>
    @endif
</div>
