{{-- HealthAlert: failing health checks ($problems: key => label, ok, detail). No personal data. No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Problem im Betrieb</title></head><body>
<h1>Oasis braucht Aufmerksamkeit</h1>
<ul>
@foreach ($problems as $problem)
    <li><strong>{{ $problem['label'] }}:</strong> {{ $problem['detail'] }}</li>
@endforeach
</ul>
<p>Was jeweils zu tun ist, steht in docs/oasis-operations.md. Den aktuellen Stand zeigt der Admin-Bereich:
<a href="{{ route('admin.system') }}">Systemzustand öffnen</a>.</p>
<p>Diese Warnung kommt für dasselbe Problem höchstens alle {{ \App\Console\Commands\Operations\HealthCheckCommand::ALERT_HOURS }} Stunden.</p>
</body></html>
