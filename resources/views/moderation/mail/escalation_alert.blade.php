{{-- EscalationAlert: escalated case (ModerationCase). Deliberately without content or names. No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Eskalierter Fall</title></head><body>
<h1>Eskalierter Fall #{{ $case->id }}</h1>
<p>Art: <strong>{{ $case->escalation?->label() }}</strong>{{ $case->escalated_by === null ? ' (automatisch erkannt)' : '' }}.</p>
<p>Eskaliert am {{ $case->escalated_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr (Berlin). Beweise sind gesichert.</p>
<p>Bitte jetzt im Moderationsbereich die Checkliste abarbeiten. Oasis informiert keine Behörde selbst.</p>
<p><a href="{{ route('moderation.cases.show', $case) }}">Fall #{{ $case->id }} öffnen</a> (Anmeldung mit Zwei-Faktor nötig)</p>
</body></html>
