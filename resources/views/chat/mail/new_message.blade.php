{{-- NewChatMessage: user (User). No sender name and no text – the chat is end-to-end encrypted. No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Neue Chat-Nachricht</title></head><body>
<h1>Du hast eine neue Chat-Nachricht</h1>
<p>Hallo {{ $user->display_name }},</p>
<p>in einem deiner Chats auf Oasis wartet eine neue Nachricht auf dich: <a href="{{ route('app.chat') }}">{{ route('app.chat') }}</a></p>
<p>Aus Datenschutzgründen steht hier weder, von wem sie ist, noch was darin steht – deine Chats sind Ende-zu-Ende-verschlüsselt. Bis du den Chat geöffnet hast, schreiben wir dir dazu nicht noch einmal.</p>
<p>Du bekommst diese E-Mail, weil du sie in der <a href="{{ route('app.privacy') }}#titel-benachrichtigungen">Privatsphäre</a> eingeschaltet hast. Dort kannst du sie jederzeit wieder ausschalten.</p>
</body></html>
