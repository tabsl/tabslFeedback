<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Controller;

use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;
use Tabsl\Feedback\Service\FeedbackService;
use Tabsl\Feedback\Service\FrontendAccess;
use Tabsl\Feedback\Service\InputValidator;
use Tabsl\Feedback\Service\TurnstileGate;
use Tabsl\Feedback\Service\UiTexts;

/**
 * Einziger Eintrittspunkt für Feedback aus dem Shop-Frontend.
 *
 * Die Antwort verrät nie, welche Systeme beteiligt waren: kein Ticket-Verweis,
 * keine GitLab-URL, kein Dienstname, kein Exception-Text — und in Erfolgs- wie
 * Fehlerfall derselbe HTTP-Status.
 */
class SubmitController extends FrontendController
{
    /** @var ModuleSettings|null */
    private $settings = null;

    /**
     * Dieser Controller liefert JSON, kein Template.
     */
    public function render(): string
    {
        return '';
    }

    public function submit(): void
    {
        if (!$this->isPostRequest()) {
            header('Allow: POST');
            $this->respond(false, 'TABSLFEEDBACK_ERROR_GENERIC', 405);
        }

        // Die Entgegennahme hängt allein an der Konfiguration. Ob der Button
        // sichtbar ist, entscheidet nur über die Darstellung — sonst ließe sich
        // ein über ?tabslFeedback=1 geöffnetes Formular nicht absenden.
        if (!(new FrontendAccess($this->getSettings()))->isSubmitAllowed()) {
            Registry::getLogger()->error(
                '[tabslFeedback] submission rejected: module is not fully configured'
            );

            $this->respond(false, 'TABSLFEEDBACK_ERROR_GENERIC');
        }

        // Bewusst KEINE Prüfung des Session-Tokens.
        //
        // Für anonyme Besucher startet der Shop keine Sitzung, weshalb die Seite
        // ein leeres stoken ausliefert. Beim Absenden entsteht dann doch eine
        // Sitzung — eine Prüfung würde also ausgerechnet die nicht angemeldeten
        // Besucher aussperren, für die das Formular gedacht ist.
        //
        // Sie brächte hier auch keinen Schutz: Der Endpunkt nimmt bewusst von
        // jedem entgegen, ein fremd ausgelöster Request erreicht nichts, was ein
        // Angreifer nicht ebenso direkt tun könnte. Gegen Missbrauch steht
        // tabslTurnstile, nicht ein Token.
        //
        // Das Backend-Formular ist davon unberührt: Dort erzwingt der
        // AdminController ohnehin eine gültige Sitzung samt Token-Prüfung.

        $turnstile = new TurnstileGate();
        $turnstileState = $turnstile->resolveState();

        // Lässt sich der Schutzzustand nicht ermitteln, wird abgewiesen statt
        // durchgelassen — sonst schaltete ein interner Fehler den einzigen
        // Missbrauchsschutz des öffentlichen Formulars still ab.
        if ($turnstileState === TurnstileGate::STATE_ERROR) {
            $this->respond(false, 'TABSLFEEDBACK_ERROR_GENERIC');
        }

        // Schlägt die Bot-Prüfung fehl, endet der Vorgang hier — es wird weder
        // OpenAI noch GitLab angesprochen.
        if ($turnstileState === TurnstileGate::STATE_ON
            && !$turnstile->verify((string) $this->getRequestValue(TurnstileGate::TOKEN_FIELD))
        ) {
            Registry::getLogger()->info('[tabslFeedback] submission rejected: bot check not passed');

            $this->respond(false, 'TABSLFEEDBACK_ERROR_BOT_CHECK');
        }

        try {
            $input = (new InputValidator())->validate(
                (string) $this->getRequestValue('fb_message'),
                (string) $this->getRequestValue('fb_subject'),
                (array) $this->getRequestValue('fb_images', []),
                (string) $this->getRequestValue('fb_name'),
                (string) $this->getRequestValue('fb_email'),
                (string) $this->getRequestValue('fb_meta')
            );

            (new FeedbackService($this->getSettings()))->submit($input, FeedbackService::CONTEXT_FRONTEND);
        } catch (FeedbackException $exception) {
            $this->respond(false, $exception->getUserMessageIdent(), 200, $exception->getUserMessageParams());
        } catch (\Throwable $exception) {
            // Ein unerwarteter Fehler darf im Shop nie als Fehlerseite sichtbar
            // werden — ohne Protokoll bliebe er allerdings
            // gänzlich unauffindbar.
            // Die Meldung stammt aus fremdem Code und kann Zeilenumbrüche oder
            // Eingaben des Melders enthalten — einzeilig und gekürzt ins Protokoll,
            // damit sich daraus kein zweiter Eintrag vortäuschen lässt.
            Registry::getLogger()->error(
                '[tabslFeedback] submission failed unexpectedly: '
                . mb_substr(trim((string) preg_replace('/\s+/u', ' ', $exception->getMessage())), 0, 300)
            );

            $this->respond(false, 'TABSLFEEDBACK_ERROR_GENERIC');
        }

        $this->respond(true, 'TABSLFEEDBACK_THANKS');
    }

    /**
     * Beendet den Request. Die Antwort trägt in Erfolgs- und Fehlerfall denselben
     * Status 200 — nur ein Methodenverstoß wird abweichend quittiert.
     *
     * @param array<string,string|int> $params Platzhalterwerte der Meldung
     */
    private function respond(bool $ok, string $messageIdent, int $status = 200, array $params = []): void
    {
        $message = UiTexts::format(
            (string) Registry::getLang()->translateString($messageIdent, null, false),
            $params
        );

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        echo (string) json_encode(['ok' => $ok, 'message' => $message]);

        exit;
    }

    /**
     * Bewusst der unescapte Zugriff: der Freitext muss unverändert ins Ticket
     * und die base64-Bilddaten dürfen nicht verändert
     * werden. Die Prüfung übernimmt InputValidator.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    private function getRequestValue(string $name, $default = '')
    {
        return Registry::getRequest()->getRequestParameter($name, $default);
    }

    private function isPostRequest(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST';
    }

    private function getSettings(): ModuleSettings
    {
        if ($this->settings === null) {
            $this->settings = new ModuleSettings();
        }

        return $this->settings;
    }
}
