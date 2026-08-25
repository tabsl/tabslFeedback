<?php
/**
 * Oberflächentexte des Feedback-Formulars — Deutsch.
 *
 * Diese Texte werden sowohl im Shop-Frontend als auch im Backend gebraucht.
 * OXID liest dafür getrennte Sprachdateien; beide binden diese Datei ein, damit
 * eine Formulierung nicht an einer Stelle geändert und an der anderen vergessen
 * werden kann.
 *
 * @package tabslFeedback
 **/

return [
    // Bedienelemente
    'TABSLFEEDBACK_TRIGGER' => 'Feedback',
    'TABSLFEEDBACK_TITLE' => 'Feedback senden',
    'TABSLFEEDBACK_MESSAGE_LABEL' => 'Nachricht',
    'TABSLFEEDBACK_SUBJECT_LABEL' => 'Betreff',
    'TABSLFEEDBACK_SCREENSHOTS_LABEL' => 'Screenshots',
    'TABSLFEEDBACK_SCREENSHOTS_HINT' => 'Ein Bild aus der Zwischenablage mit Strg+V bzw. ⌘+V einfügen. Mehrere Bilder sind möglich.',
    'TABSLFEEDBACK_NAME_LABEL' => 'Name (optional)',
    'TABSLFEEDBACK_EMAIL_LABEL' => 'E-Mail (optional)',
    'TABSLFEEDBACK_SUBMIT' => 'Absenden',
    'TABSLFEEDBACK_CANCEL' => 'Abbrechen',
    'TABSLFEEDBACK_CLOSE' => 'Schließen',
    'TABSLFEEDBACK_SENDING' => 'Wird gesendet …',
    'TABSLFEEDBACK_REMOVE_IMAGE' => 'Bild entfernen',

    // Rückmeldungen
    'TABSLFEEDBACK_THANKS' => 'Vielen Dank für Ihr Feedback.',
    'TABSLFEEDBACK_ERROR_GENERIC' => 'Ihr Feedback konnte gerade nicht übermittelt werden. Bitte versuchen Sie es später noch einmal.',
    'TABSLFEEDBACK_ERROR_NETWORK' => 'Die Verbindung wurde unterbrochen. Bitte versuchen Sie es noch einmal.',
    'TABSLFEEDBACK_ERROR_BOT_CHECK' => 'Die Sicherheitsprüfung wurde nicht bestanden. Bitte versuchen Sie es noch einmal.',

    // Eingabeprüfung
    'TABSLFEEDBACK_ERROR_MESSAGE_REQUIRED' => 'Bitte geben Sie eine Nachricht ein.',
    'TABSLFEEDBACK_ERROR_MESSAGE_TOO_LONG' => 'Die Nachricht ist zu lang (höchstens %d Zeichen).',
    'TABSLFEEDBACK_ERROR_EMAIL_INVALID' => 'Bitte prüfen Sie die eingegebene E-Mail-Adresse.',
    'TABSLFEEDBACK_ERROR_CONTACT_TOO_LONG' => 'Name und E-Mail dürfen höchstens %d Zeichen lang sein.',
    'TABSLFEEDBACK_ERROR_SUBJECT_TOO_LONG' => 'Der Betreff ist zu lang (höchstens %d Zeichen).',
    'TABSLFEEDBACK_ERROR_TOO_MANY_IMAGES' => 'Es sind höchstens %d Bilder möglich.',
    'TABSLFEEDBACK_ERROR_IMAGE_TOO_LARGE' => 'Ein Bild ist zu groß (höchstens %d MB je Bild).',
    'TABSLFEEDBACK_ERROR_IMAGES_TOO_LARGE' => 'Die Bilder sind zusammen zu groß (höchstens %d MB).',
    'TABSLFEEDBACK_ERROR_IMAGE_TYPE' => 'Dieses Bildformat wird nicht unterstützt. Möglich sind PNG, JPG, GIF und WebP.',
];
