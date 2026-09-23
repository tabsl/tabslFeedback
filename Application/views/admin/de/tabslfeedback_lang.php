<?php
/**
 * Backend-Sprachdatei — Deutsch.
 *
 * Enthält die Beschriftungen der Modul-Einstellungen und — über die gemeinsame
 * Datei — dieselben Oberflächentexte, die auch das Frontend nutzt.
 *
 * @package tabslFeedback
 **/

$sLangName = 'Deutsch';

$aLang = array_merge(
    [
        'charset' => 'UTF-8',

        // Navigation
        'TABSLFEEDBACK_ADMIN_LINK' => 'Feedback',
        'TABSLFEEDBACK_ADMIN_UNAVAILABLE' => 'Das Feedback-Formular ist nicht verfügbar. Prüfen Sie unter Erweiterungen → Module → tabslFeedback, ob das Backend-Formular aktiviert und das gewählte Ticket-Ziel vollständig eingerichtet ist: bei GitLab Adresse, Projekt-ID und Token, bei weclapp https-Adresse und API-Token.',

        // Einstellungsgruppen
        'SHOP_MODULE_GROUP_tabslfeedback_main' => 'Grundeinstellungen',
        'SHOP_MODULE_GROUP_tabslfeedback_gitlab' => 'GitLab',
        'SHOP_MODULE_GROUP_tabslfeedback_weclapp' => 'weclapp',
        'SHOP_MODULE_GROUP_tabslfeedback_openai' => 'KI-Aufbereitung',
        'SHOP_MODULE_GROUP_tabslfeedback_privacy' => 'Datenschutz',

        // Grundeinstellungen
        'SHOP_MODULE_tabslfeedback_admin_enabled' => 'Backend-Formular aktiv',
        'HELP_SHOP_MODULE_tabslfeedback_admin_enabled' => 'Blendet im Backend-Header oben links einen Feedback-Link ein. Solange das gewählte Ticket-Ziel nicht vollständig eingerichtet ist, erscheint der Link nicht.',

        'SHOP_MODULE_tabslfeedback_frontend_enabled' => 'Feedback-Button im Shop anzeigen',
        'HELP_SHOP_MODULE_tabslfeedback_frontend_enabled' => 'Blendet im Shop auf allen Seiten einen Feedback-Button ein. Ist die Einstellung aus, bleibt das Formular über den Aufruf einer beliebigen Shop-Seite mit ?tabslFeedback=1 erreichbar — praktisch für Testende, ohne den Button für alle Besucher zu zeigen. Die Einstellung steuert also die Sichtbarkeit, nicht die Erreichbarkeit: Sobald das gewählte Ticket-Ziel vollständig eingerichtet ist, nimmt der Shop Feedback entgegen. Öffentlicher Betrieb ohne das Modul tabslTurnstile erfolgt auf eigenes Risiko.',

        'SHOP_MODULE_tabslfeedback_button_position' => 'Position des Buttons',
        'HELP_SHOP_MODULE_tabslfeedback_button_position' => 'Wo der Feedback-Button im Shop erscheint. „Kein Button" zeigt keinen Knopf — dann öffnet das Formular nur dort, wo eine Seite es selbst über window.tabslFeedback.open() aufruft. Eingebunden wird es dafür weiterhin nur, solange „Feedback-Button im Shop anzeigen" eingeschaltet ist.',
        'SHOP_MODULE_tabslfeedback_button_position_bottom-left' => 'Unten links',
        'SHOP_MODULE_tabslfeedback_button_position_bottom-right' => 'Unten rechts',
        'SHOP_MODULE_tabslfeedback_button_position_center' => 'Unten mittig',
        'SHOP_MODULE_tabslfeedback_button_position_none' => 'Kein Button',

        'SHOP_MODULE_tabslfeedback_notice_text' => 'Hinweistext im Formular',
        'HELP_SHOP_MODULE_tabslfeedback_notice_text' => 'Kurzer Text unmittelbar vor dem Absenden-Knopf, im Backend- wie im Frontend-Formular. Leer lässt den Hinweis entfallen. Der Standardtext weist auf die Übermittlung von Screenshots hin — anzupassen, sobald sich Umfang oder Empfänger der Übermittlung ändern, etwa bei aktiver KI-Aufbereitung.',

        'SHOP_MODULE_tabslfeedback_ticket_target' => 'Ticket-Ziel',
        'HELP_SHOP_MODULE_tabslfeedback_ticket_target' => 'Wo aus einer Meldung ein Ticket entsteht: als Issue in GitLab oder als Helpdesk-Ticket in weclapp. Es gelten jeweils nur die Angaben der passenden Einstellungsgruppe.',
        'SHOP_MODULE_tabslfeedback_ticket_target_gitlab' => 'GitLab',
        'SHOP_MODULE_tabslfeedback_ticket_target_weclapp' => 'weclapp',

        // GitLab
        'SHOP_MODULE_tabslfeedback_gitlab_url' => 'GitLab-Adresse',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_url' => 'Basis-Adresse der GitLab-Instanz inklusive Schema, z. B. https://gitlab.com — ohne /api/v4. Bitte https verwenden: über http wird der Zugangs-Token unverschlüsselt übertragen. Fehlt das Schema, gilt die Konfiguration als unvollständig und es erscheint kein Feedback-Einstieg. Pflichtangabe.',

        'SHOP_MODULE_tabslfeedback_gitlab_project_id' => 'Projekt-ID',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_project_id' => 'Numerische ID des Zielprojekts. In GitLab auf der Projekt-Startseite im Menü über "Projekt-ID kopieren" zu finden. Pflichtangabe.',

        'SHOP_MODULE_tabslfeedback_gitlab_token' => 'Zugangs-Token',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_token' => 'Project-Access-Token mit dem Recht "api", beschränkt auf das Zielprojekt. Einem persönlichen Zugangstoken vorzuziehen, weil dessen Reichweite deutlich größer wäre. Pflichtangabe.',

        'SHOP_MODULE_tabslfeedback_gitlab_assignee_id' => 'Zuständige Person (Benutzer-ID)',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_assignee_id' => 'Numerische GitLab-Benutzer-ID, der neue Tickets zugewiesen werden. Steht im GitLab-Profil der Person. Bleibt das Feld leer, wird niemand zugewiesen.',

        // weclapp
        'SHOP_MODULE_tabslfeedback_weclapp_url' => 'weclapp-Adresse',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_url' => 'Adresse des weclapp-Mandanten, z. B. https://firma.weclapp.com. Ein angehängtes /webapp/api/v2 wird ignoriert. Nur https ist zulässig; ohne https gilt die Konfiguration als unvollständig und es erscheint kein Feedback-Einstieg. Pflichtangabe bei Ticket-Ziel weclapp.',

        'SHOP_MODULE_tabslfeedback_weclapp_token' => 'API-Token',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_token' => 'API-Token eines weclapp-Benutzers, zu finden unter Benutzername → Meine Einstellungen → API-Token. Der Token trägt alle Rechte seines Benutzers, deshalb einen eigenen Benutzer anlegen, der nur Helpdesk-Rechte hat. Tickets erscheinen unter diesem Benutzer. Ein neu erzeugter Token macht den bisherigen ungültig. Pflichtangabe bei Ticket-Ziel weclapp.',

        'SHOP_MODULE_tabslfeedback_weclapp_ticket_status_id' => 'Ticket-Status (ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_ticket_status_id' => 'Numerische ID des Status, mit dem neue Tickets entstehen. Steht in weclapp in der Adresszeile, wenn der Status unter den Helpdesk-Einstellungen geöffnet ist. Leer = Voreinstellung des Mandanten.',

        'SHOP_MODULE_tabslfeedback_weclapp_ticket_priority_id' => 'Priorität (ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_ticket_priority_id' => 'Numerische ID der Priorität für neue Tickets, zu finden wie beim Status. Leer = Voreinstellung des Mandanten.',

        'SHOP_MODULE_tabslfeedback_weclapp_ticket_channel_id' => 'Kanal (ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_ticket_channel_id' => 'Numerische ID des Ticket-Kanals, etwa eines eigens angelegten Kanals "Shop-Feedback". Leer = Standard-Kanal des Mandanten.',

        'SHOP_MODULE_tabslfeedback_weclapp_ticket_category_id' => 'Kategorie (ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_ticket_category_id' => 'Numerische ID der Ticket-Kategorie. Leer = keine Kategorie.',

        'SHOP_MODULE_tabslfeedback_weclapp_assignee_id' => 'Zuständige Person (Benutzer-ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_assignee_id' => 'Numerische weclapp-Benutzer-ID, der neue Tickets zugewiesen werden. Leer = keine Zuweisung durch das Modul; Zuweisungsregeln in weclapp greifen weiterhin.',

        // KI-Aufbereitung
        'SHOP_MODULE_tabslfeedback_ai_provider' => 'KI-Anbieter',
        'HELP_SHOP_MODULE_tabslfeedback_ai_provider' => 'Ob und über welchen Dienst der Freitext zu Titel und Beschreibung aufbereitet wird. „Ohne KI" verzichtet vollständig auf eine externe Übermittlung — im Formular erscheint dafür ein Betreff-Feld, dessen Inhalt direkt als Ticket-Titel dient. Bei OpenAI oder Anthropic bleibt zusätzlich der jeweilige API-Key erforderlich; fehlt er, entsteht das Ticket ebenfalls ohne Aufbereitung.',
        'SHOP_MODULE_tabslfeedback_ai_provider_none' => 'Ohne KI',
        'SHOP_MODULE_tabslfeedback_ai_provider_openai' => 'OpenAI',
        'SHOP_MODULE_tabslfeedback_ai_provider_anthropic' => 'Anthropic (Claude)',

        'SHOP_MODULE_tabslfeedback_openai_key' => 'OpenAI API-Key',
        'HELP_SHOP_MODULE_tabslfeedback_openai_key' => 'Nur wirksam, wenn KI-Anbieter auf OpenAI steht. Ohne Key entsteht das Ticket trotzdem — dann mit dem unveränderten Meldungstext statt einer Aufbereitung. Bilder werden nie an OpenAI übermittelt.',

        'SHOP_MODULE_tabslfeedback_openai_model' => 'OpenAI-Modell',
        'HELP_SHOP_MODULE_tabslfeedback_openai_model' => 'Das für die Aufbereitung verwendete Modell. Der Standardwert gpt-4o-mini genügt für diese Aufgabe; anzupassen nur, wenn das Modell abgekündigt wird.',

        'SHOP_MODULE_tabslfeedback_anthropic_key' => 'Anthropic API-Key',
        'HELP_SHOP_MODULE_tabslfeedback_anthropic_key' => 'Nur wirksam, wenn KI-Anbieter auf Anthropic steht. Ohne Key entsteht das Ticket trotzdem — dann mit dem unveränderten Meldungstext statt einer Aufbereitung. Bilder werden nie an Anthropic übermittelt.',

        'SHOP_MODULE_tabslfeedback_anthropic_model' => 'Anthropic-Modell',
        'HELP_SHOP_MODULE_tabslfeedback_anthropic_model' => 'Das für die Aufbereitung verwendete Modell. Der Standardwert claude-haiku-4-5-20251001 genügt für diese Aufgabe; anzupassen nur, wenn das Modell abgekündigt wird.',

        'SHOP_MODULE_tabslfeedback_ticket_language' => 'Sprache des Tickets',
        'HELP_SHOP_MODULE_tabslfeedback_ticket_language' => 'Ob Titel und Beschreibung in der Sprache der Meldung bleiben oder einheitlich auf Englisch erzeugt werden. Gilt auch für die festen Ticket-Überschriften ohne KI-Aufbereitung.',
        'SHOP_MODULE_tabslfeedback_ticket_language_source' => 'Sprache der Meldung beibehalten',
        'SHOP_MODULE_tabslfeedback_ticket_language_en' => 'Immer Englisch',

        // Datenschutz
        'SHOP_MODULE_tabslfeedback_show_contact_fields' => 'Name- und E-Mail-Feld anzeigen',
        'HELP_SHOP_MODULE_tabslfeedback_show_contact_fields' => 'Blendet zwei optionale Felder für Rückfragen ein. Das Formular bleibt auch ohne Eingabe absendbar.',

        'SHOP_MODULE_tabslfeedback_send_customer_data' => 'Kundendaten übermitteln',
        'HELP_SHOP_MODULE_tabslfeedback_send_customer_data' => 'Ergänzt bei angemeldeten Kunden Kundennummer, Name und E-Mail im Ticket. Ist die Einstellung aus, werden keine Kundendaten übermittelt.',

        'SHOP_MODULE_tabslfeedback_screenshots_enabled' => 'Screenshots erlauben',
        'HELP_SHOP_MODULE_tabslfeedback_screenshots_enabled' => 'Blendet die Screenshot-Funktion im Formular ein. Ist die Einstellung aus, entfällt der Bereich vollständig — auch technisch mitgesendete Bilder werden dann verworfen.',
    ],
    require __DIR__ . '/../../../messages/de.php'
);
