<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Controller\Admin;

use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;
use Tabsl\Feedback\Service\FeedbackService;
use Tabsl\Feedback\Service\InputValidator;
use Tabsl\Feedback\Service\UiTexts;

/**
 * Feedback-Formular im Backend.
 *
 * Der OXID-Admin ist ein Frameset, dessen Header nur wenige Pixel hoch ist — ein
 * Overlay ist dort baulich nicht darstellbar. Das Formular wird deshalb im
 * Hauptframe geöffnet, genau wie die vorhandenen Header-Links es tun.
 *
 * Keine Turnstile-Prüfung: der Bereich ist bereits durch die Anmeldung
 * geschützt. Die Eingabeprüfung ist dieselbe wie im Frontend —
 * beide Controller nutzen InputValidator.
 */
class FeedbackFormController extends AdminController
{
    /** @var ModuleSettings|null */
    private $settings = null;

    /** @var bool|null null = noch nichts abgesendet */
    private $resultOk = null;

    /** @var string */
    private $resultMessage = '';

    /** @var array<string,string> Bei Fehler erhalten gebliebene Eingaben */
    private $formValues = ['fb_message' => '', 'fb_subject' => '', 'fb_name' => '', 'fb_email' => ''];

    public function render(): string
    {
        parent::render();

        $settings = $this->getSettings();
        $available = $settings->isAdminFormEnabled() && $settings->isConfigured();

        $this->_aViewData['tabslfeedbackAvailable'] = $available;
        $this->_aViewData['tabslfeedbackShowContactFields'] = $settings->showContactFields();
        $this->_aViewData['tabslfeedbackShowSubjectField'] = !$settings->isAiEnabled();
        $this->_aViewData['tabslfeedbackShowScreenshots'] = $settings->areScreenshotsEnabled();
        $this->_aViewData['tabslfeedbackNoticeText'] = $settings->getNoticeText();
        $this->_aViewData['tabslfeedbackResultOk'] = $this->resultOk;
        $this->_aViewData['tabslfeedbackResultMessage'] = $this->resultMessage;
        $this->_aViewData['tabslfeedbackValues'] = $this->formValues;

        $uiTexts = new UiTexts(true);
        $this->_aViewData['tabslfeedbackLimits'] = $uiTexts->getLimitsJson();
        $this->_aViewData['tabslfeedbackTexts'] = $uiTexts->getTextsJson();

        return 'tabslfeedback_form.tpl';
    }

    /**
     * Nimmt die Absendung entgegen. Das Ergebnis wird nur vorgemerkt — die
     * Anzeige übernimmt das anschließende render() im selben Frame.
     */
    public function submit(): void
    {
        $settings = $this->getSettings();

        if (!$settings->isAdminFormEnabled() || !$settings->isConfigured()) {
            $this->setResult(false, 'TABSLFEEDBACK_ERROR_GENERIC');

            return;
        }

        if (!Registry::getSession()->checkSessionChallenge()) {
            $this->setResult(false, 'TABSLFEEDBACK_ERROR_GENERIC');

            return;
        }

        $request = Registry::getRequest();
        $rawMessage = (string) $request->getRequestParameter('fb_message', '');
        $rawSubject = (string) $request->getRequestParameter('fb_subject', '');
        $rawName = (string) $request->getRequestParameter('fb_name', '');
        $rawEmail = (string) $request->getRequestParameter('fb_email', '');

        try {
            $input = (new InputValidator())->validate(
                $rawMessage,
                $rawSubject,
                (array) $request->getRequestParameter('fb_images', []),
                $rawName,
                $rawEmail,
                (string) $request->getRequestParameter('fb_meta', '')
            );

            (new FeedbackService($settings))->submit($input, FeedbackService::CONTEXT_ADMIN);

            $this->setResult(true, 'TABSLFEEDBACK_THANKS');
        } catch (FeedbackException $exception) {
            $this->keepInput($rawMessage, $rawSubject, $rawName, $rawEmail);
            $this->setResult(false, $exception->getUserMessageIdent(), $exception->getUserMessageParams());
        } catch (\Throwable $exception) {
            $this->keepInput($rawMessage, $rawSubject, $rawName, $rawEmail);
            $this->setResult(false, 'TABSLFEEDBACK_ERROR_GENERIC');
        }
    }

    /**
     * Der eingegebene Text bleibt bei einem Fehler stehen, damit er nicht neu
     * getippt werden muss. Für die Screenshots gilt das
     * nicht — sie liegen nur im Browser und werden dort gehalten.
     */
    private function keepInput(string $message, string $subject, string $name, string $email): void
    {
        $this->formValues = [
            'fb_message' => $message,
            'fb_subject' => $subject,
            'fb_name' => $name,
            'fb_email' => $email,
        ];
    }

    /**
     * @param array<string,string|int> $params
     */
    private function setResult(bool $ok, string $messageIdent, array $params = []): void
    {
        $this->resultOk = $ok;
        $this->resultMessage = UiTexts::format(
            (string) Registry::getLang()->translateString($messageIdent, null, true),
            $params
        );
    }

    private function getSettings(): ModuleSettings
    {
        if ($this->settings === null) {
            $this->settings = new ModuleSettings();
        }

        return $this->settings;
    }
}
