# Changelog

Alle wesentlichen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [1.1.0] - 2026-08-17

### Added

- Formular aus dem Shop heraus öffnen: `window.tabslFeedback.open()` zeigt den Dialog ohne Seitenwechsel
- Bezug am Ticket: `open({reference: '…'})` nennt den Gegenstand der Meldung, z. B. einen Entwurf oder eine Bestellung
- Position „Kein Button": Das Formular ist eingebunden, aber nur dort erreichbar, wo der Shop es selbst öffnet

### Fixed

- Skript und Stylesheet tragen einen Zeitstempel, damit wiederkehrende Besucher nach einem Update nicht auf einem alten Stand hängen bleiben

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

[Unreleased]: https://github.com/tabsl/tabslFeedback/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/tabsl/tabslFeedback/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/tabsl/tabslFeedback/releases/tag/v1.0.0
