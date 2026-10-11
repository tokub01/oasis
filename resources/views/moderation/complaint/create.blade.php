{{--
    Public complaint form against a moderation decision (routes moderation.complaint.create via signed link from the
    decision mail, or moderation.complaint.account for the signed-in affected member). DSA Art. 20, AGB § 12.

    Variables:
      $decision  ModerationDecision with case (no reporter data, no source contact)
      $storeUrl  string             signed POST URL – must be the form action, never rebuilt here
      $deadline  CarbonInterface    last day to complain (at least six months after the notice)
    The sender's email is taken server-side from the signed link or the account, never from a form field.
--}}
@extends('layouts.oasis', ['title' => 'Widerspruch einlegen', 'description' => 'Widerspruch gegen eine Moderationsentscheidung.', 'noindex' => true])

@section('content')
    <x-oasis.auth-card title="Widerspruch einlegen" subtitle="Eine andere Person aus unserem Team prüft die Entscheidung noch einmal.">
        <div class="flex flex-col gap-2 rounded-2xl bg-sand p-4">
            <p class="m-0 font-extrabold">{{ $decision->outcome->label() }} · {{ $decision->ground->label() }}</p>
            <p class="m-0 text-[15px]"><b>Regel:</b> {{ $decision->rule_reference }}</p>
            <p class="m-0 whitespace-pre-wrap text-[15px]"><b>Begründung:</b> {{ $decision->explanation }}</p>
        </div>
        <p class="m-0 text-[15px] text-ink-2">Du kannst bis zum {{ $deadline->timezone('Europe/Berlin')->format('d.m.Y') }} widersprechen. Der Widerspruch ist kostenlos. Unabhängig davon stehen dir außergerichtliche Streitbeilegung und der Rechtsweg offen.</p>

        <form method="POST" action="{{ $storeUrl }}" class="flex flex-col gap-[18px]" novalidate>
            @csrf
            <div class="flex flex-col gap-1.5">
                <label for="c-reason" class="text-[15px] font-bold">Warum hältst du die Entscheidung für falsch?</label>
                <textarea id="c-reason" name="reason" rows="6" maxlength="10000" required class="o-input resize-y"
                          aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}"
                          @if ($errors->has('reason')) aria-describedby="c-reason-error" @endif>{{ old('reason') }}</textarea>
                @error('reason')<p id="c-reason-error" class="o-error m-0">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="o-btn o-btn-dark">Widerspruch einlegen</button>
        </form>
    </x-oasis.auth-card>
@endsection
