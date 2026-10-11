@php
    $columns = [
        'Oasis' => [
            ['Funktionen', route('oasis.funktionen')],
            ['Preise', route('oasis.preise')],
            ['Transparenz', route('oasis.transparenz')],
            ['Manifest', route('oasis.manifest')],
        ],
        'Mitmachen' => [
            ['30 Tage testen', route('oasis.testen')],
            ['Gemeinsam starten', route('oasis.gemeinsam')],
            ['Gründungsmitglied', route('oasis.gruendung')],
            ['Als Gast reinlesen', route('oasis.entdecken')],
            ['Anmelden', route('oasis.anmelden')],
        ],
        'Hilfe' => [
            ['Hilfe & FAQ', route('oasis.hilfe')],
            ['Community-Regeln', route('oasis.regeln')],
        ],
        'Rechtliches' => [
            ['Impressum', route('oasis.impressum')],
            ['Datenschutz', route('oasis.datenschutz')],
            ['AGB', route('oasis.agb')],
            ['Widerrufsbelehrung', route('oasis.widerrufsbelehrung')],
        ],
    ];
@endphp

<footer class="bg-night text-on-night">
    <div class="o-container flex flex-col gap-12 pt-[72px] pb-9">
        <div class="flex flex-wrap justify-between gap-x-12 gap-y-10">
            <div class="flex max-w-[360px] flex-[1_1_280px] flex-col gap-4">
                <a href="{{ route('oasis.home') }}" aria-label="Oasis – zur Startseite" class="flex text-on-night no-underline">
                    <x-oasis.logo />
                </a>
                <p class="text-base leading-[1.6] text-on-night-2">Das werbefreie soziale Netzwerk. Getragen von Mitgliedern, nicht von Werbekunden.</p>
            </div>
            <div class="flex flex-[3_1_560px] flex-wrap gap-x-10 gap-y-8">
                @foreach ($columns as $title => $links)
                    <nav aria-label="{{ $title }}" class="flex flex-[1_1_140px] flex-col gap-1">
                        <p class="mb-2 text-sm font-bold uppercase tracking-[1.5px] text-on-night-muted">{{ $title }}</p>
                        @foreach ($links as [$label, $href])
                            <a href="{{ $href }}" class="py-1.5 text-base text-on-night no-underline">{{ $label }}</a>
                        @endforeach
                        @if ($title === 'Hilfe')
                            <span class="py-1.5 text-base text-on-night-2">{{ config('oasis.mail.contact') }}</span>
                        @endif
                    </nav>
                @endforeach
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-4 border-t border-night-line pt-7">
            <p class="text-sm text-on-night-muted">© {{ now()->year }} {{ config('oasis.operator.name') }} · Werbefrei, auch in Zukunft.</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('oasis.kuendigen') }}" class="inline-flex min-h-11 items-center rounded-full border border-on-night-muted px-[18px] text-[15px] font-bold text-on-night no-underline">Verträge hier kündigen</a>
                <a href="{{ route('oasis.widerrufen') }}" class="inline-flex min-h-11 items-center rounded-full border border-on-night-muted px-[18px] text-[15px] font-bold text-on-night no-underline">Vertrag widerrufen</a>
            </div>
        </div>
    </div>
</footer>
