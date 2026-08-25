<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;

/**
 * Stellt Grenzwerte und übersetzte Oberflächentexte als JSON für das
 * JavaScript bereit — im Frontend-Widget wie im Backend-Formular.
 *
 * Die Platzhalter der Meldungen ("höchstens %d Bilder") werden hier gefüllt und
 * nicht im Browser: die Grenzwerte sind PHP-Konstanten, und ein zweiter
 * Formatierungsweg im JavaScript wäre eine zusätzliche Fehlerquelle.
 */
class UiTexts
{
    /** Idents ohne Platzhalter. */
    private const PLAIN_TEXTS = [
        'triggerLabel' => 'TABSLFEEDBACK_TRIGGER',
        'title' => 'TABSLFEEDBACK_TITLE',
        'messageLabel' => 'TABSLFEEDBACK_MESSAGE_LABEL',
        'subjectLabel' => 'TABSLFEEDBACK_SUBJECT_LABEL',
        'screenshotsLabel' => 'TABSLFEEDBACK_SCREENSHOTS_LABEL',
        'screenshotsHint' => 'TABSLFEEDBACK_SCREENSHOTS_HINT',
        'nameLabel' => 'TABSLFEEDBACK_NAME_LABEL',
        'emailLabel' => 'TABSLFEEDBACK_EMAIL_LABEL',
        'submit' => 'TABSLFEEDBACK_SUBMIT',
        'cancel' => 'TABSLFEEDBACK_CANCEL',
        'close' => 'TABSLFEEDBACK_CLOSE',
        'sending' => 'TABSLFEEDBACK_SENDING',
        'removeImage' => 'TABSLFEEDBACK_REMOVE_IMAGE',
        'thanks' => 'TABSLFEEDBACK_THANKS',
        'errorGeneric' => 'TABSLFEEDBACK_ERROR_GENERIC',
        'errorNetwork' => 'TABSLFEEDBACK_ERROR_NETWORK',
        'errorMessageRequired' => 'TABSLFEEDBACK_ERROR_MESSAGE_REQUIRED',
        'errorEmailInvalid' => 'TABSLFEEDBACK_ERROR_EMAIL_INVALID',
        'errorImageType' => 'TABSLFEEDBACK_ERROR_IMAGE_TYPE',
    ];

    /**
     * Setzt Platzhalterwerte in einen übersetzten Text ein.
     *
     * Enthält eine Übersetzung mehr Platzhalter als Werte übergeben werden,
     * wirft vsprintf einen ValueError. Da diese Methode auch aus catch-Blöcken
     * heraus aufgerufen wird, entstünde daraus eine Fehlerseite statt der
     * vorgesehenen neutralen Meldung — deshalb fällt sie im
     * Zweifel auf den unformatierten Text zurück.
     *
     * @param array<string,string|int> $params
     */
    public static function format(string $message, array $params): string
    {
        if ($params === []) {
            return $message;
        }

        try {
            return vsprintf($message, array_values($params));
        } catch (\Throwable $exception) {
            return $message;
        }
    }

    /** @var bool Im Backend werden die Admin-Sprachdateien gelesen. */
    private $isAdmin;

    public function __construct(bool $isAdmin)
    {
        $this->isAdmin = $isAdmin;
    }

    /**
     * @return array<string,mixed>
     */
    public function getLimits(): array
    {
        return [
            'maxImages' => InputValidator::MAX_IMAGES,
            'maxImageBytes' => InputValidator::MAX_IMAGE_BYTES,
            'maxTotalBytes' => InputValidator::MAX_TOTAL_BYTES,
            'maxMessageLength' => InputValidator::MAX_MESSAGE_LENGTH,
            'maxSubjectLength' => InputValidator::MAX_SUBJECT_LENGTH,
            'maxContactLength' => InputValidator::MAX_CONTACT_FIELD_LENGTH,
            'allowedMimeTypes' => InputValidator::ALLOWED_MIME_TYPES,
        ];
    }

    public function getLimitsJson(): string
    {
        return $this->encode($this->getLimits());
    }

    public function getTextsJson(): string
    {
        $texts = [];

        foreach (self::PLAIN_TEXTS as $key => $ident) {
            $texts[$key] = $this->translate($ident);
        }

        $texts['errorMessageTooLong'] = sprintf(
            $this->translate('TABSLFEEDBACK_ERROR_MESSAGE_TOO_LONG'),
            InputValidator::MAX_MESSAGE_LENGTH
        );
        $texts['errorContactTooLong'] = sprintf(
            $this->translate('TABSLFEEDBACK_ERROR_CONTACT_TOO_LONG'),
            InputValidator::MAX_CONTACT_FIELD_LENGTH
        );
        $texts['errorSubjectTooLong'] = sprintf(
            $this->translate('TABSLFEEDBACK_ERROR_SUBJECT_TOO_LONG'),
            InputValidator::MAX_SUBJECT_LENGTH
        );
        $texts['errorTooManyImages'] = sprintf(
            $this->translate('TABSLFEEDBACK_ERROR_TOO_MANY_IMAGES'),
            InputValidator::MAX_IMAGES
        );
        $texts['errorImageTooLarge'] = sprintf(
            $this->translate('TABSLFEEDBACK_ERROR_IMAGE_TOO_LARGE'),
            (int) round(InputValidator::MAX_IMAGE_BYTES / 1048576)
        );
        $texts['errorImagesTooLarge'] = sprintf(
            $this->translate('TABSLFEEDBACK_ERROR_IMAGES_TOO_LARGE'),
            (int) round(InputValidator::MAX_TOTAL_BYTES / 1048576)
        );

        return $this->encode($texts);
    }

    private function translate(string $ident): string
    {
        return (string) Registry::getLang()->translateString($ident, null, $this->isAdmin);
    }

    /**
     * Das JSON landet in einem HTML-Attribut. Anführungszeichen, Apostrophe und
     * spitze Klammern werden deshalb als Escape-Sequenz kodiert — ein Apostroph
     * in einer Übersetzung würde sonst das Attribut beenden.
     *
     * @param array<string,mixed> $data
     */
    private function encode(array $data): string
    {
        return (string) json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        );
    }
}
