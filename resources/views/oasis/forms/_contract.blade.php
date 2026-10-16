{{--
    Cancellation/withdrawal forms from Kuendigen.dc.html, included by oasis.kuendigen/oasis.widerrufen.
    $mode: string, kuendigen or widerrufen. The forms are the Livewire components oasis.cancellation-declaration
    and oasis.withdrawal-declaration.
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
            <a href="{{ route('oasis.'.$key) }}" wire:navigate @if($mode === $key) aria-current="page" @endif
            class="o-btn {{ $mode === $key ? 'o-btn-dark' : 'o-btn-outline' }}">{{ $label }}</a>
    @endforeach
</nav>

<div class="flex flex-wrap gap-[32px] items-start">

    @if($mode === 'kuendigen')
        <livewire:oasis.cancellation-declaration />
    @endif

    @if($mode === 'widerrufen')
        <livewire:oasis.withdrawal-declaration />
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
