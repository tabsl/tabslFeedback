# tabslFeedback

OXID eShop Modul für ein Feedback-Formular in Backend und Shop. Der Freitext des
Melders wird von OpenAI zu Titel und Beschreibung aufbereitet; daraus entsteht
ein GitLab-Issue mit Screenshots und technischem Kontext.

## Warum dieses Modul nutzen?

Fehlermeldungen zu einem Shop kommen heute per Telefon, E-Mail oder Chat:

- ❌ „Das geht nicht" — ohne URL, ohne Browser, ohne Screenshot
- ❌ Jemand muss die Meldung lesen, verstehen und von Hand in ein Ticket übertragen
- ❌ Meldungen, die niemand überträgt, verschwinden
- ❌ Beobachtungen von Shop-Besuchern erreichen die Entwicklung praktisch nie

**tabslFeedback** löst das durch:

- ✅ Einen Feedback-Einstieg im Backend **und** im Shop, jeweils einzeln abschaltbar
- ✅ Screenshots per Zwischenablage — einfügen, Vorschau prüfen, absenden
- ✅ Automatisch erhobenen technischen Kontext (Seite, Browser, Theme, Modulliste …)
- ✅ Ein fertig formuliertes GitLab-Issue, direkt der zuständigen Person zugewiesen
- ✅ Betrieb ohne jede Rückfrage an den Melder — er sieht nur eine Bestätigung

## Hauptfunktionen

### Zwei Einstiege, unabhängig schaltbar

- **Backend** — zusätzlicher Link im Header oben links, Formular im Hauptframe
- **Shop** — kleiner Button auf jeder Seite, Position wählbar (unten links, unten
  mittig, unten rechts)

Beide Formulare lassen sich getrennt aktivieren; „beide aus" ist ein zulässiger
Zustand. Fehlen die GitLab-Pflichtangaben, erscheint gar kein Einstieg.

### Formular ohne sichtbaren Button öffnen

Jede Shop-Seite lässt sich mit dem Parameter `?tabslFeedback=1` aufrufen — dann
öffnet sich das Feedback-Formular sofort, auch wenn der Button ausgeblendet ist:

```
https://mein-shop.de/?tabslFeedback=1
https://mein-shop.de/Kategorie/Artikel.html?tabslFeedback=1
```

Gedacht für Testende und Redaktion: Man verschickt den Link, ohne den Button
für alle Besucher einzuschalten. `?tabslFeedback=0` unterdrückt ihn umgekehrt
auf einer Seite, auf der er sonst erschiene.

> ⚠️ **Der Parameter ist bewusst nicht durch ein Geheimnis geschützt**, und sein
> Name steht in diesem quelloffenen Repository. Die Einstellung „Feedback-Button
> im Shop anzeigen" regelt deshalb nur die **Sichtbarkeit**, nicht die
> **Erreichbarkeit**: Sobald die GitLab-Angaben vollständig sind, nimmt der Shop
> Feedback entgegen — auch bei ausgeblendetem Button. Wer das nicht möchte, lässt
> die GitLab-Angaben leer oder deaktiviert das Modul. Gegen automatisierten
> Missbrauch schützt allein `tabslTurnstile` (siehe unten).

### Screenshots aus der Zwischenablage

Bild kopieren, im Formular `Strg+V` bzw. `⌘+V` — fertig. Mehrere Bilder pro
Meldung, jedes mit Vorschau und einzeln wieder entfernbar. Feedback ohne Bild
bleibt jederzeit möglich.

### Aufbereitung durch die KI — mit Rückfallebene

OpenAI erzeugt aus dem Freitext einen Titel und eine lesbare Beschreibung. Der
**ursprüngliche Wortlaut steht immer zusätzlich im Issue** — eine schlechte
Aufbereitung kann eine Meldung so nicht unbrauchbar machen.

Ist kein API-Key hinterlegt oder antwortet OpenAI nicht, entsteht das Issue
trotzdem: mit dem Rohtext, den Screenshots und dem Kontext, und einem Vermerk,
dass keine Aufbereitung stattfand. Der Melder bemerkt keinen Unterschied.

### Technischer Kontext am Ticket

In einem eingeklappten Block: aufgerufene Seite, Referrer, Browser inkl. Version
und Betriebssystem, Fenster- und Bildschirmgröße, Shop-ID, Sprache, Währung,
aktives Theme mit Version, Liste der aktiven Module mit Versionen, Shop- und
PHP-Version. Bei angemeldeten Kunden optional Kundennummer, Name und E-Mail.

## Schnellstart

### Installation

```bash
composer require tabsl/tabslfeedback
```

Alternativ manuell nach `source/modules/tabsl/tabslFeedback/` entpacken.

Anschließend aktivieren:

```bash
vendor/bin/oe-console oe:module:activate tabslFeedback
```

oder im Backend unter **Erweiterungen → Module**.

### GitLab vorbereiten

1. **Projekt-ID** ermitteln: In GitLab die Startseite des Zielprojekts öffnen —
   die numerische ID steht dort unter dem Projektnamen bzw. im Menü **⋮ →
   „Projekt-ID kopieren"**.
2. **Zugangs-Token anlegen**: Im Zielprojekt unter **Settings → Access tokens**
   einen *Project Access Token* mit dem Scope **`api`** und der Rolle
   *Reporter* (oder höher, je nach Berechtigungsmodell) erzeugen.
   → Einem persönlichen Zugangstoken vorziehen: dessen Reichweite umfasst alle
   Projekte des Kontos, ein Project-Access-Token nur dieses eine.
3. **Benutzer-ID der zuständigen Person** ermitteln: Deren GitLab-Profil öffnen —
   die numerische User-ID steht in der Profilübersicht. Bleibt das Feld leer,
   entstehen Issues ohne Zuweisung.

### Modul konfigurieren

Im Backend unter **Erweiterungen → Module → tabslFeedback → Einstellungen**:

| Einstellung | Bedeutung | Standard |
| --- | --- | --- |
| `tabslfeedback_admin_enabled` | Feedback-Link im Backend-Header einblenden | aus |
| `tabslfeedback_frontend_enabled` | Feedback-**Button** im Shop einblenden (steuert die Sichtbarkeit, nicht die Erreichbarkeit — siehe unten) | aus |
| `tabslfeedback_button_position` | Position des Buttons: `bottom-left` (unten links), `center` (unten mittig), `bottom-right` (unten rechts) | `bottom-right` |
| `tabslfeedback_gitlab_url` | Basis-Adresse der GitLab-Instanz inkl. Schema, ohne `/api/v4` — **Pflicht** | leer |
| `tabslfeedback_gitlab_project_id` | Numerische ID des Zielprojekts — **Pflicht** | leer |
| `tabslfeedback_gitlab_token` | Project-Access-Token mit Scope `api` — **Pflicht** | leer |
| `tabslfeedback_gitlab_assignee_id` | Numerische Benutzer-ID für die Zuweisung; leer = keine Zuweisung | leer |
| `tabslfeedback_openai_key` | OpenAI API-Key; leer = Ticket ohne Aufbereitung | leer |
| `tabslfeedback_openai_model` | Verwendetes Modell | `gpt-4o-mini` |
| `tabslfeedback_ticket_language` | `source` = Sprache der Meldung behalten, `en` = immer Englisch | `source` |
| `tabslfeedback_show_contact_fields` | Optionale Felder für Name und E-Mail anzeigen | aus |
| `tabslfeedback_send_customer_data` | Bei angemeldeten Kunden Kundennummer, Name und E-Mail ins Ticket übernehmen | aus |

Die drei GitLab-Felder sind Pflicht. Fehlt eines davon, erscheint weder der
Header-Link noch der Frontend-Button — ein Formular, das kein Ticket erzeugen
kann, wird gar nicht erst angeboten. Dasselbe gilt für eine Adresse ohne
`http://` oder `https://`, weil daraus keine gültige Anfrage entstehen kann.

> ⚠️ **Die GitLab-Adresse sollte auf `https://` lauten.** Der Zugangs-Token wird
> als HTTP-Header übertragen; über `http://` wandert er unverschlüsselt durchs
> Netz. Für interne Instanzen bleibt `http://` möglich, wird aber bei jeder
> Absendung im Shop-Log vermerkt.

Das Modul enthält **keine** Vorbelegung für Adressen, Projekt-IDs oder
Zugangsdaten.

### Grenzwerte

Fest eingebaut, bewusst nicht konfigurierbar:

| Grenze | Wert |
| --- | --- |
| Bilder je Meldung | 5 |
| Größe je Bild | 10 MB |
| Größe aller Bilder zusammen | 20 MB |
| Länge des Freitexts | 5.000 Zeichen |
| Länge von Name und E-Mail | je 255 Zeichen |
| Bildformate | PNG, JPG, GIF, WebP |

Alle Grenzen werden serverseitig durchgesetzt. Damit die Bilder eine Meldung mit
mehreren Screenshots überhaupt erreichen, sollten `post_max_size` und
`memory_limit` der PHP-Installation oberhalb von 20 MB liegen.

## Welche Daten gehen an wen

Grundlage für die Datenschutzerklärung des einsetzenden Shops. Übermittelt wird
ausschließlich beim Absenden einer Meldung.

### An OpenAI (`api.openai.com`)

**Übermittelt:** ausschließlich der Freitext der Meldung.

**Nicht übermittelt:** Screenshots, Name, E-Mail, Kundendaten, Umgebungsdaten,
IP-Adresse, Shop-Adresse.

Enthält der Freitext personenbezogene Angaben, weil ein Melder sie hineinschreibt,
werden diese mit übertragen — darauf hat das Modul keinen Einfluss. Ohne
hinterlegten API-Key findet **keine** Übermittlung an OpenAI statt; das Ticket
entsteht dann ohne Aufbereitung.

### An die konfigurierte GitLab-Instanz

**Übermittelt:** Freitext, aufbereiteter Titel und Beschreibung, alle Screenshots,
optional Name und E-Mail, sowie der technische Kontext (Seite, Referrer, Browser,
Fenster- und Bildschirmgröße, Shop-ID, Sprache, Währung, Theme, Modulliste,
Shop- und PHP-Version). Bei angemeldeten Kunden zusätzlich Kundennummer, Name und
E-Mail — **nur** wenn `tabslfeedback_send_customer_data` aktiv ist.

**Nicht übermittelt:** IP-Adresse, Warenkorb- und Bestellkontext,
Browser-Konsolenmeldungen.

Das Ziel ist die vom Betreiber selbst gewählte GitLab-Instanz — bei einer eigenen
Installation verlassen die Daten die eigene Infrastruktur nicht.

### An Cloudflare (`challenges.cloudflare.com`)

Nur, wenn das optionale Modul `tabslTurnstile` aktiv und konfiguriert ist.

**Übermittelt:** der Turnstile-Token und die von Cloudflare selbst erhobenen
Signale des Browsers.

**Nicht übermittelt:** Die IP-Adresse des Melders wird von diesem Modul
ausdrücklich **nicht** an Turnstile weitergegeben; das optionale Feld `remoteip`
bleibt leer.

### Nirgendwohin

Das Modul legt **keine** eigene Datenbanktabelle an und speichert Meldungen
nirgends im Shop. Es gibt keinen Zwischenspeicher und keine Wiedervorlage: Ist
GitLab nicht erreichbar, ist die Meldung verloren, und der Melder erhält eine
entsprechende Fehlermeldung statt einer falschen Bestätigung.

### Ins Shop-Log

Damit ausbleibende Tickets überhaupt auffallen, hält das Modul technische
Störungen im Shop-Log fest — jeweils mit dem Präfix `[tabslFeedback]`:

| Ereignis | Stufe |
| --- | --- |
| Issue-Anlage fehlgeschlagen (Meldung verloren) | `error` |
| Screenshot-Upload fehlgeschlagen (Bild fehlt im Ticket) | `error` |
| KI-Aufbereitung fehlgeschlagen — inkl. HTTP-Status und Fehlermeldung des Dienstes | `error` |
| Absendung bei unvollständiger Konfiguration abgewiesen | `error` |
| Zustand des Schutzmoduls nicht ermittelbar | `error` |
| GitLab-Adresse nutzt `http` statt `https` | `warning` |
| Bot-Prüfung nicht bestanden (Normalbetrieb) | `info` |

**Nicht protokolliert** werden der Meldungstext, Kontaktangaben, Kundendaten,
Screenshots und die Zugangsdaten.

> **Hinweis:** OXID protokolliert standardmäßig erst ab Stufe `error`. Die
> Einträge in der Tabelle oben mit dieser Stufe erscheinen also ohne weiteres
> Zutun. Für `warning` und `info` muss `sLogLevel` in `source/config.inc.php`
> entsprechend gesetzt werden:
>
> ```php
> $this->sLogLevel = 'warning';
> ```
>
> Entsteht ein Ticket ohne Aufbereitung, steht der Grund als `error` im Log —
> etwa `openai request failed — http 401, api: invalid_api_key`. Ein
> abgelaufener Schlüssel, ein erschöpftes Kontingent und eine Zeitüberschreitung
> sind daran unterscheidbar.

## Missbrauchsschutz im Shop

Das Frontend-Formular ist öffentlich erreichbar. Jede Absendung erzeugt ein
GitLab-Issue und einen kostenpflichtigen OpenAI-Aufruf — ein Bot kann also
sowohl das Projekt fluten als auch Kosten verursachen.

Das Modul unterstützt dafür [tabslTurnstile](https://github.com/tabsl/tabslTurnstile)
(Cloudflare Turnstile für OXID 6):

- Ist `tabslTurnstile` installiert, aktiviert und mit Site-Key konfiguriert,
  erscheint im Feedback-Formular ein Turnstile-Widget und die Absendung wird
  serverseitig geprüft. Besteht die Prüfung nicht, entsteht **weder** ein Issue
  **noch** wird OpenAI angesprochen.
- Ist `tabslTurnstile` nicht installiert, nicht aktiviert oder ohne Site-Key,
  funktioniert tabslFeedback vollständig weiter — der Schutz entfällt ersatzlos,
  ohne Fehler und ohne Installationsaufforderung.
- Das Backend-Formular ist von der Prüfung ausgenommen; dieser Bereich ist bereits
  durch die Anmeldung geschützt.

> ⚠️ **Der öffentliche Betrieb des Frontend-Formulars ohne Schutzmodul erfolgt auf
> eigenes Risiko.** Wer keinen Bot-Schutz einsetzen möchte, sollte das
> Frontend-Formular deaktiviert lassen und nur das Backend-Formular nutzen.

Es gibt bewusst kein eigenes Rate-Limiting: ohne Zwischenspeicher wäre es nur
über die Session abbildbar und damit gegen Bots wirkungslos.

## Kosten

- **GitLab** — keine zusätzlichen Kosten; es werden nur Issues und Datei-Uploads
  im eigenen Projekt angelegt.
- **OpenAI** — je Meldung ein Aufruf mit dem Freitext (höchstens 5.000 Zeichen)
  und einer kurzen Antwort. Mit dem voreingestellten `gpt-4o-mini` liegen die
  Kosten pro Meldung im Bereich von Bruchteilen eines Cents. Bilder werden nicht
  übermittelt, der teuerste Anteil entfällt also. Die tatsächliche Zahl der
  Aufrufe hängt allein davon ab, wie oft das Formular abgesendet wird — siehe
  Missbrauchsschutz.
- **Cloudflare Turnstile** — dauerhaft kostenlos.

## Was das Modul nicht tut

- Keine Feedback-Übersicht im Shop, kein Archiv, keine Wiedervorlage — GitLab ist
  die einzige Ablage
- Keine Hintergrundverarbeitung: das Ticket entsteht beim Absenden
- Keine Bildauswertung durch die KI
- Keine Labels, keine Priorität, keine Kategorisierung am Issue
- Keine Duplikaterkennung
- Keine Rückmeldung an den Melder über den Bearbeitungsstand
- Genau ein GitLab-Projekt je Shop; keine Jira-, GitHub- oder E-Mail-Ziele

## Kompatibilität

- OXID eShop 6.x
- PHP 7.4 und PHP 8.x
- Getestet mit den Themes `wave` und `ps`; das Frontend-Widget bindet sich an den
  Block `base_js` und setzt weder jQuery noch Bootstrap voraus

## Support

Das Modul wird als Open Source bereitgestellt, ohne Anspruch auf Support oder
Reaktionszeiten. Fehlerberichte und Verbesserungsvorschläge sind über die
GitHub-Issues willkommen; eine Bearbeitung erfolgt nach Möglichkeit.

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md).

## License

GNU General Public License v3.0 — siehe [LICENSE](LICENSE).

## Copyright

Tobias Merkl | https://oxid-module.eu
