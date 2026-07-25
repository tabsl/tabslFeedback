<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Exception;

use Exception;

/**
 * Fehlerzustände der Feedback-Verarbeitung.
 *
 * Für den Melder sind CONFIG, UPLOAD und GITLAB ununterscheidbar — er sieht in
 * allen Fällen dieselbe neutrale Meldung (requirements.md A2/B4). Nur
 * VALIDATION erzeugt einen benennenden Hinweis, damit eine zu große Datei oder
 * eine unplausible E-Mail korrigierbar ist statt still zu scheitern.
 *
 * Ein Ausfall von OpenAI ist hier bewusst NICHT vertreten: er ist kein
 * Fehlerzustand des Vorgangs, sondern führt lediglich zu einem Issue ohne
 * Aufbereitung. OpenAiService signalisiert das per null-Rückgabe
 * (planning.md §4, Component 4).
 */
class FeedbackException extends Exception
{
    public const TYPE_CONFIG = 'config';
    public const TYPE_VALIDATION = 'validation';
    public const TYPE_UPLOAD = 'upload';
    public const TYPE_GITLAB = 'gitlab';

    /** @var string */
    private $type;

    /** @var string Sprachdatei-Ident für die Meldung an den Nutzer */
    private $userMessageIdent;

    /** @var array<string,string|int> Platzhalterwerte für die Nutzermeldung */
    private $userMessageParams;

    /**
     * @param array<string,string|int> $userMessageParams
     */
    private function __construct(
        string $type,
        string $userMessageIdent,
        string $internalMessage,
        array $userMessageParams = []
    ) {
        parent::__construct($internalMessage);

        $this->type = $type;
        $this->userMessageIdent = $userMessageIdent;
        $this->userMessageParams = $userMessageParams;
    }

    public static function config(string $internalMessage): self
    {
        return new self(self::TYPE_CONFIG, 'TABSLFEEDBACK_ERROR_GENERIC', $internalMessage);
    }

    /**
     * @param array<string,string|int> $params
     */
    public static function validation(string $userMessageIdent, array $params = []): self
    {
        return new self(self::TYPE_VALIDATION, $userMessageIdent, 'validation failed: ' . $userMessageIdent, $params);
    }

    public static function upload(string $internalMessage): self
    {
        return new self(self::TYPE_UPLOAD, 'TABSLFEEDBACK_ERROR_GENERIC', $internalMessage);
    }

    public static function gitlab(string $internalMessage): self
    {
        return new self(self::TYPE_GITLAB, 'TABSLFEEDBACK_ERROR_GENERIC', $internalMessage);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUserMessageIdent(): string
    {
        return $this->userMessageIdent;
    }

    /**
     * @return array<string,string|int>
     */
    public function getUserMessageParams(): array
    {
        return $this->userMessageParams;
    }
}
