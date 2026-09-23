# tabslFeedback

OXID eShop Modul für ein Feedback-Formular in Backend und Shop. Der Freitext des
Melders wird wahlweise von OpenAI oder Anthropic zu Titel und Beschreibung
aufbereitet; daraus entsteht ein GitLab-Issue oder ein weclapp-Helpdesk-Ticket
mit Screenshots und technischem Kontext. Auch ganz ohne KI nutzbar.

Statt „Das geht nicht" per Telefon oder E-Mail — ohne URL, ohne Browser, ohne
Screenshot, und ohne dass jemand die Meldung von Hand ins Ticketsystem überträgt.

## Funktionen

**Zwei Einstiege, getrennt schaltbar** — im Backend ein Link im Header oben
links, im Shop ein kleiner Button auf jeder Seite (Position wählbar). „Beide
aus" ist ein zulässiger Zustand; ist das Ticket-Ziel nicht vollständig
eingerichtet, erscheint gar kein Einstieg.

**GitLab oder weclapp** — `tabslfeedback_ticket_target` bestimmt, wo das Ticket
entsteht: als GitLab-Issue oder als Ticket im weclapp-Helpdesk. Inhalt und
Ablauf sind für den Melder identisch. In weclapp hängen die Screenshots als
Dokumente am Ticket; die Beschreibung ist dort HTML statt Markdown.

**Screenshots aus der Zwischenablage, abschaltbar** — Bild kopieren, im
Formular `Strg+V` bzw. `⌘+V`. Mehrere Bilder pro Meldung, jedes mit Vorschau und
einzeln entfernbar. Feedback ohne Bild bleibt möglich. Über
`tabslfeedback_screenshots_enabled` lässt sich die Funktion komplett
ausblenden, etwa wenn Screenshots datenschutzrechtlich vermieden werden sollen.

**KI-Aufbereitung mit Rückfallebene — oder ganz ohne KI** — der ursprüngliche
Wortlaut steht immer zusätzlich im Ticket. `tabslfeedback_ai_provider` wählt
zwischen OpenAI, Anthropic und „Ohne KI". Fehlt bei aktivem Anbieter der
API-Key oder antwortet der Dienst nicht, entsteht das Ticket trotzdem: mit
Rohtext, Screenshots, Kontext und einem Vermerk, dass keine Aufbereitung
stattfand. Bei „Ohne KI" zeigt das Formular stattdessen ein Betreff-Feld, dessen
Inhalt direkt zum Ticket-Titel wird. Der Melder bemerkt in keinem Fall einen
Unterschied im Ablauf.

**Konfigurierbarer Hinweistext** — `tabslfeedback_notice_text` zeigt einen
kurzen, frei editierbaren Satz unmittelbar vor dem Absenden-Knopf, etwa zur
Transparenz über die Übermittlung von Screenshots oder an einen KI-Dienst. Leer
lässt den Hinweis entfallen.

**Technischer Kontext am Ticket** — in einem eingeklappten Block: Herkunft der
Meldung, aufgerufene Seite, Referrer, Browser inkl. Version und Betriebssystem,
Fenster- und Bildschirmgröße, Shop-ID, Sprache, Währung, Theme mit Version,
aktive Module mit Versionen, Shop- und PHP-Version. Bei angemeldeten Kunden
optional Kundennummer, Name und E-Mail. Öffnet eine Shop-Seite das Formular
selbst, steht davor zusätzlich der **Bezug** (siehe unten). Seite und Referrer
werden zuvor
bereinigt: Sitzungskennungen und geheimnisverdächtige Parameter (`sid`,
`stoken`, `token`, `password`, `secret` …) werden durch `…` ersetzt; fachlich
nützliche Parameter wie Kategorie, Suchbegriff oder Seitenzahl bleiben erhalten.

### Formular ohne sichtbaren Button öffnen

Jede Shop-Seite lässt sich mit `?tabslFeedback=1` aufrufen — das Formular öffnet
sich sofort, auch bei ausgeblendetem Button. Gedacht für Testende und Redaktion:
Man verschickt den Link, ohne den Button für alle einzuschalten. `?tabslFeedback=0`
(ebenso `false`, `off`, `no`) verhindert nur das sofortige Öffnen; ein
eingeschalteter Button bleibt sichtbar.

> ⚠️ **Der Parameter ist bewusst nicht durch ein Geheimnis geschützt**, und sein
> Name steht in diesem quelloffenen Repository. Die Einstellung „Feedback-Button
> im Shop anzeigen" regelt deshalb nur die **Sichtbarkeit**, nicht die
> **Erreichbarkeit**: Sobald das Ticket-Ziel vollständig eingerichtet ist, nimmt
> der Shop Feedback entgegen — auch bei ausgeblendetem Button. Wer das nicht
> möchte, lässt die Zugangsdaten leer oder deaktiviert das Modul. Gegen automatisierten
> Missbrauch schützt allein `tabslTurnstile` (siehe unten).

### Formular aus dem Shop heraus öffnen

Wo eine Meldung entsteht, steht selten der Feedback-Button: im Bestellabschluss,
in einem Konfigurator, in einem geöffneten Dialog. Solche Stellen öffnen das
Formular selbst — ohne Seitenwechsel:

```js
window.tabslFeedback.open({ reference: 'Entwurf 4711' });
```

`reference` ist frei wählbar und erscheint im Ticket als eigene Zeile **Bezug**,
direkt über der Seite. Sie beantwortet, was die URL nicht beantworten kann:
worum es in der Meldung geht. Ohne Angabe öffnet der Dialog wie über den Button.

Die Schnittstelle steht bereit, sobald das Widget auf der Seite liegt — also
sobald „Feedback-Button im Shop anzeigen" eingeschaltet ist **oder** die Seite
mit `?tabslFeedback=1` aufgerufen wurde.

Soll der Shop den Dialog ausschließlich selbst öffnen, ohne dass irgendwo ein
Button erscheint, braucht es **beides**: die Einstellung „Feedback-Button im Shop
anzeigen" bleibt **eingeschaltet** und die Position steht auf `none`. Der
Schalter bindet das Widget ein, die Position entscheidet über den Knopf. Nur die
Position auf `none` zu stellen genügt nicht — bei ausgeschaltetem Schalter liegt
auf gewöhnlichen Seiten kein Widget und damit kein `window.tabslFeedback`.

> Der Wert reist als Angabe des Browsers und ist damit fälschbar wie jede andere
> Client-Angabe. Er wird auf 200 Zeichen gekürzt und maskiert ins Ticket
> geschrieben — als Hinweis gedacht, nicht als Beleg.

## Schnellstart

```bash
composer require tabsl/tabslfeedback
vendor/bin/oe-console oe:module:activate tabslFeedback
```

Alternativ manuell nach `source/modules/tabsl/tabslFeedback/` entpacken und im
Backend unter **Erweiterungen → Module** aktivieren.

### GitLab vorbereiten

1. **Projekt-ID** — steht auf der Startseite des Zielprojekts unter dem
   Projektnamen bzw. im Menü **⋮ → „Projekt-ID kopieren"**.
2. **Zugangs-Token** — im Zielprojekt unter **Settings → Access tokens** einen
   *Project Access Token* mit Scope **`api`** und Rolle *Reporter* (oder höher)
   anlegen. Einem persönlichen Token vorziehen: dessen Reichweite umfasst alle
   Projekte des Kontos, ein Project-Access-Token nur dieses eine.
3. **Benutzer-ID der zuständigen Person** — steht in deren GitLab-Profil. Bleibt
   das Feld leer, entstehen Issues ohne Zuweisung.

### weclapp vorbereiten

Nur nötig bei Ticket-Ziel `weclapp`.

1. **Adresse** — die Adresse des Mandanten, z. B. `https://firma.weclapp.com`.
   Nur `https` ist zulässig.
2. **API-Benutzer und Token** — einen eigenen weclapp-Benutzer anlegen, der nur
   Helpdesk-Rechte hat, und dessen Token unter **Benutzername → Meine
   Einstellungen → API-Token** kopieren. Der Token trägt sämtliche Rechte seines
   Benutzers, eine feinere Beschränkung bietet weclapp nicht. Tickets und
   Kommentare erscheinen unter diesem Benutzer. Wer einen neuen Token erzeugt,
   macht den bisherigen ungültig.
3. **Optional: Status, Priorität, Kanal, Kategorie, zuständige Person** — die
   numerische ID steht in der Adresszeile, wenn der Eintrag in weclapp geöffnet
   ist. Leer bleibt die Voreinstellung des Mandanten wirksam. Verlangt der
   Mandant eine Angabe, die fehlt, lehnt weclapp das Ticket ab; das Shop-Log
   nennt dann das fehlende Feld, etwa `ticketStatusId (notNull)`.

### Modul konfigurieren

Backend → **Erweiterungen → Module → tabslFeedback → Einstellungen**:

| Einstellung | Bedeutung | Standard |
| --- | --- | --- |
| `tabslfeedback_admin_enabled` | Feedback-Link im Backend-Header einblenden | aus |
| `tabslfeedback_frontend_enabled` | Widget im Shop einbinden — mit Button, außer die Position steht auf `none` (nur Sichtbarkeit, nicht Erreichbarkeit) | aus |
| `tabslfeedback_button_position` | `bottom-left`, `center`, `bottom-right` oder `none` (kein Button, siehe unten) | `bottom-right` |
| `tabslfeedback_ticket_target` | `gitlab` oder `weclapp` — wo das Ticket entsteht | `gitlab` |
| `tabslfeedback_gitlab_url` | Basis-Adresse der GitLab-Instanz inkl. Schema, ohne `/api/v4` — **Pflicht** bei Ziel `gitlab` | leer |
| `tabslfeedback_gitlab_project_id` | Numerische ID des Zielprojekts — **Pflicht** bei Ziel `gitlab` | leer |
| `tabslfeedback_gitlab_token` | Project-Access-Token mit Scope `api` — **Pflicht** bei Ziel `gitlab` | leer |
| `tabslfeedback_gitlab_assignee_id` | Benutzer-ID für die Zuweisung; leer = keine Zuweisung | leer |
| `tabslfeedback_weclapp_url` | Adresse des weclapp-Mandanten mit `https://` — **Pflicht** bei Ziel `weclapp` | leer |
| `tabslfeedback_weclapp_token` | API-Token eines weclapp-Benutzers — **Pflicht** bei Ziel `weclapp` | leer |
| `tabslfeedback_weclapp_ticket_status_id` | Status-ID neuer Tickets; leer = Voreinstellung | leer |
| `tabslfeedback_weclapp_ticket_priority_id` | Prioritäts-ID; leer = Voreinstellung | leer |
| `tabslfeedback_weclapp_ticket_channel_id` | Kanal-ID; leer = Standard-Kanal | leer |
| `tabslfeedback_weclapp_ticket_category_id` | Kategorie-ID; leer = keine Kategorie | leer |
| `tabslfeedback_weclapp_assignee_id` | Benutzer-ID für die Zuweisung; leer = keine Zuweisung | leer |
| `tabslfeedback_notice_text` | Kurzer Hinweistext vor dem Absenden-Knopf; leer = kein Hinweis | Hinweis auf Screenshot-Übermittlung |
| `tabslfeedback_ai_provider` | `none`, `openai` oder `anthropic` — bei `none` erscheint statt der Aufbereitung ein Betreff-Feld | `openai` |
| `tabslfeedback_openai_key` | OpenAI API-Key; nur bei Anbieter `openai`; leer = Ticket ohne Aufbereitung | leer |
| `tabslfeedback_openai_model` | Verwendetes OpenAI-Modell | `gpt-4o-mini` |
| `tabslfeedback_anthropic_key` | Anthropic API-Key; nur bei Anbieter `anthropic`; leer = Ticket ohne Aufbereitung | leer |
| `tabslfeedback_anthropic_model` | Verwendetes Anthropic-Modell | `claude-haiku-4-5-20251001` |
| `tabslfeedback_ticket_language` | `source` = Sprache der Meldung behalten, `en` = immer Englisch | `source` |
| `tabslfeedback_show_contact_fields` | Optionale Felder für Name und E-Mail anzeigen | aus |
| `tabslfeedback_send_customer_data` | Bei angemeldeten Kunden Kundennummer, Name und E-Mail ins Ticket übernehmen | aus |
| `tabslfeedback_screenshots_enabled` | Screenshot-Funktion im Formular anzeigen | an |

Fehlt eine Pflichtangabe des gewählten Ziels — bei GitLab eine der drei
Angaben oder das `http://` bzw. `https://` in der Adresse, bei weclapp Token oder
`https://` —, erscheint weder Header-Link noch Frontend-Button: ein Formular,
das kein Ticket erzeugen kann, wird gar nicht erst angeboten. Das
Modul enthält **keine** Vorbelegung für Adressen, Projekt-IDs oder Zugangsdaten.

> ⚠️ **Die GitLab-Adresse sollte auf `https://` lauten.** Der Zugangs-Token wird
> als HTTP-Header übertragen; über `http://` wandert er unverschlüsselt durchs
> Netz. Für interne Instanzen bleibt `http://` möglich, wird aber bei jeder
> Absendung im Shop-Log vermerkt.

### Grenzwerte

Fest eingebaut, bewusst nicht konfigurierbar: 5 Bilder je Meldung, 10 MB je Bild,
20 MB für alle Bilder zusammen, 5.000 Zeichen Freitext, 120 Zeichen für den
Betreff (nur bei Anbieter „Ohne KI" sichtbar), je 255 Zeichen für Name und
E-Mail, 200 Zeichen für den Bezug; Formate PNG, JPG, GIF, WebP.

Alle Grenzen werden serverseitig durchgesetzt. Damit mehrere Screenshots
durchkommen, sollten `post_max_size` und `memory_limit` der PHP-Installation
oberhalb von 20 MB liegen.

Bei Ticket-Ziel weclapp entsteht das Ticket vor den Screenshots. Für deren
Übertragung gilt ein festes Zeitbudget von 20 Sekunden; nicht mehr übertragene
Bilder vermerkt ein interner Kommentar am Ticket. Der Timeout des Webservers
(etwa `fastcgi_read_timeout` bei nginx, Standard 60 Sekunden) sollte bei
weclapp mindestens 90 Sekunden betragen. Sonst kann der Melder eine
Fehlermeldung sehen, obwohl das Ticket bereits angelegt ist, und durch
erneutes Absenden ein zweites Ticket erzeugen.

## Welche Daten gehen an wen

Grundlage für die Datenschutzerklärung des einsetzenden Shops. Übermittelt wird
ausschließlich beim Absenden einer Meldung.

**An OpenAI (`api.openai.com`) oder Anthropic (`api.anthropic.com`)** — je nach
`tabslfeedback_ai_provider` ausschließlich der Freitext der Meldung, an genau
einen der beiden Dienste. Nicht: Screenshots, Name, E-Mail, Kundendaten,
Umgebungsdaten, IP-Adresse, Shop-Adresse. Schreibt ein Melder personenbezogene
Angaben in den Freitext, werden diese mit übertragen — darauf hat das Modul
keinen Einfluss. Steht der Anbieter auf „Ohne KI" oder fehlt der API-Key,
findet **keine** Übermittlung statt.

**An die konfigurierte GitLab-Instanz bzw. den weclapp-Mandanten** — je nach
`tabslfeedback_ticket_target` an genau eines der beiden Ziele: Freitext, aufbereiteter Titel und
Beschreibung, alle Screenshots, optional Name und E-Mail sowie der oben
beschriebene technische Kontext; bei einem vom Shop selbst geöffneten Dialog
zusätzlich der übergebene Bezug. Kundendaten **nur** bei aktivem
`tabslfeedback_send_customer_data`. Nicht: IP-Adresse, Warenkorb- und
Bestellkontext, Browser-Konsolenmeldungen. Bei einer eigenen GitLab-Installation
verlassen die Daten die eigene Infrastruktur nicht; bei weclapp liegen sie beim
Mandanten in der weclapp-Cloud, Screenshots als Dokumente am Ticket.

**An Cloudflare (`challenges.cloudflare.com`)** — nur bei aktivem
`tabslTurnstile`: der Turnstile-Token und die von Cloudflare selbst erhobenen
Browser-Signale. Die IP-Adresse des Melders wird ausdrücklich **nicht**
weitergegeben; das optionale Feld `remoteip` bleibt leer.

**Nirgendwohin** — das Modul legt **keine** eigene Datenbanktabelle an und
speichert Meldungen nirgends im Shop. Kein Zwischenspeicher, keine Wiedervorlage:
Ist das Ticket-Ziel nicht erreichbar, ist die Meldung verloren, und der Melder erhält eine
Fehlermeldung statt einer falschen Bestätigung.

### Ins Shop-Log

Damit ausbleibende Tickets auffallen, hält das Modul technische Störungen im
Shop-Log fest, jeweils mit dem Präfix `[tabslFeedback]`:

| Ereignis | Stufe |
| --- | --- |
| Issue- bzw. Ticket-Anlage fehlgeschlagen (Meldung verloren) — bei weclapp inkl. HTTP-Status und abgelehnter Felder | `error` |
| weclapp antwortet bei der Ticket-Anlage nicht (Zeitüberschreitung) — Ticket kann trotzdem entstanden sein, vor erneutem Absenden in weclapp nachsehen | `error` |
| Screenshot-Upload fehlgeschlagen (Bild fehlt im Ticket) | `error` |
| Interner Kommentar am weclapp-Ticket fehlgeschlagen | `error` |
| KI-Aufbereitung fehlgeschlagen — inkl. HTTP-Status und Fehlermeldung | `error` |
| Absendung bei unvollständiger Konfiguration abgewiesen | `error` |
| Unerwarteter Fehler beim Absenden (einzeilig und gekürzt) | `error` |
| Zustand des Schutzmoduls nicht ermittelbar | `error` |
| Schutzmodul aktiv, liefert aber nicht die erwartete Schnittstelle | `error` |
| GitLab-Adresse nutzt `http` statt `https` | `warning` |
| Bot-Prüfung nicht bestanden (Normalbetrieb) | `info` |

**Nicht protokolliert** werden Meldungstext, Kontaktangaben, Kundendaten,
Screenshots und Zugangsdaten.

> **Hinweis:** OXID protokolliert standardmäßig erst ab Stufe `error`. Für
> `warning` und `info` muss `sLogLevel` in `source/config.inc.php` gesetzt
> werden: `$this->sLogLevel = 'warning';`
>
> Entsteht ein Ticket ohne Aufbereitung, steht der Grund als `error` im Log —
> etwa `openai request failed — http 401, api: invalid_api_key`. Abgelaufener
> Schlüssel, erschöpftes Kontingent und Zeitüberschreitung sind daran
> unterscheidbar.

## Missbrauchsschutz im Shop

Das Frontend-Formular ist öffentlich erreichbar. Jede Absendung erzeugt ein
GitLab-Issue bzw. weclapp-Ticket und einen kostenpflichtigen OpenAI-Aufruf — ein
Bot kann also sowohl das Projekt bzw. den Helpdesk fluten als auch Kosten
verursachen.

Das Modul unterstützt dafür [tabslTurnstile](https://github.com/tabsl/tabslTurnstile)
(Cloudflare Turnstile für OXID 6): Ist es installiert, aktiviert und mit
Site-Key konfiguriert, erscheint im Formular ein Turnstile-Widget und die
Absendung wird serverseitig geprüft — besteht die Prüfung nicht, entsteht
**weder** ein Ticket **noch** wird OpenAI angesprochen. Fehlt das Modul, läuft
tabslFeedback vollständig weiter; der Schutz entfällt ersatzlos, ohne Fehler und
ohne Installationsaufforderung. Das Backend-Formular ist von der Prüfung
ausgenommen, da bereits durch die Anmeldung geschützt.

> ⚠️ **Der öffentliche Betrieb des Frontend-Formulars ohne Schutzmodul erfolgt auf
> eigenes Risiko.** Wer keinen Bot-Schutz einsetzen möchte, sollte das
> Frontend-Formular deaktiviert lassen und nur das Backend-Formular nutzen.

Es gibt bewusst kein eigenes Rate-Limiting: ohne Zwischenspeicher wäre es nur
über die Session abbildbar und damit gegen Bots wirkungslos.

## Kosten

**GitLab** — keine zusätzlichen Kosten. **weclapp** — keine Kosten über die
bestehende weclapp-Lizenz hinaus; der API-Benutzer belegt allerdings einen
Benutzer-Platz. **OpenAI oder Anthropic** — je Meldung
ein Aufruf mit dem Freitext (höchstens 5.000 Zeichen) und einer kurzen
Antwort; mit den voreingestellten Modellen (`gpt-4o-mini` bzw.
`claude-haiku-4-5-20251001`) liegen die Kosten pro Meldung bei Bruchteilen
eines Cents, Bilder werden nicht übermittelt. Bei Anbieter „Ohne KI" entfällt
dieser Posten vollständig. **Cloudflare Turnstile** — dauerhaft kostenlos.

## Was das Modul nicht tut

- Keine Feedback-Übersicht, kein Archiv, keine Wiedervorlage — GitLab bzw.
  weclapp ist die einzige Ablage
- Keine Hintergrundverarbeitung: das Ticket entsteht beim Absenden
- Keine Bildauswertung durch die KI
- Keine Labels, keine automatische Einstufung von Priorität oder Kategorie, keine
  Duplikaterkennung; bei weclapp lassen sich Priorität und Kategorie fest vorgeben
- Keine Rückmeldung an den Melder über den Bearbeitungsstand
- Genau ein Ziel je Shop: ein GitLab-Projekt oder ein weclapp-Mandant, nicht
  beide zugleich; keine Jira-, GitHub- oder E-Mail-Ziele
- Keine Verknüpfung des Melders mit einem weclapp-Kunden oder -Kontakt

## Kompatibilität

OXID eShop 6.x, PHP 7.4 und PHP 8.x. Getestet mit den Themes `wave` und `ps`;
das Frontend-Widget bindet sich an den Block `base_js` und setzt weder jQuery
noch Bootstrap voraus.

## Frontend-Assets

Ausgeliefert werden `out/src/js/tabslfeedback.min.js` und
`out/src/css/tabslfeedback.min.css`. Die lesbaren Quelldateien liegen unter
demselben Namen ohne `.min` daneben und sind die **einzige** Stelle, an der
Änderungen vorgenommen werden — die minifizierten Fassungen entstehen daraus:

```bash
make minify   # erzeugt die .min-Dateien neu
make verify   # prüft, ob die .min-Dateien zum Quellstand passen
```

Beides braucht Node. Die Werkzeuge (`terser`, `clean-css-cli`) sind in
`package.json` auf **exakte Versionen** festgelegt und werden beim ersten Lauf
per `npm ci` installiert. Das ist kein Zufall: Eine andere terser-Version
erzeugt aus derselben Quelle ein anderes Ergebnis, und da die minifizierten
Dateien im Repository liegen, entstünde sonst bei jedem Beitragenden ein
Komplett-Diff auf unveränderter Quelle.

`make minify` bricht ab, wenn ein Werkzeug einen Fehler meldet oder keine bzw.
eine leere Ausgabedatei entsteht; das erzeugte JavaScript wird zusätzlich mit
`node --check` geprüft. Warnungen von `clean-css` werden ausgegeben, brechen den
Lauf aber nicht ab — sie sind kein verlässliches Qualitätssignal: eine leere
Property löst eine Warnung aus, obwohl die Ausgabe in Ordnung ist, während eine
verworfene leere Regel stillschweigend passiert.

`make verify` vergleicht **den Inhalt**: Es erzeugt die Assets in ein
temporäres Verzeichnis neu und prüft sie byteweise gegen die eingecheckten
Dateien. Ein Zeitstempel-Vergleich wäre wertlos, weil Git keine mtimes
wiederherstellt — nach einem Clone liegen Quelle und `.min` in derselben
Sekunde. So findet `verify` auch eine von Hand bearbeitete `.min`-Datei und
eignet sich damit sowohl für einen Pre-Commit-Hook als auch für CI.

> ⚠️ **Wer nur die Quelldatei ändert, ändert nichts am Shop.** Eingebunden wird
> ausschließlich die `.min`-Fassung; ohne `make minify` läuft weiterhin der alte
> Stand.

## Support

Open Source, ohne Anspruch auf Support oder Reaktionszeiten. Fehlerberichte und
Verbesserungsvorschläge sind über die GitHub-Issues willkommen; eine Bearbeitung
erfolgt nach Möglichkeit.

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md).

## License

GNU General Public License v3.0 — siehe [LICENSE](LICENSE).

## Copyright

Tobias Merkl | <https://oxid-module.eu>
