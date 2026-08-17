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
        'TABSLFEEDBACK_ADMIN_UNAVAILABLE' => 'Das Feedback-Formular ist nicht verfügbar. Prüfen Sie unter Erweiterungen → Module → tabslFeedback, ob das Backend-Formular aktiviert und GitLab-Adresse, Projekt-ID sowie Token hinterlegt sind.',

        // Einstellungsgruppen
        'SHOP_MODULE_GROUP_tabslfeedback_main' => 'Grundeinstellungen',
        'SHOP_MODULE_GROUP_tabslfeedback_gitlab' => 'GitLab',
        'SHOP_MODULE_GROUP_tabslfeedback_openai' => 'KI-Aufbereitung',
        'SHOP_MODULE_GROUP_tabslfeedback_privacy' => 'Datenschutz',

        // Grundeinstellungen
        'SHOP_MODULE_tabslfeedback_admin_enabled' => 'Backend-Formular aktiv',
        'HELP_SHOP_MODULE_tabslfeedback_admin_enabled' => 'Blendet im Backend-Header oben links einen Feedback-Link ein. Ohne vollständige GitLab-Angaben erscheint der Link nicht.',

        'SHOP_MODULE_tabslfeedback_frontend_enabled' => 'Feedback-Button im Shop anzeigen',
        'HELP_SHOP_MODULE_tabslfeedback_frontend_enabled' => 'Blendet im Shop auf allen Seiten einen Feedback-Button ein. Ist die Einstellung aus, bleibt das Formular über den Aufruf einer beliebigen Shop-Seite mit ?tabslFeedback=1 erreichbar — praktisch für Testende, ohne den Button für alle Besucher zu zeigen. Die Einstellung steuert also die Sichtbarkeit, nicht die Erreichbarkeit: Sobald die GitLab-Angaben vollständig sind, nimmt der Shop Feedback entgegen. Öffentlicher Betrieb ohne das Modul tabslTurnstile erfolgt auf eigenes Risiko.',

        'SHOP_MODULE_tabslfeedback_button_position' => 'Position des Buttons',
        'HELP_SHOP_MODULE_tabslfeedback_button_position' => 'Wo der Feedback-Button im Shop erscheint. „Kein Button" zeigt keinen Knopf — dann öffnet das Formular nur dort, wo eine Seite es selbst über window.tabslFeedback.open() aufruft. Eingebunden wird es dafür weiterhin nur, solange „Feedback-Button im Shop anzeigen" eingeschaltet ist.',
        'SHOP_MODULE_tabslfeedback_button_position_bottom-left' => 'Unten links',
        'SHOP_MODULE_tabslfeedback_button_position_bottom-right' => 'Unten rechts',
        'SHOP_MODULE_tabslfeedback_button_position_center' => 'Unten mittig',
        'SHOP_MODULE_tabslfeedback_button_position_none' => 'Kein Button',

        // GitLab
        'SHOP_MODULE_tabslfeedback_gitlab_url' => 'GitLab-Adresse',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_url' => 'Basis-Adresse der GitLab-Instanz inklusive Schema, z. B. https://gitlab.com — ohne /api/v4. Bitte https verwenden: über http wird der Zugangs-Token unverschlüsselt übertragen. Fehlt das Schema, gilt die Konfiguration als unvollständig und es erscheint kein Feedback-Einstieg. Pflichtangabe.',

        'SHOP_MODULE_tabslfeedback_gitlab_project_id' => 'Projekt-ID',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_project_id' => 'Numerische ID des Zielprojekts. In GitLab auf der Projekt-Startseite im Menü über "Projekt-ID kopieren" zu finden. Pflichtangabe.',

        'SHOP_MODULE_tabslfeedback_gitlab_token' => 'Zugangs-Token',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_token' => 'Project-Access-Token mit dem Recht "api", beschränkt auf das Zielprojekt. Einem persönlichen Zugangstoken vorzuziehen, weil dessen Reichweite deutlich größer wäre. Pflichtangabe.',

        'SHOP_MODULE_tabslfeedback_gitlab_assignee_id' => 'Zuständige Person (Benutzer-ID)',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_assignee_id' => 'Numerische GitLab-Benutzer-ID, der neue Tickets zugewiesen werden. Steht im GitLab-Profil der Person. Bleibt das Feld leer, wird niemand zugewiesen.',

        // KI-Aufbereitung
        'SHOP_MODULE_tabslfeedback_openai_key' => 'OpenAI API-Key',
        'HELP_SHOP_MODULE_tabslfeedback_openai_key' => 'Ohne Key entsteht das Ticket trotzdem — dann mit dem unveränderten Meldungstext statt einer Aufbereitung. Bilder werden nie an OpenAI übermittelt.',

        'SHOP_MODULE_tabslfeedback_openai_model' => 'Modell',
        'HELP_SHOP_MODULE_tabslfeedback_openai_model' => 'Das für die Aufbereitung verwendete Modell. Der Standardwert gpt-4o-mini genügt für diese Aufgabe; anzupassen nur, wenn das Modell abgekündigt wird.',

        'SHOP_MODULE_tabslfeedback_ticket_language' => 'Sprache des Tickets',
        'HELP_SHOP_MODULE_tabslfeedback_ticket_language' => 'Ob Titel und Beschreibung in der Sprache der Meldung bleiben oder einheitlich auf Englisch erzeugt werden.',
        'SHOP_MODULE_tabslfeedback_ticket_language_source' => 'Sprache der Meldung beibehalten',
        'SHOP_MODULE_tabslfeedback_ticket_language_en' => 'Immer Englisch',

        // Datenschutz
        'SHOP_MODULE_tabslfeedback_show_contact_fields' => 'Name- und E-Mail-Feld anzeigen',
        'HELP_SHOP_MODULE_tabslfeedback_show_contact_fields' => 'Blendet zwei optionale Felder für Rückfragen ein. Das Formular bleibt auch ohne Eingabe absendbar.',

        'SHOP_MODULE_tabslfeedback_send_customer_data' => 'Kundendaten übermitteln',
        'HELP_SHOP_MODULE_tabslfeedback_send_customer_data' => 'Ergänzt bei angemeldeten Kunden Kundennummer, Name und E-Mail im Ticket. Ist die Einstellung aus, werden keine Kundendaten übermittelt.',
    ],
    require __DIR__ . '/../../../messages/de.php'
);
