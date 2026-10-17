# Oasis Safety Architecture (Stage 8)

Blockieren, Stummschalten und private Merkliste für das Oasis-Netzwerk.

## Grundsätze & Datenschutz

- **Ruhig & ohne Suchtmechanik**: Keine Zähler-Badges für Merklisten, keine Push-Spirale, keine öffentlichen Zähler, wer was gemerkt hat.
- **Leise Schutzmaßnahmen**: Blockierte oder stummgeschaltete Personen werden zu keinem Zeitpunkt benachrichtigt.
- **Konsequente Nicht-Existenz**: Bei einer Blockade existieren die beiden Konten füreinander schlicht nicht mehr (404 Not Found für Profile und Tropfen).
- **Vollständige Zugänglichkeit**: Livewire-Aktionen benötigen JavaScript und bleiben durch Web-Session/CSRF und erneute Autorisierung geschützt; Rückmeldungen nutzen barrierefreie HTML5-Elemente (`role="status"`, zugängliche Namen für Bildschirmleser).

---

## 1. Datenmodell (`database/migrations/2026_10_14_200000_create_safety_tables.php`)

| Tabelle | Model | Felder & Fremdschlüssel | Zweck & Regeln |
|---|---|---|---|
| `blocks` | `App\Models\Block` | `id`, `user_id` (FK `users`, cascade), `blocked_user_id` (FK `users`, cascade), timestamps, Unique(`user_id`, `blocked_user_id`), Index(`blocked_user_id`, `user_id`) | Beidseitige Blockade: Konten sehen gegenseitig keine Tropfen, Antworten oder Profile (404), können sich nicht folgen, nicht interagieren, Rollen nicht ändern und keinen Chat starten. |
| `mutes` | `App\Models\Mute` | `id`, `user_id` (FK `users`, cascade), `muted_user_id` (FK `users`, cascade), timestamps, Unique(`user_id`, `muted_user_id`), Index(`muted_user_id`) | Einseitige Stummschaltung: Tropfen der Person verschwinden aus Quelle, Entdecken, Oasen und Antworten des Betrachters. Profil bleibt aufrufbar. |
| `bookmarks` | `App\Models\Bookmark` | `id`, `user_id` (FK `users`, cascade), `post_id` (FK `posts`, cascade), timestamps, Unique(`user_id`, `post_id`), Index(`user_id`, `created_at`) | Private Merkliste: Tropfen privat speichern/entfernen (nur für Mitglieder). Niemand sieht, wer was gemerkt hat (auch Autor:innen nicht). |

Beim Löschen eines Kontos (`User::delete()`) kaskadieren alle Blöcke, Stummschaltungen und Lesezeichen automatisch über Datenbank-Fremdschlüssel (`ON DELETE CASCADE`).

---

## 2. Schnittstelle `App\Models\Concerns\HasSafety`

Das Trait wird von `App\Models\User` verwendet und stellt standardisierte Methoden bereit:

- `isBlockedWith(User $other): bool`: Prüft beidseitig, ob eine Blockade zwischen zwei Konten vorliegt.
- `blockedUserIds(): array<int>`: Liefert alle Benutzer-IDs, mit denen eine Blockade in beliebiger Richtung besteht (gecached pro Request).
- `mutedUserIds(): array<int>`: Liefert alle Benutzer-IDs, die dieses Konto stummgeschaltet hat (einseitig, gecached pro Request).
- `hasBlocked(User $other): bool`: Prüft, ob dieses Konto ein anderes aktiv blockiert hat.
- `hasMuted(User $other): bool`: Prüft, ob dieses Konto ein anderes stummgeschaltet hat.
- `hasBookmarked(Post $post): bool`: Prüft, ob dieses Konto einen Tropfen gemerkt hat.
- `clearSafetyCache(): void`: Setzt die In-Memory-Caches der Benutzer-IDs zurück (z. B. nach Mutationen).

---

## 3. Andockpunkte im Netzwerk & Chat

1. **`App\Queries\Network\PostFeed`**:
   - Alle Listen (`source`, `byAuthor`, `inOasis`, `discover`, `matching`, `tagged`, `replies`) filtern Tropfen von blockierten Benutzern (beidseitig) und stummgeschalteten Benutzern (für den Betrachter) über `filterSafety` aus (`whereNotIn('posts.user_id', ...)`).
   - `withCardData` lädt die Lesezeichen-Präsenz (`bookmarks as bookmarked`) für den Betrachter optimiert via Subquery.

2. **`App\Models\Post` & `App\Policies\PostPolicy`**:
   - `Post::isVisibleTo(?User $viewer)` gibt `false` zurück, wenn zwischen Autor:in und Betrachter:in eine Blockade besteht.
   - `PostPolicy::view` verweigert die Sichtbarkeit bei Blockaden.
   - `PostPolicy::interact` verweigert Antworten, Weitertragen und Reaktionen bei Blockaden.

3. **`App\Livewire\Network\ProfileShow`**:
   - `render`: Gibt `404 Not Found` zurück, wenn zwischen Profilinhaber:in und Betrachter:in eine Blockade vorliegt.

4. **`App\Actions\Network\ManageFollows`**:
   - `follow`: Wirft `ValidationException`, wenn die Zielperson blockiert ist oder den Aufrufer blockiert hat.

5. **`App\Actions\Network\ManageOasis`**:
   - `setRole`: Wirft `ValidationException`, wenn zwischen agierendem Host und dem Mitglied eine Blockade vorliegt.

6. **`App\Actions\Chat\ManageConversation`**:
   - `create`: Wirft `ValidationException` (HTTP 422), wenn ein Mitglied blockiert ist.
   - `membership`: Verweigert mit HTTP 422 das Hinzufügen von blockierten Konten zu Gruppenchats.

7. **`App\Actions\Safety\ManageBlock`**:
   - Beim Blockieren werden bestehende Follow-Beziehungen zwischen den beiden Konten in **beide** Richtungen sofort gelöscht.

8. **`App\Queries\Safety\BookmarkList`**:
   - Sortierung: Neueste Lesezeichen zuerst (`bookmarks.id DESC`).
   - Ungültige Tropfen (gelöscht, aus Oasen entfernt, oder von blockierten/stummgeschalteten Konten) werden automatisch herausgefiltert.

---

## 4. Routen (`routes/safety.php`) & Livewire-Komponenten (Stage 12)

Alle Sicherheits-Routen erfordern ein authentifiziertes, bestätigtes Konto (`middleware(['auth', 'verified'])`) und werden als klassenbasierte Livewire-4-Vollseitenkomponenten über `Route::livewire` ausgeliefert:

- `GET /app/sicherheit` (`safety.index`) → `App\Livewire\Safety\SafetyOverview`: Übersicht blockierter und stummgeschalteter Konten mit direkten Livewire-Aktionen (`unblock(int $userId)`, `unmute(int $userId)`).
- `GET /app/gemerkt` (`safety.bookmarks`) → `App\Livewire\Safety\Bookmarks`: Private Merkliste (neueste zuerst) mit `App\Livewire\Concerns\InteractsWithPosts` für Live-Interaktionen im Feed (inkl. `bookmark` / `unbookmark`).

Blockieren, Stummschalten und Merken aus Feeds oder Profilen heraus laufen direkt über Livewire-Aktionen (`InteractsWithPosts` bzw. Profil-Komponente) auf Basis der bestehenden Domain-Actions (`ManageBlock`, `ManageMute`, `ManageBookmark`).

---

## 5. Benutzeroberfläche & Komponenten

- **`post-card.blade.php`**:
  - Knopf "Merken" / "Gemerkt" für Mitglieder mit diskretem Lesezeichen-Icon (`wire:submit="bookmark(...)"` / `wire:submit="unbookmark(...)"`).
  - Aktionen "Stumm" / "Nicht mehr stumm", "Blockieren" und "Melden" in der Fußzeile (nur für fremde Beiträge).
- **Profil-Ansicht**:
  - Knöpfe "Stummschalten" / "Nicht mehr stumm" und "Blockieren" für angemeldete Mitglieder im Kopfbereich fremder Profile.
- **`resources/views/livewire/safety/safety-overview.blade.php`**:
  - Übersichtliche Listen für blockierte und stummgeschaltete Konten mit Zählern, Avataren, Namen (`wire:navigate`), `wire:key` pro Zeile und Aktionen zum Aufheben (`wire:click="unblock(...)"`, `wire:click="unmute(...)"`).
  - Zweispaltiges Layout (`x-network.columns`, `'wide' => true`) mit erklärenden Infokarten.
- **`resources/views/livewire/safety/bookmarks.blade.php`**:
  - Darstellung über `<x-network.feed>` mit `wire:key` pro Beitrag und Hinweisen zur privaten Natur der Lesezeichen.

## Privatsphäre-Center (Stufe 13)

Die Zielgruppen aus `privacy_settings` ergänzen die Sicherheitsregeln; eine Blockade gewinnt immer.
„Menschen, denen du folgst“ meint vom Mitglied selbst gewählte Personen, nicht die Karawane.
Profilzugriff, Karawane-Zahl, Antworten und Weitertragen werden serverseitig getrennt geprüft. Eine sichtbare
Nachricht allein berechtigt deshalb nicht mehr zu Antworten oder Weitertragen; Reaktionen und Merken bleiben
unverändert. Bereits offene Composer prüfen die Freigabe vor jedem Schreiben erneut.

`SendHint::allowed` prüft bei Erwähnungen zusätzlich `mentions_audience` der erwähnten Person gegenüber dem Autor.
Diese Prüfung gilt auch beim späteren Lesen von Hinweisen und beim Digest. Ein nicht erlaubtes `@name` bleibt als
Text erhalten, bekommt aber keinen Profillink. Rich-Text erhält dafür den tatsächlichen Textautor, auch bei Zitaten
und gebündelten Antworten; der aktuelle Leser ändert die Erlaubnis nicht. Ziele, Follow- und Block-Kanten werden
für die Liste gesammelt geladen. Profil- und Suchmaschinenfreigaben sind unabhängig von Blockiert/Stumm und der
Sichtbarkeit einzelner Tropfen; `noindex` ersetzt keine Zugriffskontrolle.
