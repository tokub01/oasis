@extends('layouts.oasis', ['title' => 'Gemeinsam starten', 'description' => 'Gemeinsam starten bei Oasis.', 'active' => 'gemeinsam', 'noindex' => false])
@section('content')


<section aria-labelledby="g-titel" class="[padding:clamp(56px,_6vw,_88px)_0] bg-dune">
<div class="[max-width:1296px] [margin:0_auto] [padding:0_clamp(20px,_4vw,_48px)] grid [grid-template-columns:repeat(auto-fit,_minmax(min(420px,_100%),_1fr))] [gap:clamp(40px,_5vw,_72px)] items-center">
<div class="flex flex-col gap-[22px]">
<p class="m-0 text-[14px] font-bold [letter-spacing:2.5px] uppercase text-sun-text">Gemeinsam starten</p>
<h1 id="g-titel" class="m-0 o-display font-medium [font-size:clamp(44px,_6vw,_84px)] [line-height:1.02] [letter-spacing:-0.02em] text-night">Zieh nicht allein um.</h1>
<p class="m-0 [max-width:32em] [font-size:clamp(18px,_1.5vw,_20px)] [line-height:1.6] text-ink-2">Ein Netzwerk ist nur so gut wie die Menschen darin. Darum könnt ihr bei Oasis als Gruppe starten: Euer Test beginnt erst, wenn alle da sind.</p>
<a href="{{ route('oasis.testen') }}" class="[align-self:flex-start] inline-flex items-center justify-center [min-height:56px] [padding:0_28px] rounded-[999px] bg-sun text-sun-ink text-[18px] font-extrabold no-underline">Gruppe anlegen</a>
</div>
<figure aria-label="Beispiel: Fachschaft startet gemeinsam" class="m-0 [justify-self:center] w-full [max-width:460px] [box-sizing:border-box] [padding:30px] bg-paper rounded-[28px] flex flex-col gap-[20px] [box-shadow:0_24px_50px_-28px_rgba(14,_43,_47,_0.45)]">
<div class="flex flex-col [line-height:1.3] gap-[4px]">
<span class="text-[14px] font-bold [letter-spacing:1.5px] uppercase text-spring-text">Gruppenstart</span>
<span class="text-[22px] font-extrabold text-night">Fachschaft Biologie</span>
<span class="text-[15px] text-muted">7 von 12 haben angenommen</span>
</div>
<div aria-hidden="true" class="flex gap-[6px]">
<span class="[flex:1] [height:8px] rounded-[4px] bg-spring"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-spring"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-spring"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-spring"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-spring"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-spring"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-spring"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-dune-line"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-dune-line"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-dune-line"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-dune-line"></span>
<span class="[flex:1] [height:8px] rounded-[4px] bg-dune-line"></span>
</div>
<div class="flex items-center gap-[10px] [padding:12px_14px] bg-sand rounded-[14px] text-[15px] text-ink">
<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#1F6F69" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-none"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
<span>Euer Test startet, wenn alle da sind, spätestens in {{ config('oasis.group_start_days') }} Tagen.</span>
</div>
<span class="inline-flex items-center justify-center gap-[10px] [min-height:48px] rounded-[999px] bg-night text-on-night text-[16px] font-bold">
<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
Einladungslink kopieren</span>
</figure>
</div>
</section>

<section aria-labelledby="ablauf-titel" class="[padding:clamp(64px,_7vw,_104px)_0]">
<div class="[max-width:1296px] [margin:0_auto] [padding:0_clamp(20px,_4vw,_48px)] flex flex-col gap-[44px]">
<h2 id="ablauf-titel" class="m-0 o-display font-medium [font-size:clamp(30px,_3.2vw,_46px)] [line-height:1.12] [letter-spacing:-0.015em] text-night">So funktioniert der Gruppenstart</h2>
<ol class="m-0 p-0 list-none grid [grid-template-columns:repeat(auto-fit,_minmax(min(300px,_100%),_1fr))] gap-[24px]">
<li class="flex flex-col gap-[12px] [padding:32px_28px] bg-paper rounded-[24px]">
<span aria-hidden="true" class="o-display text-[56px] font-medium [line-height:1] text-sun-text">1</span>
<h3 class="m-0 text-[22px] [line-height:1.3] font-extrabold text-night">Gruppe anlegen</h3>
<p class="m-0 text-[16px] [line-height:1.6] text-ink-2">Gib eurer Gruppe einen Namen, zum Beispiel „WG Kastanienallee“ oder „Fachschaft Bio“.</p>
</li>
<li class="flex flex-col gap-[12px] [padding:32px_28px] bg-paper rounded-[24px]">
<span aria-hidden="true" class="o-display text-[56px] font-medium [line-height:1] text-sun-text">2</span>
<h3 class="m-0 text-[22px] [line-height:1.3] font-extrabold text-night">Link teilen</h3>
<p class="m-0 text-[16px] [line-height:1.6] text-ink-2">Schick den Einladungslink per WhatsApp, Signal oder Mail. Wer ihn öffnet, kann erst mal ohne Konto reinlesen.</p>
</li>
<li class="flex flex-col gap-[12px] [padding:32px_28px] bg-paper rounded-[24px]">
<span aria-hidden="true" class="o-display text-[56px] font-medium [line-height:1] text-sun-text">3</span>
<h3 class="m-0 text-[22px] [line-height:1.3] font-extrabold text-night">Gemeinsam loslegen</h3>
<p class="m-0 text-[16px] [line-height:1.6] text-ink-2">Euer {{ config('oasis.trial_days') }}-Tage-Test startet für alle gleichzeitig, sobald die Gruppe vollständig ist.</p>
</li>
</ol>
<div class="flex flex-col gap-[16px]">
<h3 class="m-0 text-[20px] font-extrabold text-night">Passt für</h3>
<ul class="m-0 p-0 list-none flex flex-wrap gap-[10px]">
<li class="text-[16px] font-bold [padding:9px_18px] [border:1px_solid_#D6C8B0] rounded-[999px]">WGs</li>
<li class="text-[16px] font-bold [padding:9px_18px] [border:1px_solid_#D6C8B0] rounded-[999px]">Freundeskreise</li>
<li class="text-[16px] font-bold [padding:9px_18px] [border:1px_solid_#D6C8B0] rounded-[999px]">Fachschaften</li>
<li class="text-[16px] font-bold [padding:9px_18px] [border:1px_solid_#D6C8B0] rounded-[999px]">Ausbildungsjahrgänge</li>
<li class="text-[16px] font-bold [padding:9px_18px] [border:1px_solid_#D6C8B0] rounded-[999px]">Vereine</li>
<li class="text-[16px] font-bold [padding:9px_18px] [border:1px_solid_#D6C8B0] rounded-[999px]">Teams</li>
<li class="text-[16px] font-bold [padding:9px_18px] [border:1px_solid_#D6C8B0] rounded-[999px]">Initiativen</li>
</ul>
</div>
</div>
</section>

<section aria-label="Einladungen und Patenplätze" class="[padding:clamp(64px,_7vw,_104px)_0] bg-paper [border-top:1px_solid_#E5DBC9] [border-bottom:1px_solid_#E5DBC9]">
<div class="[max-width:1296px] [margin:0_auto] [padding:0_clamp(20px,_4vw,_48px)] grid [grid-template-columns:repeat(auto-fit,_minmax(min(420px,_100%),_1fr))] gap-[24px]">
<div class="flex flex-col gap-[14px] [padding:36px] [border:1px_solid_#D6C8B0] rounded-[28px]">
<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="#0E2B2F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><path d="M22 6l-10 7L2 6"></path></svg>
<h2 class="m-0 text-[24px] [line-height:1.3] font-extrabold text-night">Einladungen verschenken</h2>
<p class="m-0 text-[17px] [line-height:1.6] text-ink-2">Jedes Mitglied kann {{ config('oasis.invites_per_month') }} Testzugänge im Monat verschenken. Eine Einladung ist ein Geschenk, keine Pflicht: Niemand muss andere werben, um selbst dabei zu sein.</p>
</div>
<div class="flex flex-col gap-[14px] [padding:36px] [border:1px_solid_#D6C8B0] rounded-[28px]">
<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="#0E2B2F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12v10H4V12"></path><path d="M2 7h20v5H2z"></path><path d="M12 22V7"></path><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
<h2 class="m-0 text-[24px] [line-height:1.3] font-extrabold text-night">Patenplätze</h2>
<p class="m-0 text-[17px] [line-height:1.6] text-ink-2">Mäzen-Mitglieder und Partnerorganisationen verschenken Patenplätze an Menschen, denen auch {{ \App\Support\OasisFormat::euro(config('oasis.tiers.solidar.monthly')) }} im Monat gerade zu viel sind. Ohne Nachweis.</p>
</div>
</div>
</section>

<section aria-labelledby="orga-titel" class="[padding:clamp(64px,_7vw,_104px)_0]">
<div class="[max-width:1296px] [margin:0_auto] [padding:0_clamp(20px,_4vw,_48px)] grid [grid-template-columns:repeat(auto-fit,_minmax(min(420px,_100%),_1fr))] [gap:40px_72px] items-start">
<div class="flex flex-col gap-[16px]">
<p class="m-0 text-[14px] font-bold [letter-spacing:2.5px] uppercase text-sun-text">Für Organisationen</p>
<h2 id="orga-titel" class="m-0 o-display font-medium [font-size:clamp(30px,_3.2vw,_46px)] [line-height:1.12] [letter-spacing:-0.015em] text-night">Mit vielen auf einmal umziehen?</h2>
<p class="m-0 [max-width:30em] text-[18px] [line-height:1.6] text-ink-2">Hochschulgruppen, Vereine und Initiativen: Schreibt uns, dann planen wir euren Start gemeinsam, mit eigener Oase und Gastgeber:innen aus euren Reihen.</p>
</div>
<form method="POST" action="{{ route('oasis.gemeinsam.store') }}" aria-label="Anfrage für Organisationen" class="flex flex-col gap-[18px] [padding:32px] bg-paper [border:1px_solid_#D6C8B0] rounded-[28px]">
@csrf
@include('oasis.forms._feedback')
<div class="flex flex-col gap-[6px]">
<label for="orga-name" class="text-[15px] font-bold">Name der Organisation</label>
<input id="orga-name" type="text" placeholder="z. B. Fachschaft Biologie" class="o-input min-w-0 [min-height:48px] [padding:0_14px] [border:1px_solid_#9C8E77] rounded-[12px] bg-field font-sans text-[16px] text-ink" name="organisation" aria-invalid="{{ $errors->has('organisation') ? 'true' : 'false' }}" aria-describedby="orga-name-error" required value="{{ old('organisation') }}">
<p id="orga-name-error" class="o-error">@error('organisation'){{ $message }}@enderror</p>
</div>
<div class="flex flex-col gap-[6px]">
<label for="orga-anzahl" class="text-[15px] font-bold">Wie viele Leute ungefähr?</label>
<input id="orga-anzahl" type="number" min="2" placeholder="z. B. 40" class="o-input min-w-0 [min-height:48px] [padding:0_14px] [border:1px_solid_#9C8E77] rounded-[12px] bg-field font-sans text-[16px] text-ink" name="people" aria-invalid="{{ $errors->has('people') ? 'true' : 'false' }}" aria-describedby="orga-anzahl-error" required value="{{ old('people') }}">
<p id="orga-anzahl-error" class="o-error">@error('people'){{ $message }}@enderror</p>
</div>
<div class="flex flex-col gap-[6px]">
<label for="orga-mail" class="text-[15px] font-bold">E-Mail für Rückfragen</label>
<input id="orga-mail" type="email" placeholder="name@beispiel.tld" class="o-input min-w-0 [min-height:48px] [padding:0_14px] [border:1px_solid_#9C8E77] rounded-[12px] bg-field font-sans text-[16px] text-ink" name="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="orga-mail-error" required value="{{ old('email') }}">
<p id="orga-mail-error" class="o-error">@error('email'){{ $message }}@enderror</p>
</div>
<button type="submit" class="[min-height:52px] border-0 rounded-[999px] bg-night text-on-night text-[17px] font-extrabold">Anfrage senden</button>
<p class="m-0 text-[14px] [line-height:1.5] text-muted">Wir nutzen deine Angaben nur, um dir zu antworten. Mehr in der <a href="{{ route('oasis.datenschutz') }}" class="text-ink underline">Datenschutzerklärung</a>.</p>
</form>
</div>
</section>


@endsection
