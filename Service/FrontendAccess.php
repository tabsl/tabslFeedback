<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;

/**
 * Entscheidet, ob und wie das Feedback-Formular im Shop erscheint.
 *
 * Die Funktion ist verfügbar, sobald die GitLab-Angaben vollständig sind. Die
 * Einstellung „Feedback-Button im Shop anzeigen" steuert allein die Sichtbarkeit
 * des Buttons — nicht, ob Feedback entgegengenommen wird. Dadurch lässt sich das
 * Formular jederzeit über `?tabslFeedback=1` öffnen, auch wenn der Button
 * ausgeblendet ist, ohne dass dafür ein Sitzungsvermerk nötig wäre.
 *
 * ⚠️ Damit ist der öffentliche Endpunkt immer erreichbar, sobald das Modul
 * konfiguriert ist. Das Ausblenden des Buttons ist eine Frage der Darstellung,
 * keine Zugangssperre. Missbrauchsschutz leistet allein tabslTurnstile
 * (requirements.md C2) — für den öffentlichen Betrieb ist es dringend empfohlen.
 */
class FrontendAccess
{
    public const REQUEST_PARAM = 'tabslFeedback';

    /** @var ModuleSettings */
    private $settings;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?? new ModuleSettings();
    }

    /**
     * Darf eine Absendung entgegengenommen werden? Einzige Bedingung ist eine
     * vollständige GitLab-Konfiguration — ohne sie könnte kein Ticket entstehen.
     */
    public function isSubmitAllowed(): bool
    {
        return $this->settings->isConfigured();
    }

    /**
     * Soll das Widget in die Seite eingebunden werden? Entweder weil der Button
     * generell sichtbar ist oder weil diese Seite gezielt mit dem Parameter
     * aufgerufen wurde.
     */
    public function shouldRenderWidget(): bool
    {
        if (!$this->settings->isConfigured()) {
            return false;
        }

        return $this->settings->isFrontendFormEnabled() || $this->isOpenRequested();
    }

    /**
     * Wurde die Seite mit `?tabslFeedback=1` aufgerufen? Dann öffnet sich der
     * Dialog sofort — und der Button erscheint, auch wenn er sonst ausgeblendet ist.
     */
    public function isOpenRequested(): bool
    {
        $value = Registry::getRequest()->getRequestParameter(self::REQUEST_PARAM);

        if ($value === null || $value === '') {
            return false;
        }

        return !in_array(strtolower((string) $value), ['0', 'false', 'off', 'no'], true);
    }
}
