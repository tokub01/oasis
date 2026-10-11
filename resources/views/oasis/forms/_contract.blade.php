{{--
    Cancellation/withdrawal forms from Kuendigen.dc.html, included by oasis.kuendigen/oasis.widerrufen.
    $mode: string, kuendigen or widerrufen; $errors and old input are supplied by the web session.
    The cancellation JS module toggles reason/date requirements; without JS both fields stay available.
    § 312k BGB requires public cancellation without retention offers; withdrawal follows §§ 355 ff. BGB.
--}}

<div class="o-container flex flex-col gap-[32px]">

    <div class="flex flex-col gap-[14px] [max-width:760px]">
        <p class="o-eyebrow">Verträge</p>
        <h1 class="m-0 o-display font-medium [font-size:clamp(36px,_4.4vw,_60px)] [line-height:1.08] [letter-spacing:-0.015em] text-night">Kündigen oder widerrufen</h1>
        <p class="m-0 text-[18px] [line-height:1.6] text-ink-2">Hier beendest du deine Mitgliedschaft, jederzeit und auch ohne Anmeldung. Du bekommst sofort eine Bestätigung per E-Mail.</p>
    </div>

    <nav aria-label="Was möchtest du tun?" class="flex flex-wrap gap-3">
        @foreach (['kuendigen' => 'Verträge hier kündigen', 'widerrufen' => 'Vertrag widerrufen'] as $key => $label)
            <a href="{{ route('oasis.'.$key) }}" @if($mode === $key) aria-current="page" @endif
            class="o-btn {{ $mode === $key ? 'o-btn-dark' : 'o-btn-outline' }}">{{ $label }}</a>
    @endforeach
</nav>

<div class="flex flex-wrap gap-[32px] items-start">

    @if($mode === 'kuendigen')
        <form data-oasis="cancellation" method="POST" action="{{ route('oasis.kuendigen.store') }}" aria-labelledby="k-titel" class="[flex:999_1_520px] min-w-0 flex flex-col gap-[22px] [padding:clamp(24px,_3vw,_40px)] bg-paper border border-paper-line rounded-[32px]">
            @csrf
            <input type="hidden" name="contract" value="Oasis-Mitgliedschaft">
            @include('oasis.forms._feedback')
            <h2 id="k-titel" class="m-0 text-[24px] [line-height:1.3] font-extrabold text-night">Mitgliedschaft kündigen</h2>
            <fieldset class="m-0 p-0 border-0 flex flex-col gap-[10px]">
                <legend class="p-0 [margin-bottom:10px] text-[16px] font-extrabold">Art der Kündigung</legend>
                <label class="flex items-center gap-[12px] [min-height:52px] [padding:0_16px] border border-dune-line rounded-[14px] bg-field text-[17px] cursor-pointer">
                    <input type="radio" name="type" value="ordentlich" @checked(old('type', 'ordentlich') === 'ordentlich') class="[width:20px] [height:20px] m-0 accent-night">
                    <span>
                        <b>Ordentlich</b> zum nächstmöglichen Zeitpunkt</span>
                </label>
                <label class="flex items-center gap-[12px] [min-height:52px] [padding:0_16px] border border-dune-line rounded-[14px] bg-field text-[17px] cursor-pointer">
                    <input type="radio" name="type" value="ausserordentlich" @checked(old('type') === 'ausserordentlich') class="[width:20px] [height:20px] m-0 accent-night">
                    <span>
                        <b>Außerordentlich</b> aus wichtigem Grund</span>
                </label>
            </fieldset>
            <div data-reason-field>
                <div class="flex flex-col gap-[6px]">
                    <label for="k-grund" class="text-[15px] font-bold">Grund für die außerordentliche Kündigung</label>
                    <textarea id="k-grund" rows="4" class="o-input min-w-0 resize-y" name="reason" @required(old('type') === 'ausserordentlich') aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}" @if ($errors->has('reason')) aria-describedby="k-grund-error" @endif>{{ old('reason') }}</textarea>
                    <p id="k-grund-error" class="o-error">@error('reason'){{ $message }}@enderror</p>
                </div>
            </div>
            <fieldset class="m-0 p-0 border-0 flex flex-col gap-[10px]">
                <legend class="p-0 [margin-bottom:10px] text-[16px] font-extrabold">Wann soll die Kündigung wirken?</legend>
                <label class="flex items-center gap-[12px] [min-height:52px] [padding:0_16px] border border-dune-line rounded-[14px] bg-field text-[17px] cursor-pointer">
                    <input type="radio" name="timing" value="naechst" @checked(old('timing', 'naechst') === 'naechst') class="[width:20px] [height:20px] m-0 accent-night">Zum nächstmöglichen Zeitpunkt</label>
                <div class="flex flex-wrap items-center [gap:10px_14px] [min-height:52px] [padding:6px_16px] border border-dune-line rounded-[14px] bg-field">
                    <label class="flex items-center gap-[12px] text-[17px] cursor-pointer">
                        <input type="radio" name="timing" value="datum" @checked(old('timing') === 'datum') class="[width:20px] [height:20px] m-0 accent-night">Zu einem bestimmten Datum:</label>
                    <label for="k-datum" class="[position:absolute] [width:1px] [height:1px] [overflow:hidden] [clip:rect(0_0_0_0)]">Gewünschtes Kündigungsdatum</label>
                    <input id="k-datum" type="date" class="o-input min-w-0" name="effective_date" @required(old('timing') === 'datum') aria-invalid="{{ $errors->has('effective_date') ? 'true' : 'false' }}" @if ($errors->has('effective_date')) aria-describedby="k-datum-error" @endif value="{{ old('effective_date') }}">
                    <p id="k-datum-error" class="o-error">@error('effective_date'){{ $message }}@enderror</p>
                </div>
            </fieldset>
            <div class="o-auto-grid [--min:240px] [gap:18px_20px]">
                <div class="flex flex-col gap-[6px]">
                    <label for="k-name" class="text-[15px] font-bold">Vor- und Nachname</label>
                    <input id="k-name" type="text" autocomplete="name" class="o-input min-w-0" name="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @if ($errors->has('name')) aria-describedby="k-name-error" @endif required value="{{ old('name') }}">
                    <p id="k-name-error" class="o-error">@error('name'){{ $message }}@enderror</p>
                </div>
                <div class="flex flex-col gap-[6px]">
                    <label for="k-mail" class="text-[15px] font-bold">E-Mail-Adresse deines Kontos</label>
                    <input id="k-mail" type="email" autocomplete="email" aria-describedby="k-mail-hinweis{{ $errors->has('email') ? ' k-mail-error' : '' }}" class="o-input min-w-0" name="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" required value="{{ old('email') }}">
                    <p id="k-mail-error" class="o-error">@error('email'){{ $message }}@enderror</p>
                    <span id="k-mail-hinweis" class="text-[14px] text-muted">Hierhin schicken wir sofort die Bestätigung.</span>
                </div>
            </div>
            <div class="o-auto-grid [--min:240px] [gap:18px_20px]">
                <div class="flex flex-col gap-[6px]">
                    <label for="k-user" class="text-[15px] font-bold">Benutzername <span class="font-medium text-muted">(optional)</span>
                    </label>
                    <input id="k-user" type="text" placeholder="@" class="o-input min-w-0" name="username" aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}" @if ($errors->has('username')) aria-describedby="k-user-error" @endif value="{{ old('username') }}">
                    <p id="k-user-error" class="o-error">@error('username'){{ $message }}@enderror</p>
                </div>
                <div class="flex flex-col gap-[6px]">
                    <span class="text-[15px] font-bold">Vertrag</span>
                    <span class="flex items-center [min-height:52px] [padding:0_16px] rounded-[12px] bg-dune text-[17px] font-bold">Oasis-Mitgliedschaft</span>
                </div>
            </div>
            <button type="submit" class="[min-height:56px] border-0 rounded-[999px] bg-night text-on-night text-[18px] font-extrabold">Jetzt kündigen</button>
            <p class="m-0 text-[15px] [line-height:1.55] text-ink-2">Kein Umweg, keine Halteangebote. Die Bestätigung mit Datum und Uhrzeit bekommst du sofort per E-Mail.</p>
        </form>
    @endif

    @if($mode === 'widerrufen')
        <form method="POST" action="{{ route('oasis.widerrufen.store') }}" aria-labelledby="w-titel" class="[flex:999_1_520px] min-w-0 flex flex-col gap-[22px] [padding:clamp(24px,_3vw,_40px)] bg-paper border border-paper-line rounded-[32px]">
            @csrf
            <input type="hidden" name="contract" value="Oasis-Mitgliedschaft">
            @include('oasis.forms._feedback')
            <h2 id="w-titel" class="m-0 text-[24px] [line-height:1.3] font-extrabold text-night">Vertrag widerrufen</h2>
            <p class="m-0 text-[17px] [line-height:1.6] text-ink-2">Innerhalb von 14 Tagen nach Abschluss kannst du deine Mitgliedschaft ohne Angabe von Gründen widerrufen. Wie und was wir erstatten, steht in der <a href="{{ route('oasis.widerrufsbelehrung') }}" class="text-ink underline">Widerrufsbelehrung</a>.</p>
            <div class="o-auto-grid [--min:240px] [gap:18px_20px]">
                <div class="flex flex-col gap-[6px]">
                    <label for="w-name" class="text-[15px] font-bold">Vor- und Nachname</label>
                    <input id="w-name" type="text" autocomplete="name" class="o-input min-w-0" name="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @if ($errors->has('name')) aria-describedby="w-name-error" @endif required value="{{ old('name') }}">
                    <p id="w-name-error" class="o-error">@error('name'){{ $message }}@enderror</p>
                </div>
                <div class="flex flex-col gap-[6px]">
                    <label for="w-mail" class="text-[15px] font-bold">E-Mail-Adresse deines Kontos</label>
                    <input id="w-mail" type="email" autocomplete="email" class="o-input min-w-0" name="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if ($errors->has('email')) aria-describedby="w-mail-error" @endif required value="{{ old('email') }}">
                    <p id="w-mail-error" class="o-error">@error('email'){{ $message }}@enderror</p>
                </div>
            </div>
            <div class="o-auto-grid [--min:240px] [gap:18px_20px]">
                <div class="flex flex-col gap-[6px]">
                    <label for="w-datum" class="text-[15px] font-bold">Abgeschlossen am <span class="font-medium text-muted">(optional)</span>
                    </label>
                    <input id="w-datum" type="date" class="o-input min-w-0" name="contracted_on" aria-invalid="{{ $errors->has('contracted_on') ? 'true' : 'false' }}" @if ($errors->has('contracted_on')) aria-describedby="w-datum-error" @endif value="{{ old('contracted_on') }}">
                    <p id="w-datum-error" class="o-error">@error('contracted_on'){{ $message }}@enderror</p>
                </div>
                <div class="flex flex-col gap-[6px]">
                    <span class="text-[15px] font-bold">Vertrag</span>
                    <span class="flex items-center [min-height:52px] [padding:0_16px] rounded-[12px] bg-dune text-[17px] font-bold">Oasis-Mitgliedschaft</span>
                </div>
            </div>
            <button type="submit" class="[min-height:56px] border-0 rounded-[999px] bg-night text-on-night text-[18px] font-extrabold">Widerruf bestätigen</button>
            <p class="m-0 text-[15px] [line-height:1.55] text-ink-2">Die Eingangsbestätigung bekommst du sofort per E-Mail.</p>
        </form>
    @endif

    <aside aria-labelledby="gut-titel" class="[flex:1_1_320px] flex flex-col gap-[16px] [padding:clamp(24px,_3vw,_36px)] bg-dune rounded-[32px]">
        <h2 id="gut-titel" class="m-0 text-[20px] font-extrabold text-night">Gut zu wissen</h2>
        <ul class="m-0 p-0 list-none flex flex-col gap-[14px] text-[16px] [line-height:1.55]">
            <li class="flex gap-[12px] items-start">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--color-spring-text)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-none [margin-top:3px]">
                    <path d="M20 6L9 17l-5-5">
                    </path>
                </svg>
                <span>Monatsbeiträge enden zum Ende des laufenden Monats.</span>
            </li>
            <li class="flex gap-[12px] items-start">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--color-spring-text)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-none [margin-top:3px]">
                    <path d="M20 6L9 17l-5-5">
                    </path>
                </svg>
                <span>Jahresbeiträge enden zum Ende des bezahlten Jahres.</span>
            </li>
            <li class="flex gap-[12px] items-start">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--color-spring-text)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-none [margin-top:3px]">
                    <path d="M20 6L9 17l-5-5">
                    </path>
                </svg>
                <span>Deine Tropfen kannst du noch {{ config('oasis.retention_days') }} Tage danach herunterladen.</span>
            </li>
            <li class="flex gap-[12px] items-start">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--color-spring-text)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-none [margin-top:3px]">
                    <path d="M20 6L9 17l-5-5">
                    </path>
                </svg>
                <span>Du kannst jederzeit wiederkommen.</span>
            </li>
        </ul>
    </aside>

</div>
</div>
