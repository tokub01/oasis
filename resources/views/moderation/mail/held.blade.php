{{-- ContentHeldNotice (Art. 17 DSA): post (Post), postUrl (string), reason (string, e.g. "eine Beleidigung"). Sent when the automated check holds a Tropfen. No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Dein Tropfen wird geprüft</title></head><body>
<h1>Dein Tropfen wird geprüft</h1>
<p>Hallo {{ $post->author->display_name }},</p>
<p>unsere automatische Prüfung hat in deinem Tropfen vom {{ $post->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr
möglicherweise {{ $reason }} erkannt und ihn dem Moderationsteam gemeldet. Bis ein Mensch aus unserem Moderationsteam ihn geprüft hat, siehst nur du ihn:
<a href="{{ $postUrl }}">{{ $postUrl }}</a></p>
<ul>
    <li>Die Einschätzung kam von einem automatisierten Verfahren (Sprachmodell). Entscheiden wird ein Mensch, in der Regel innerhalb von 48 Stunden.</li>
    <li>Grundlage sind unsere <a href="{{ route('oasis.regeln') }}">Community-Regeln</a>.</li>
    <li>Ist alles in Ordnung, ist der Tropfen danach wieder für alle sichtbar. Andernfalls bekommst du eine begründete Entscheidung und kannst dich beschweren.</li>
</ul>
<p>Du musst nichts tun. Löschen kannst du den Tropfen jederzeit selbst.</p>
</body></html>
