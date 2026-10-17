{{-- AccountSuspendedNotice (Art. 17 DSA): suspension (AccountSuspension), strikes (int), limit (int). No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Dein Konto ist gesperrt</title></head><body>
@php($ban = $suspension->type === \App\Models\AccountSuspension::BAN)
<h1>{{ $ban ? 'Dein Konto ist dauerhaft gesperrt' : 'Dein Konto ist vorübergehend gesperrt' }}</h1>
<p>Hallo {{ $suspension->user->display_name }},</p>
<p>{{ $suspension->label() }} seit {{ $suspension->started_at->timezone('Europe/Berlin')->format('d.m.Y, H:i') }} Uhr.</p>
<p><strong>Vorwurf:</strong> {{ $suspension->accusation }}</p>
@if ($suspension->excerpt)
    <p><strong>Betroffener Text:</strong> „{{ $suspension->excerpt }}“</p>
@endif
<ul>
    @if ($suspension->type === \App\Models\AccountSuspension::PROVISIONAL)
        <li>Erkannt hat das ein automatisiertes Verfahren (Sprachmodell). Ob wirklich ein Verstoß vorliegt, entscheidet ein Mensch aus unserem Moderationsteam, in der Regel innerhalb von {{ config('oasis.moderation.review_hours') }} Stunden. Bis dahin ist dein Konto gesperrt.</li>
    @endif
    <li>Bestätigte Verstöße bisher: {{ $strikes }} von {{ $limit }}. Nach {{ $limit }} bestätigten Verstößen sperren wir ein Konto dauerhaft.</li>
    <li>Du kannst dich äußern – dich entschuldigen, den Zusammenhang erklären oder widersprechen: <a href="{{ route('app.suspended') }}">{{ route('app.suspended') }}</a>. Das Moderationsteam liest es vor seiner Entscheidung.</li>
    <li>Auch während der Sperre kannst du dich anmelden, deine Daten herunterladen, deine Mitgliedschaft kündigen und dein Konto löschen.</li>
    <li>Grundlage sind unsere <a href="{{ route('oasis.regeln') }}">Community-Regeln</a>.</li>
</ul>
</body></html>
