<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Core;

use OxidEsales\Eshop\Core\Registry;

/**
 * Zentraler, typisierter Zugriff auf die Modul-Einstellungen.
 *
 * Der Bestand kennt drei Zugriffsvarianten (getConfigParam, getShopConfVar mit
 * Modul-Präfix, gekapselter Helper). Hier wird durchgängig getShopConfVar mit
 * 'module:tabslFeedback' verwendet — die eindeutige Variante, die auch dann
 * korrekt liest, wenn ein anderes Modul ein gleichnamiges Setting führt.
 *
 * Bewusst nicht eine private Zugriffsmethode je Service: Bei siebzehn Settings
 * und fünf Nutzern hätte das die Setting-Namen über mehrere Dateien verteilt;
 * sie liegen deshalb hier an einer Stelle.
 */
class ModuleSettings
{
    private const MODULE_ID = 'module:tabslFeedback';

    public const AI_PROVIDER_NONE = 'none';

    public const AI_PROVIDER_OPENAI = 'openai';

    public const AI_PROVIDER_ANTHROPIC = 'anthropic';

    public function isAdminFormEnabled(): bool
    {
        return (bool) $this->get('tabslfeedback_admin_enabled');
    }

    public function isFrontendFormEnabled(): bool
    {
        return (bool) $this->get('tabslfeedback_frontend_enabled');
    }

    /**
     * `none` bindet das Widget ohne sichtbaren Knopf ein — der Dialog geht dann
     * nur über `window.tabslFeedback.open()` auf, aufgerufen von der Stelle im
     * Shop, an der die Meldung entsteht.
     *
     * @return string bottom-left|bottom-right|center|none
     */
    public function getButtonPosition(): string
    {
        $position = (string) $this->get('tabslfeedback_button_position');

        return in_array($position, ['bottom-left', 'bottom-right', 'center', 'none'], true)
            ? $position
            : 'bottom-right';
    }

    public function getGitLabUrl(): string
    {
        return rtrim(trim((string) $this->get('tabslfeedback_gitlab_url')), '/');
    }

    public function getGitLabProjectId(): string
    {
        return trim((string) $this->get('tabslfeedback_gitlab_project_id'));
    }

    public function getGitLabToken(): string
    {
        return trim((string) $this->get('tabslfeedback_gitlab_token'));
    }

    public function getGitLabAssigneeId(): string
    {
        return trim((string) $this->get('tabslfeedback_gitlab_assignee_id'));
    }

    /**
     * @return string none|openai|anthropic
     */
    public function getAiProvider(): string
    {
        $provider = (string) $this->get('tabslfeedback_ai_provider');

        return in_array(
            $provider,
            [self::AI_PROVIDER_NONE, self::AI_PROVIDER_OPENAI, self::AI_PROVIDER_ANTHROPIC],
            true
        ) ? $provider : self::AI_PROVIDER_OPENAI;
    }

    /**
     * Bewusst am gewählten Anbieter festgemacht, nicht am Vorhandensein eines
     * Schlüssels: Ein Betreiber, der explizit „ohne KI" gewählt hat, soll das
     * Betreff-Feld zuverlässig sehen — unabhängig davon, ob zufällig noch ein
     * Schlüssel hinterlegt ist. Fehlt bei aktivem Anbieter der Schlüssel, greift
     * weiterhin die bestehende Rückfallebene (Ticket ohne Aufbereitung).
     */
    public function isAiEnabled(): bool
    {
        return $this->getAiProvider() !== self::AI_PROVIDER_NONE;
    }

    public function getOpenAiKey(): string
    {
        return trim((string) $this->get('tabslfeedback_openai_key'));
    }

    public function getOpenAiModel(): string
    {
        $model = trim((string) $this->get('tabslfeedback_openai_model'));

        return $model !== '' ? $model : 'gpt-4o-mini';
    }

    public function getAnthropicKey(): string
    {
        return trim((string) $this->get('tabslfeedback_anthropic_key'));
    }

    public function getAnthropicModel(): string
    {
        $model = trim((string) $this->get('tabslfeedback_anthropic_model'));

        return $model !== '' ? $model : 'claude-haiku-4-5-20251001';
    }

    /**
     * Einziges Bool-Setting mit Standardwert true (siehe metadata.php) — ein
     * reiner Datei-Deploy auf eine bereits aktive Installation trägt diesen
     * Default nicht automatisch in oxconfig ein, das übernimmt erst eine
     * erneute Modul-Aktivierung. Bis dahin liefert get() null; das darf nicht
     * als „ausgeschaltet" gelesen werden, sonst verlieren bestehende
     * Installationen die Screenshot-Funktion unbemerkt.
     */
    public function areScreenshotsEnabled(): bool
    {
        $value = $this->get('tabslfeedback_screenshots_enabled');

        return $value === null ? true : (bool) $value;
    }

    /**
     * Freier Hinweistext im Formular, unmittelbar vor dem Absenden-Knopf. Leer
     * lässt den Hinweis ganz entfallen.
     */
    public function getNoticeText(): string
    {
        return trim((string) $this->get('tabslfeedback_notice_text'));
    }

    /**
     * @return string source|en
     */
    public function getTicketLanguage(): string
    {
        return $this->get('tabslfeedback_ticket_language') === 'en' ? 'en' : 'source';
    }

    public function showContactFields(): bool
    {
        return (bool) $this->get('tabslfeedback_show_contact_fields');
    }

    public function sendCustomerData(): bool
    {
        return (bool) $this->get('tabslfeedback_send_customer_data');
    }

    /**
     * Die drei GitLab-Angaben sind Pflicht. Fehlt eine davon,
     * kann kein Issue entstehen — dann erscheint gar kein Feedback-Einstieg.
     *
     * Anbieter und Key der KI-Aufbereitung gehören bewusst NICHT dazu: ohne sie
     * entsteht das Issue lediglich ohne Aufbereitung, das ist ein zulässiger
     * Betriebszustand.
     */
    public function isConfigured(): bool
    {
        return $this->hasValidGitLabUrl()
            && $this->getGitLabProjectId() !== ''
            && $this->getGitLabToken() !== '';
    }

    /**
     * Die Adresse muss ein http(s)-Schema tragen.
     *
     * Ohne Schema baut cURL keine gültige Anfrage — die Absendung schlüge dann
     * bei jedem Versuch fehl, ohne dass der Betreiber die Ursache sähe. Als
     * unvollständige Konfiguration behandelt, erscheint stattdessen erst gar
     * kein Feedback-Einstieg.
     */
    public function hasValidGitLabUrl(): bool
    {
        $scheme = parse_url($this->getGitLabUrl(), PHP_URL_SCHEME);

        return $scheme === 'https' || $scheme === 'http';
    }

    /**
     * Über http wandert der Zugangs-Token unverschlüsselt durchs Netz. Für
     * interne Instanzen bleibt es erlaubt, wird aber protokolliert, damit es
     * nicht unbemerkt im Produktivbetrieb landet.
     */
    public function usesUnencryptedGitLabUrl(): bool
    {
        return parse_url($this->getGitLabUrl(), PHP_URL_SCHEME) === 'http';
    }

    /**
     * @return mixed
     */
    private function get(string $name)
    {
        return Registry::getConfig()->getShopConfVar($name, null, self::MODULE_ID);
    }
}
