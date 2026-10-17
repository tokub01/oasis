{{-- AccountReleasedNotice: suspension (AccountSuspension, ended), strikes (int), limit (int). No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Dein Konto ist wieder freigegeben</title></head><body>
<h1>Dein Konto ist wieder freigegeben</h1>
<p>Hallo {{ $suspension->user->display_name }},</p>
@if ($suspension->result === 'confirmed')
    <p>das Moderationsteam hat einen Verstoß gegen unsere <a href="{{ route('oasis.regeln') }}">Community-Regeln</a> bestätigt. Die Sperre ist trotzdem aufgehoben – du kannst Oasis wieder wie gewohnt nutzen. Die Begründung der Entscheidung bekommst du in einer eigenen E-Mail.</p>
    <p>Bestätigte Verstöße bisher: {{ $strikes }} von {{ $limit }}. Nach {{ $limit }} bestätigten Verstößen sperren wir ein Konto dauerhaft.</p>
@else
    <p>das Moderationsteam hat dein Konto geprüft und wieder freigegeben. Du kannst Oasis wieder wie gewohnt nutzen.</p>
@endif
<p><a href="{{ route('app.home') }}">Zu Oasis</a></p>
</body></html>
