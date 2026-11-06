<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;

/**
 * Entscheidet, ob und wie das Feedback-Formular im Shop erscheint.
 *
 * Die Funktion ist verfügbar, sobald das gewählte Ticket-Ziel eingerichtet ist. Die
 * Einstellung „Feedback-Button im Shop anzeigen" steuert allein die Sichtbarkeit
 * des Buttons — nicht, ob Feedback entgegengenommen wird. Dadurch lässt sich das
 * Formular jederzeit über `?tabslFeedback=1` öffnen, auch wenn der Button
 * ausgeblendet ist. Der Parameter wird in der Session vermerkt, damit das Widget
 * auf den folgenden Seiten erhalten bleibt; `?tabslFeedback=0` löscht den Vermerk.
 * Für Gäste ohne Session-Cookie startet OXID die Session nur, wenn der Parameter
 * in `aRequireSessionWithParams` der config.inc.php steht.
 *
 * ⚠️ Damit ist der öffentliche Endpunkt immer erreichbar, sobald das Modul
 * konfiguriert ist. Das Ausblenden des Buttons ist eine Frage der Darstellung,
 * keine Zugangssperre. Missbrauchsschutz leistet allein tabslTurnstile — für den
 * öffentlichen Betrieb ist es dringend empfohlen.
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
     * Darf eine Absendung entgegengenommen werden? Einzige Bedingung ist ein
     * vollständig eingerichtetes Ticket-Ziel — ohne es könnte kein Ticket entstehen.
     */
    public function isSubmitAllowed(): bool
    {
        return $this->settings->isConfigured();
    }

    /**
     * Soll das Widget in die Seite eingebunden werden? Entweder weil der Button
     * generell sichtbar ist oder weil diese Seite oder eine frühere derselben
     * Session gezielt mit dem Parameter aufgerufen wurde.
     */
    public function shouldRenderWidget(): bool
    {
        if (!$this->settings->isConfigured()) {
            return false;
        }

        $this->rememberRequestInSession();

        return $this->settings->isFrontendFormEnabled()
            || $this->isOpenRequested()
            || (bool) Registry::getSession()->getVariable(self::REQUEST_PARAM);
    }

    /**
     * Wurde die Seite mit `?tabslFeedback=1` aufgerufen? Dann öffnet sich der
     * Dialog sofort — und das Widget liegt auf der Seite, auch wenn der Button
     * sonst ausgeblendet ist. Ein Button erscheint dabei nur, wenn die Position
     * nicht auf `none` steht.
     */
    public function isOpenRequested(): bool
    {
        $value = Registry::getRequest()->getRequestParameter(self::REQUEST_PARAM);

        if ($value === null || $value === '') {
            return false;
        }

        return !in_array(strtolower((string) $value), ['0', 'false', 'off', 'no'], true);
    }

    private function rememberRequestInSession(): void
    {
        $value = Registry::getRequest()->getRequestParameter(self::REQUEST_PARAM);

        if ($value === null || $value === '') {
            return;
        }

        if ($this->isOpenRequested()) {
            Registry::getSession()->setVariable(self::REQUEST_PARAM, true);
        } else {
            Registry::getSession()->deleteVariable(self::REQUEST_PARAM);
        }
    }
}
