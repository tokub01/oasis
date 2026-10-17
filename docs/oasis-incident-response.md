# Notfallplan: Datenpannen und Sicherheitsvorfälle

Entwurf, Stand 17. Oktober 2026 – mit der Anwältin oder dem Anwalt abstimmen. Gilt für jede Verletzung des Schutzes
personenbezogener Daten: Verlust, unbefugte Offenlegung, Veränderung oder unbefugter Zugriff, auch versehentlich (z. B.
E-Mail an falsche Empfänger, offener Speicher-Bucket, gestohlenes Gerät, kompromittiertes Admin-Konto).

## Die ersten Stunden

1. **Ruhe bewahren, nichts löschen.** Logs, Datenbank und betroffene Dateien nicht bereinigen – sie sind Beweise.
2. **Sofort erfassen:** Admin-Bereich → **Datenpannen** → „Neue Datenpanne erfassen“. Der Zeitpunkt „Bekannt geworden
   am“ startet die 72-Stunden-Frist (Art. 33 Abs. 1 DSGVO). Erfasse lieber früh und ergänze später per Nachtrag.
3. **Eindämmen:** Lücke schließen, betroffene Zugänge sperren, Schlüssel und Passwörter tauschen (`.env`: `APP_KEY`
   nur nach Rücksprache, da er verschlüsselte Daten unlesbar macht; Mollie-, Mistral-, AWS-, Mail-Schlüssel sofort),
   betroffene Sitzungen beenden (`sessions` leeren meldet alle ab).
4. **Risiko einstufen** (im Formular):
   - *Kein Risiko* – z. B. verschlüsselte Daten verloren, Schlüssel sicher: nur dokumentieren.
   - *Risiko* – Meldung an die Aufsichtsbehörde binnen 72 Stunden.
   - *Hohes Risiko* – z. B. Zahlungsdaten, Passwörter im Klartext, sensible Inhalte, große Zahl Betroffener:
     zusätzlich die Betroffenen unverzüglich benachrichtigen (Art. 34 DSGVO).

## Melden (Oasis sendet nichts selbst)

- Zuständig: Landesbeauftragte für Datenschutz und Informationsfreiheit NRW (`config('oasis.privacy.authority')`).
  Die Meldung über das offizielle Online-Formular der Behörde einreichen.
- Den vorbereiteten Text aus „Vorbereitete Meldung an die Aufsichtsbehörde“ kopieren, prüfen und ergänzen. Er enthält
  die Pflichtangaben nach Art. 33 Abs. 3: Art der Verletzung, Kategorien und ungefähre Zahl der Betroffenen und
  Datensätze, Kontakt, wahrscheinliche Folgen, Maßnahmen.
- Nicht alles bekannt? Trotzdem fristgerecht melden und schrittweise nachreichen (Art. 33 Abs. 4).
- Nach 72 Stunden: sofort melden und die Verzögerung begründen; das Register fügt dafür einen Platzhalter ein.
- Danach „Meldung vermerken“ mit Behörde und Aktenzeichen.

## Betroffene benachrichtigen (bei hohem Risiko)

In klarer, einfacher Sprache (Art. 34 Abs. 2): was passiert ist, welche Daten betroffen sind, wahrscheinliche Folgen,
was wir tun, was die Person selbst tun kann (z. B. Passwort ändern, auf Phishing achten), Kontakt
`config('oasis.mail.privacy')`. Versand per E-Mail aus dem Betreiber-Postfach; danach „Benachrichtigung vermerken“.

## Danach

- Ursache, Verlauf und Maßnahmen als Nachträge dokumentieren; erst abschließen, wenn Meldung und Benachrichtigung
  vermerkt sind (die Seite erzwingt das).
- Zahlungsdienst betroffen? Mollie informieren. Hosting oder Speicher betroffen? IONOS bzw. AWS informieren.
- Prüfen, ob die TOMs (docs/oasis-toms.md) angepasst werden müssen.
- Abgeschlossene Einträge werden nach `oasis.privacy.records_years` gelöscht.

## Kontakte (vom Betreiber auszufüllen)

| Rolle | Wer | Erreichbar |
| --- | --- | --- |
| Verantwortlich, Entscheidung über Meldung | Tobias Kubina | [noch festzulegen] |
| Technische Eindämmung | [noch festzulegen] | [noch festzulegen] |
| Rechtliche Beratung | [noch festzulegen] | [noch festzulegen] |
