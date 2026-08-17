<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Core;

use Tabsl\Feedback\Service\FrontendAccess;
use Tabsl\Feedback\Service\TurnstileGate;
use Tabsl\Feedback\Service\UiTexts;

/**
 * Template-Brücke des Moduls.
 *
 * Die Blocks fragen ausschließlich diese Getter — es liegt keine Logik in den
 * Templates. Alle Namen tragen das Modul-Präfix, weil ViewConfig im Shop von
 * mehreren Modulen erweitert wird und gleichnamige Getter einander überschreiben
 * würden.
 */
class ViewConfig extends ViewConfig_parent
{
    /** @var ModuleSettings|null */
    private $tabslFeedbackSettings = null;

    /** @var TurnstileGate|null */
    private $tabslFeedbackTurnstileGate = null;

    /** @var FrontendAccess|null */
    private $tabslFeedbackAccess = null;

    /**
     * Soll der Feedback-Link im Backend-Header erscheinen?
     *
     * Ohne die drei GitLab-Pflichtangaben gibt es keinen Einstieg — ein Formular,
     * das garantiert kein Ticket erzeugen kann, wird gar nicht erst angeboten.
     */
    public function isTabslFeedbackAdminAvailable(): bool
    {
        $settings = $this->getTabslFeedbackSettings();

        return $settings->isAdminFormEnabled() && $settings->isConfigured();
    }

    /**
     * Wird das Widget auf dieser Seite eingebunden? Entweder weil der Button
     * generell sichtbar ist oder weil die Seite mit `?tabslFeedback=1`
     * aufgerufen wurde — siehe FrontendAccess.
     */
    public function isTabslFeedbackFrontendAvailable(): bool
    {
        return $this->getTabslFeedbackAccess()->shouldRenderWidget();
    }

    /**
     * Soll sich der Dialog beim Laden der Seite sofort öffnen? Das ist der Fall,
     * wenn die Seite gerade mit dem Zugangsparameter aufgerufen wurde.
     */
    public function isTabslFeedbackAutoOpen(): bool
    {
        return $this->getTabslFeedbackAccess()->isOpenRequested();
    }

    /**
     * @return string bottom-left|bottom-right|center|none
     */
    public function getTabslFeedbackButtonPosition(): string
    {
        return $this->getTabslFeedbackSettings()->getButtonPosition();
    }

    public function showTabslFeedbackContactFields(): bool
    {
        return $this->getTabslFeedbackSettings()->showContactFields();
    }

    /**
     * Der Hinweis auf die KI-Übermittlung erscheint nur, wenn tatsächlich eine
     * stattfindet — ohne hinterlegten Schlüssel wäre er schlicht falsch.
     */
    public function showTabslFeedbackAiNotice(): bool
    {
        return $this->getTabslFeedbackSettings()->usesAiProcessing();
    }

    /**
     * Dieselbe Zustandsermittlung, die auch SubmitController serverseitig nutzt.
     */
    public function isTabslFeedbackTurnstileActive(): bool
    {
        return $this->getTabslFeedbackTurnstileGate()->isActive();
    }

    public function getTabslFeedbackTurnstileSiteKey(): string
    {
        return $this->getTabslFeedbackTurnstileGate()->getSiteKey();
    }

    /**
     * Mit Zeitstempel, weil `window.tabslFeedback` sonst an einem gecachten
     * Skriptstand hängt: Eine Seite, die den Dialog selbst öffnet, findet die
     * Schnittstelle beim wiederkehrenden Besucher nicht vor und ihr Einstieg
     * bliebe wirkungslos.
     */
    public function getTabslFeedbackAssetUrl(string $file): string
    {
        $url = (string) $this->getModuleUrl('tabslFeedback', $file);

        // Pfad aus dem Ort dieser Klasse, nicht aus dem erwarteten Modulverzeichnis:
        // Wird das Modul unter einem anderen Namen entpackt, fiele der Zeitstempel
        // sonst still auf 0 zurück — und mit ihm der Zweck dieser Methode.
        $path = dirname(__DIR__) . '/' . ltrim($file, '/');
        $version = is_file($path) ? (string) filemtime($path) : '0';

        return $url . (strpos($url, '?') === false ? '?' : '&') . 'v=' . $version;
    }

    /**
     * Ziel des Absende-Requests. Bewusst aus der Shop-Basis-URL gebaut statt aus
     * getSelfActionLink(), damit keine Parameter der aktuellen Seite mitwandern.
     */
    public function getTabslFeedbackSubmitUrl(): string
    {
        return $this->getConfig()->getShopUrl() . 'index.php?cl=tabslfeedback_submit';
    }

    /**
     * Das Turnstile-Script darf nicht doppelt geladen werden. Bringt tabslTurnstile
     * es auf dieser Seite bereits mit (etwa im Kontaktformular), meldet dessen
     * ViewConfig-Erweiterung das über isTurnstileScriptLoaded().
     */
    public function shouldLoadTabslFeedbackTurnstileScript(): bool
    {
        if (!$this->isTabslFeedbackTurnstileActive()) {
            return false;
        }

        if (!method_exists($this, 'isTurnstileScriptLoaded')) {
            return true;
        }

        return !$this->isTurnstileScriptLoaded();
    }

    /**
     * Grenzwerte für die Vorabprüfung im Browser. Sie ersetzen die serverseitige
     * Prüfung nicht, ersparen dem Melder aber den Roundtrip.
     *
     * @return string JSON für das data-Attribut des Widgets
     */
    public function getTabslFeedbackLimitsJson(): string
    {
        return (new UiTexts(false))->getLimitsJson();
    }

    /**
     * Übersetzte Oberflächentexte für das per JavaScript aufgebaute Widget.
     */
    public function getTabslFeedbackTextsJson(): string
    {
        return (new UiTexts(false))->getTextsJson();
    }

    private function getTabslFeedbackSettings(): ModuleSettings
    {
        if ($this->tabslFeedbackSettings === null) {
            $this->tabslFeedbackSettings = new ModuleSettings();
        }

        return $this->tabslFeedbackSettings;
    }

    private function getTabslFeedbackAccess(): FrontendAccess
    {
        if ($this->tabslFeedbackAccess === null) {
            $this->tabslFeedbackAccess = new FrontendAccess($this->getTabslFeedbackSettings());
        }

        return $this->tabslFeedbackAccess;
    }

    private function getTabslFeedbackTurnstileGate(): TurnstileGate
    {
        if ($this->tabslFeedbackTurnstileGate === null) {
            $this->tabslFeedbackTurnstileGate = new TurnstileGate();
        }

        return $this->tabslFeedbackTurnstileGate;
    }
}
