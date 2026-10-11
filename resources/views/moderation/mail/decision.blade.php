{{-- DecisionNotice: decision (ModerationDecision), recipient (reporter|subject), complaintUrl (string). No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Moderationsentscheidung</title></head><body>
<h1>Entscheidung zu einer Inhaltsmeldung</h1>
<p>Inhalt: {{ $decision->case->subject_url }}</p>
<p>Entschieden am {{ $decision->decided_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr (Berlin).</p>
<p>Ergebnis: {{ $decision->outcome->label() }}.</p>
@if ($recipient === 'subject')
    <p>Grundlage: {{ $decision->ground->label() }} – {{ $decision->rule_reference }}</p>
    <h2>Maßgebliche Tatsachen</h2><p>{{ $decision->facts }}</p>
    <h2>Begründung</h2><p>{{ $decision->explanation }}</p>
    <p>Es wurden keine automatisierten Verfahren für diese Entscheidung eingesetzt.</p>
@else
    <p>Wir haben deine Meldung geprüft. Persönliche Angaben über betroffene Konten teilen wir nicht mit.</p>
@endif
<p>Du kannst kostenlos innerhalb von mindestens sechs Monaten ab dieser Mitteilung
<a href="{{ $complaintUrl }}">intern Beschwerde einlegen</a>. Eine andere Person im Team prüft deine Beschwerde.
Du kannst auch auf diese E-Mail antworten oder an {{ config('oasis.mail.dsa') }} schreiben.</p>
<p>Du kannst dich an eine nach Art. 21 DSA zertifizierte außergerichtliche Streitbeilegungsstelle wenden.
Gerichtliche Rechtsbehelfe bleiben dir offen. Die interne Beschwerde beschränkt diese Rechte nicht.</p>
</body></html>
