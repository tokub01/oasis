{{--
    Complaint form against a moderation decision, component App\Livewire\Moderation\ComplaintForm
    (routes moderation.complaint.create via signed link, moderation.complaint.account for the affected member).

    Variables:
      $shown     ModerationDecision  as this recipient may read it (no facts or reasons for a reporter)
      $deadline  CarbonInterface     last day to complain (at least six months after the notice)
    The sender's email is resolved server-side, never taken from a form field.
--}}
<div>
@if ($submitted)
    <x-oasis.auth-card title="Danke, dein Widerspruch ist da." subtitle="Wir haben dir eine Eingangsbestätigung per E-Mail geschickt.">
        <p role="status" class="m-0 text-[17px] leading-[1.6] text-ink-2">Eine andere Person aus unserem Team als die, die zuerst entschieden hat, prüft die Entscheidung. Das Ergebnis und die Begründung bekommst du per E-Mail.</p>
        <a href="{{ route('oasis.home') }}" wire:navigate class="o-btn o-btn-dark">Zur Startseite</a>
    </x-oasis.auth-card>
@else
    <x-oasis.auth-card title="Widerspruch einlegen" subtitle="Eine andere Person aus unserem Team prüft die Entscheidung noch einmal.">
        <div class="flex flex-col gap-2 rounded-2xl bg-sand p-4">
            <p class="m-0 font-extrabold">{{ $shown->outcome->label() }} · {{ $shown->ground->label() }}</p>
            @if ($shown->rule_reference !== '')
                <p class="m-0 text-[15px]"><b>Regel:</b> {{ $shown->rule_reference }}</p>
            @endif
            <p class="m-0 whitespace-pre-wrap text-[15px]"><b>Begründung:</b> {{ $shown->explanation }}</p>
        </div>
        <p class="m-0 text-[15px] text-ink-2">Du kannst bis zum {{ $deadline->timezone('Europe/Berlin')->format('d.m.Y') }} widersprechen. Der Widerspruch ist kostenlos. Unabhängig davon stehen dir außergerichtliche Streitbeilegung und der Rechtsweg offen.</p>

        <form wire:submit="submit" class="flex flex-col gap-[18px]" novalidate>
            <div class="flex flex-col gap-1.5">
                <label for="c-reason" class="text-[15px] font-bold">Warum hältst du die Entscheidung für falsch?</label>
                <textarea id="c-reason" wire:model="reason" rows="6" maxlength="10000" required class="o-input resize-y"
                          aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}"
                          @error('reason') aria-describedby="c-reason-error" @enderror></textarea>
                @error('reason')<p id="c-reason-error" role="alert" class="o-error m-0">{{ $message }}</p>@enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-dark">Widerspruch einlegen</button>
        </form>
    </x-oasis.auth-card>
@endif
</div>
