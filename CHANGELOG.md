# Changelog

Alle wesentlichen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [1.3.0] - 2026-09-24

### Added

- weclapp als zweites Ticket-Ziel neben GitLab: `tabslfeedback_ticket_target` wählt, wo aus einer Meldung ein Ticket entsteht. Das weclapp-Ticket enthält KI-Aufbereitung, Originalmeldung, Kontakt und technischen Kontext; Screenshots hängen als Dokumente am Ticket
- Status, Priorität, Kanal, Kategorie und zuständige Person des weclapp-Tickets sind über ihre IDs vorgebbar; Priorität und Kanal sind in weclapp Pflichtfelder und sollten gesetzt werden
- Jira Cloud als drittes Ticket-Ziel: Vorgang mit KI-Aufbereitung, Originalmeldung, Kontakt und technischem Kontext im Atlassian Document Format, Screenshots als Anhänge. Anmeldung per E-Mail und API-Token, auch für Atlassian-Service-Accounts über die Gateway-Adresse

### Changed

- Ticket-Ziele laufen intern über eine gemeinsame Schnittstelle
- GitLab: Originalmeldung und KI-Beschreibung stehen im Issue als Codeblock statt als Zitat bzw. Fließtext

### Security

- GitLab: Über das öffentliche Formular ließen sich Links, Bilder, HTML und @-Erwähnungen ins Issue schreiben, über eine Anweisung an die KI zusätzlich Quick Actions wie `/assign` oder `/close`. Freitext und KI-Text werden jetzt als Codeblock gesetzt und von GitLab nicht mehr ausgewertet

## [1.2.0] - 2026-08-25

### Added

- Modul auch ganz ohne KI-Aufbereitung nutzbar: `tabslfeedback_ai_provider` schaltet zwischen OpenAI, Anthropic und „Ohne KI" um
- Anthropic (Claude) als zweiter KI-Anbieter neben OpenAI
- Betreff-Feld im Formular, sichtbar sobald „Ohne KI" gewählt ist — sein Inhalt wird direkt zum Ticket-Titel
- Screenshots über `tabslfeedback_screenshots_enabled` abschaltbar
- Konfigurierbarer Hinweistext (`tabslfeedback_notice_text`) vor dem Absenden-Knopf, ersetzt den bisher fest an die KI-Aufbereitung gekoppelten Hinweis

## [1.1.0] - 2026-08-18

### Added

- Formular aus dem Shop heraus öffnen: `window.tabslFeedback.open()` zeigt den Dialog ohne Seitenwechsel
- Bezug am Ticket: `open({reference: '…'})` nennt den Gegenstand der Meldung, z. B. einen Entwurf oder eine Bestellung
- Position „Kein Button": Das Formular ist eingebunden, aber nur dort erreichbar, wo der Shop es selbst öffnet

### Changed

- Shop und Backend laden Skript und Stylesheet in verkleinerter Fassung — rund 58 % weniger JavaScript und 36 % weniger CSS je Seitenaufruf

### Fixed

- Skript und Stylesheet tragen einen Zeitstempel, damit wiederkehrende Besucher nach einem Update nicht auf einem alten Stand hängen bleiben
- Nach dem Schließen des Dialogs steht der Fokus wieder auf dem Element, das ihn geöffnet hat
- Ein Bezug aus reinem Leerraum erzeugt keine leere Zeile mehr im Ticket

## [1.0.0] - 2026-07-25

### Added

- Feedback-Formular für OXID eShop 6, im Backend und im Shop getrennt zuschaltbar
- Screenshots aus der Zwischenablage einfügen, mit Vorschau und einzeln wieder entfernbar
- Titel und Beschreibung vom KI-Dienst aufbereitet, die Originalmeldung bleibt am Ticket
- Ticket entsteht auch ohne KI-Aufbereitung, mit Rohtext, Screenshots und Hinweis
- Anlage des GitLab-Issues inklusive Screenshot-Upload und optionaler Zuweisung
- Umgebungsdaten am Ticket: Herkunft, Seite, Browser, Shop, Theme, aktive Module, Versionen
- Formular per Adresszusatz öffnen, ohne den Button für alle Besucher einzuschalten
- Position des Buttons im Shop wählbar: unten links, unten mittig oder unten rechts
- Optionaler Bot-Schutz über tabslTurnstile, ohne feste Abhängigkeit
- Ticket-Sprache wahlweise wie die Meldung oder einheitlich auf Englisch
- Zwölf Moduleinstellungen ohne projektspezifische Vorbelegung
- Technische Störungen erscheinen im Shop-Log, ohne Meldungstext und Zugangsdaten

### Security

- Sitzungskennungen und geheimnisverdächtige Parameter werden aus Seite und Referrer entfernt
- Ohne vollständige GitLab-Angaben nimmt der Shop kein Feedback entgegen

[Unreleased]: https://github.com/tabsl/tabslFeedback/compare/1.3.0...HEAD
[1.3.0]: https://github.com/tabsl/tabslFeedback/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/tabsl/tabslFeedback/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/tabsl/tabslFeedback/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/tabsl/tabslFeedback/releases/tag/1.0.0
