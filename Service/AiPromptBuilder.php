<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;

/**
 * Vom Anbieter unabhängiger Teil der KI-Aufbereitung: der Systemprompt und die
 * Prüfung der Modellantwort. OpenAiService und AnthropicService unterscheiden
 * sich nur darin, wie eine Anfrage gestellt und die Antwort dem Modell
 * entnommen wird — Regeltext und Ergebnisprüfung sind identisch und liegen
 * deshalb hier an einer Stelle.
 */
class AiPromptBuilder
{
    public const MAX_TITLE_LENGTH = 120;

    public function buildSystemPrompt(ModuleSettings $settings): string
    {
        // Ohne Fallback rät das Modell die Sprache, sobald die Meldung zu kurz
        // ist, um sie zu erkennen — aus "test" wurde so schon ein spanisches
        // Ticket. Die Shop-Sprache ist der naheliegende Rückfall.
        $languageRule = $settings->getTicketLanguage() === 'en'
            ? 'Write both fields in English, regardless of the language of the report.'
            : 'Write both fields in the same language the report is written in. If that language cannot '
                . 'be determined — for example because the report is very short — write both fields in the '
                . 'language with the ISO 639-1 code "' . $this->getShopLanguage() . '". Never pick a third language.';

        return 'You turn an informal user report about an online shop into a bug ticket. '
            . 'Answer with a JSON object containing exactly the keys "title" and "description". '

            . '"title": a short, specific one-line summary, at most ' . self::MAX_TITLE_LENGTH . ' characters, '
            . 'no trailing period. Mandatory — never empty, not even for a report of a single word. '
            // Bei "geht nicht" erfand das Modell einen Gegenstand hinzu ("Zugang zur
            // Website funktioniert nicht") und schickte den Bearbeiter damit auf eine
            // Fährte, die in der Meldung gar nicht steht.
            . 'If the report names no concrete subject — a bare "geht nicht" or the like — stay close to '
            . 'the reporter\'s own words instead of guessing what they might have meant. '

            // Die Beschreibung ist der Teil, der leicht zu Füllmaterial verkommt. Das
            // Ticket zeigt die Originalmeldung ohnehin im Wortlaut, eine Umformulierung
            // verdoppelt sie also nur — und die Frage nach der "erwarteten" Wirkung hat
            // das Modell zuverlässig dazu verleitet, sich eine auszudenken.
            . '"description": usually an EMPTY STRING. Write one ONLY when the report states several '
            . 'distinct facts that are hard to take in as running text — then list the facts it actually '
            . 'contains as short Markdown bullets (what happened, where, when, how often, which payment '
            . 'method, which device, and so on). A report that is one clear sentence, or that carries a '
            . 'single fact, needs NO description. '
            . 'Never restate the report in other words. '
            . 'Never use reporting phrases such as "the user reports", "the user describes" or '
            . '"der Benutzer meldet" — write about the problem, not about the person. '
            . 'Never add an expected behaviour, a cause, reproduction steps, labels, severity or a '
            . 'category that the report does not itself contain. '
            . 'The original wording is always shown in the ticket, so repeating it wastes the reader\'s time. '

            . $languageRule;
    }

    /**
     * Prüft und normalisiert die vom Modell gelieferten Felder. $content ist
     * bereits die dekodierte JSON-Antwort des Modells, unabhängig vom Anbieter.
     *
     * @param mixed $content
     *
     * @return array{title:string,description:string}|null
     */
    public function normalize($content): ?array
    {
        if (!is_array($content)) {
            return null;
        }

        $title = isset($content['title']) && is_scalar($content['title'])
            ? trim((string) $content['title'])
            : '';

        $description = isset($content['description']) && is_scalar($content['description'])
            ? trim((string) $content['description'])
            : '';

        // Ein fehlender Titel gilt als unbrauchbare Antwort, auch
        // wenn eine Beschreibung vorliegt: Diese besteht im Regelfall aus
        // Stichpunkten und ergäbe als Titel eine Markdown-Liste in der
        // Issue-Übersicht. Stattdessen greift der Rohtext-Fallback in
        // FeedbackService, der dafür gedacht ist.
        if ($title === '') {
            return null;
        }

        return [
            // Zeilenumbrüche im Titel würden die Issue-Anlage verunstalten.
            'title' => mb_substr((string) preg_replace('/\s+/u', ' ', $title), 0, self::MAX_TITLE_LENGTH),
            'description' => $description,
        ];
    }

    /**
     * Strippt einen umschließenden Markdown-Codeblock, den Modelle trotz
     * ausdrücklicher Anweisung gelegentlich um die JSON-Antwort legen.
     */
    public function stripCodeFence(string $text): string
    {
        $text = trim($text);

        if (!preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text, $matches)) {
            return $text;
        }

        return trim($matches[1]);
    }

    /**
     * Sprache des Shops als ISO-639-1-Code. Dient nur als Rückfall für den
     * Prompt; ist sie nicht ermittelbar, entfällt die Vorgabe stillschweigend.
     */
    private function getShopLanguage(): string
    {
        try {
            $abbr = (string) Registry::getLang()->getLanguageAbbr();

            return $abbr !== '' ? $abbr : 'de';
        } catch (\Throwable $exception) {
            return 'de';
        }
    }
}
