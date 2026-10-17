{{-- DataBreachAlert: a breach with risk was recorded (DataBreach). Title and deadline only. No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Datenpanne erfasst</title></head><body>
<h1>Datenpanne erfasst: {{ $breach->title }}</h1>
<p>Einstufung: <strong>{{ \App\Models\DataBreach::RISKS[$breach->risk] }}</strong>.</p>
<p>Entdeckt am {{ $breach->discovered_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr. Die Meldung an die Aufsichtsbehörde ist bis
<strong>{{ $breach->reportDueAt()->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr</strong> fällig (Art. 33 DSGVO).</p>
<p>Oasis meldet nichts selbst. Die vorbereitete Meldung steht im Admin-Bereich:</p>
<p><a href="{{ route('admin.breaches') }}">Datenpannen öffnen</a> (Anmeldung mit Zwei-Faktor nötig)</p>
</body></html>
