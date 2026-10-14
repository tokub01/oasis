# Hinweise und Erwähnungen (Stufe 8)

Die private Seite `/app/hinweise` (`notifications.index`) zeigt Hinweise chronologisch mit 20 Einträgen pro Seite,
gruppiert nach „Heute“, „Diese Woche“ und „Früher“. Einzelne Hinweise und alle Seiten lassen sich über CSRF-geschützte
Formulare als gelesen markieren. Verifizierte Konten dürfen auch nach Ablauf ihrer Testzeit die Seite und die
Einstellungen (`notifications.settings`, `/app/hinweise/einstellungen`) nutzen. JavaScript ist nicht nötig.

## Zustellung

`PostPublished(Post $post)` wird nach erfolgreichem Speichern einschließlich aller Bilder ausgelöst.
`Followed(User $follower, User|Oasis $target)` wird nur für eine tatsächlich neue Follow-Zeile ausgelöst.
`NotificationServiceProvider` registriert die beiden Listener, Artisan-Befehle und Zeitpläne. Er gehört in
`bootstrap/providers.php` (Integration durch Claude). Die Listener verwenden absichtlich `process`, damit Laravels
zusätzliche automatische `handle`/`__invoke`-Erkennung sie nicht zweimal registriert. Route- und Event-Caches bleiben
mit dem Provider funktionsfähig.

Es gibt sechs unabhängig einstellbare Hinweisarten: neue Person in der Karawane, Antwort, Weitertragen, Erwähnung,
angenommene Einladung und Ernennung zur Gastgeber:in. Folgen einer Oase löst keinen Hinweis aus. Die bestehende
Einladungsannahme erstellt weiterhin die beiderseitigen Follow-Hinweise und zusätzlich den eigenen Einladungshinweis.
Eine Ernennung meldet nur eine tatsächliche Änderung von Mitglied zu Gastgeber:in und nutzt das angemeldete Konto als
Absender; direkte Aufrufe ohne angemeldeten Akteur ändern die Rolle ohne einen Hinweis.

`SendHint` ist die gemeinsame Zustellgrenze. Es prüft Selbstzustellung, `isBlockedWith`, die Stummschaltung durch den
Empfänger (`mutedUserIds`) und `Post::isVisibleTo`. Fehlende Einstellungen bedeuten Hinweise AN und E-Mail AUS.
Blockaden wirken beidseitig, Stummschaltungen nur für den Empfänger. Gespeichert werden Hinweisart, beteiligte IDs und
höchstens 120 Unicode-Zeichen aus dem Tropfen; keine Namen, vollständigen Inhalte oder Einladungs-Tokens.

Ein deterministischer UUID-Schlüssel pro Ereignis und Empfänger sowie eine Datenbanksperre auf dem Empfängerkonto
verhindern doppelte Zustellung. Eine neue Follow-Zeile nach einem Entfolgen ist ein neues Ereignis. Bei Tropfen gilt
Antwort vor Weitertragen vor Erwähnung: dieselbe Person bekommt genau einen Hinweis. Ist die vorrangige Art aus,
entsteht kein Ersatzhinweis einer anderen Art. Die ersten zehn vorhandenen und für den Absender nicht gesperrten
Erwähnten werden in Textreihenfolge berücksichtigt; eigene Konten und unlesbare Tropfen zählen nicht mit.

Beim Anzeigen und beim E-Mail-Digest prüft `HintPresentation` Sicherheit und Sichtbarkeit erneut. Entfernte Tropfen,
gelöschte Konten/Oasen und nachträgliche Blockaden oder Stummschaltungen geben keine gespeicherten Auszüge preis.
Solche Hinweise bleiben bis zum Aufräumen gespeichert; eine Seite kann deshalb weniger als 20 sichtbare Einträge
haben. Claudes dezenter Navigationspunkt darf `$user->unreadNotifications()->count()` verwenden.

## Rich-Text

`Mentions::usernames(string $text): array` liefert eindeutige kleingeschriebene Namen in Textreihenfolge. Die Regel
`[a-z0-9_]{3,30}` erkennt keine Teilnamen längerer Zeichenfolgen und keine normalen E-Mail-Adressen.
`x-network.rich-text :text="$text"` escaped alle Inhalte und verlinkt vorhandene Konten über `oasis.profil`.
Die Komponente verwendet dasselbe `Mentions::PATTERN` wie die Zustellung und übernimmt die Themen-Regel aus
`SearchController::TAG_PATTERN`. Themen führen auf Claudes `search.tags.show`; Zeilenumbrüche bleiben erhalten.
`Mentions::existing()` fragt bei leerer Namensliste nicht ab. Bereits geprüfte Namen (auch nicht vorhandene) werden
nur innerhalb des aktuellen Request-Objekts zwischengespeichert; neue Requests lösen sie erneut auf.
Die Hinweis-Liste und der Digest nutzen `HintPresentation::forMany()`: Absender, Tropfen und Oasen werden pro Liste
jeweils in einer Sammelabfrage geladen. Sicherheits- und Sichtbarkeitsprüfungen bleiben pro Hinweis erhalten;
Ressourcen werden nicht zwischen Aufrufen gecacht, damit entfernte Inhalte sofort verborgen bleiben.
Die Themenroute wird niemals als Platzhalter im Produkt registriert; isolierte Tests legen sie bei Bedarf an.

## E-Mail und Aufräumen

`oasis:notification-digest` läuft täglich um **18:00 Europe/Berlin**. Es versendet nur an explizit eingeschaltete,
verifizierte Konten mit neuen, ungelesenen und aktuell sichtbaren Hinweisen. Die E-Mail enthält eine Summe und Links
zur Liste sowie zum Abschalten, keine Namen oder Tropfen-Auszüge. Hinweise werden dadurch nicht als gelesen markiert.

Die separate Tabelle `notification_preferences` enthält sechs Schalter in `kinds`, `digest_enabled` (Standard false)
und `last_digest_at`. Der Digest verarbeitet neue Hinweise seit dem letzten erfolgreichen Lauf; beim ersten Lauf
höchstens die letzten 24 Stunden. Später am Abend eingehende Hinweise folgen beim nächsten Tageslauf. Ein gesperrter
Datensatz und der Tages-Checkpoint verhindern erneute E-Mails am gleichen Berliner Kalendertag. Auch leere Fenster
werden fortgeschrieben. Fehler beim synchronen Mailversand rollen den Checkpoint zurück und lassen sich wiederholen.
Wie bei synchronem SMTP allgemein kann ein Prozessabbruch nach SMTP-Annahme, aber vor dem DB-Commit zu einem erneuten
Versuch führen; eine atomare Transaktion über SMTP und MySQL ist nicht möglich.

`oasis:prune-notifications` löscht täglich alle Netzwerkhinweise, deren `created_at` mehr als **90 Tage** zurückliegt,
auch ungelesene. Andere Laravel Notifications bleiben unberührt. Beide Tabellen werden beim Löschen eines Kontos
per Foreign-Key-Cascade bereinigt. Es entstehen keine zusätzlichen Konfigurationsschlüssel oder Abhängigkeiten.

Der Produktions-Scheduler muss laufen: `* * * * * php artisan schedule:run`. Zeitpläne lassen sich mit
`php artisan schedule:list` kontrollieren. Manuelle Läufe:

```sh
php artisan oasis:notification-digest
php artisan oasis:prune-notifications
```

## Prüfung

```sh
docker compose exec -T -e DB_DATABASE=testing_codex laravel.test php artisan test tests/Feature/Notifications
docker compose exec -T -e DB_DATABASE=testing_codex -e XDEBUG_MODE=coverage laravel.test php vendor/bin/pest tests/Feature/Notifications --coverage
```

Diese Befehle verwenden die eigene Datenbank `testing_codex`. Die Tests benutzen echte Block-/Mute-Datensätze aus
Antigravitys Paket. Der Abschlussbericht enthält nur tatsächlich gelaufene Prüfungen samt Ergebnis.
