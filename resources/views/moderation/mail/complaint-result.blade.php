{{-- ComplaintResult: complaint (ModerationComplaint) with its recorded human review. No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Beschwerdeentscheidung</title></head><body>
<h1>Ergebnis deiner Beschwerde</h1>
<p>Ergebnis: {{ $complaint->status->label() }}.</p>
<p>Geprüft am {{ $complaint->reviewed_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr (Berlin).</p>
<p>Eine zweite Person hat die ursprüngliche Entscheidung geprüft; die Prüfung erfolgte nicht automatisiert.</p>
<h2>Begründung</h2><p>{{ $complaint->review_explanation }}</p>
<p>Außergerichtliche Streitbeilegung durch eine nach Art. 21 DSA zertifizierte Stelle und gerichtliche
Rechtsbehelfe bleiben offen. Bei Fragen antworte auf diese Nachricht oder schreibe an {{ config('oasis.mail.dsa') }}.</p>
</body></html>
