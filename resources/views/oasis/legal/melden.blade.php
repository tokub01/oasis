{{--
    Public DSA Art. 16 content-notice form from Rechtliches.dc.html at oasis.melden.
    Uses web-session errors/old input and _feedback; accepted notices are handled by ContentReportController.
    HTTP(S) link, explanation, contact and good-faith declaration are required. No JavaScript required.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Inhalte melden', 'legalKey' => 'melden'])
@section('legal-content')

    <form method="POST" action="{{ route('oasis.melden.store') }}" aria-label="Rechtswidrige Inhalte melden" class="flex flex-col gap-[18px] [max-width:44em]">
        @csrf
        @include('oasis.forms._feedback')
        <p class="m-0 text-[17px] [line-height:1.6] text-ink-2">Hier kann jede Person Inhalte melden, die sie für rechtswidrig hält, auch ohne Konto.</p>
        <div class="flex flex-col gap-[6px]">
            <label for="r-url" class="text-[15px] font-bold">Link zum Inhalt</label>
            <input id="r-url" type="url" placeholder="https://get-oasis.com/…" class="o-input min-w-0" name="url" aria-invalid="{{ $errors->has('url') ? 'true' : 'false' }}" aria-describedby="r-url-error" required value="{{ old('url') }}">
            <p id="r-url-error" class="o-error">@error('url'){{ $message }}@enderror</p>
        </div>
        <div class="flex flex-col gap-[6px]">
            <label for="r-grund" class="text-[15px] font-bold">Warum ist der Inhalt rechtswidrig?</label>
            <textarea id="r-grund" rows="5" class="o-input min-w-0 resize-y" name="reason" aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}" aria-describedby="r-grund-error">{{ old('reason') }}</textarea>
            <p id="r-grund-error" class="o-error">@error('reason'){{ $message }}@enderror</p>
        </div>
        <div class="o-auto-grid [--min:220px] [gap:18px_20px]">
            <div class="flex flex-col gap-[6px]">
                <label for="r-name" class="text-[15px] font-bold">Dein Name</label>
                <input id="r-name" type="text" autocomplete="name" class="o-input min-w-0" name="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="r-name-error" required value="{{ old('name') }}">
                <p id="r-name-error" class="o-error">@error('name'){{ $message }}@enderror</p>
            </div>
            <div class="flex flex-col gap-[6px]">
                <label for="r-mail" class="text-[15px] font-bold">Deine E-Mail-Adresse</label>
                <input id="r-mail" type="email" autocomplete="email" class="o-input min-w-0" name="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="r-mail-error" required value="{{ old('email') }}">
                <p id="r-mail-error" class="o-error">@error('email'){{ $message }}@enderror</p>
            </div>
        </div>
        <label class="flex gap-[12px] items-start text-[15px] [line-height:1.5]">
            <input type="checkbox" name="truthful" value="1" required @checked(old('truthful')) class="flex-none [width:20px] [height:20px] [margin:2px_0_0] accent-night">
            <span>Ich bestätige, dass meine Angaben nach bestem Wissen richtig und vollständig sind.</span>
        </label>
        <button type="submit" class="[align-self:flex-start] [min-height:52px] [padding:0_28px] border-0 rounded-[999px] bg-night text-on-night text-[17px] font-extrabold">Meldung absenden</button>
    </form>

@endsection
