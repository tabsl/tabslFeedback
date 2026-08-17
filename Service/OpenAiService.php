<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;

/**
 * Erzeugt aus dem Freitext des Melders einen Ticket-Titel und eine lesbare
 * Beschreibung.
 *
 * Der Dienst wirft nie — ein Ausfall (fehlender Key, Zeitüberschreitung,
 * Fehlerstatus, unbrauchbare Antwort) wird als null gemeldet. Die Ticket-Anlage
 * läuft dann ohne Aufbereitung weiter.
 *
 * Bilddaten werden ausdrücklich nicht übermittelt.
 */
class OpenAiService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    private const MAX_TITLE_LENGTH = 120;

    private const TIMEOUT_SECONDS = 10;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /** @var ModuleSettings */
    private $settings;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?? new ModuleSettings();
    }

    /**
     * @return array{title:string,description:string}|null null = keine Aufbereitung möglich
     */
    public function generateTicket(string $message): ?array
    {
        $apiKey = $this->settings->getOpenAiKey();

        if ($apiKey === '') {
            return null;
        }

        $payload = [
            'model' => $this->settings->getOpenAiModel(),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->buildSystemPrompt()],
                ['role' => 'user', 'content' => $message],
            ],
        ];

        $response = $this->request($apiKey, $payload);

        if ($response === null) {
            return null;
        }

        return $this->parseResponse($response);
    }

    private function buildSystemPrompt(): string
    {
        // Ohne Fallback rät das Modell die Sprache, sobald die Meldung zu kurz
        // ist, um sie zu erkennen — aus "test" wurde so schon ein spanisches
        // Ticket. Die Shop-Sprache ist der naheliegende Rückfall.
        $languageRule = $this->settings->getTicketLanguage() === 'en'
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

    /**
     * @param array<string,mixed> $payload
     */
    private function request(string $apiKey, array $payload): ?string
    {
        $body = json_encode($payload);

        if ($body === false) {
            return null;
        }

        $curl = curl_init(self::API_URL);

        if ($curl === false) {
            return null;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($response) || $status !== 200) {
            // Ohne Protokoll ist im Nachhinein nicht feststellbar, warum ein
            // Ticket ohne Aufbereitung entstanden ist — abgelaufener Schlüssel,
            // erschöpftes Kontingent und Zeitüberschreitung sähen alle gleich aus.
            $this->log(
                'openai request failed'
                . ' — http ' . $status
                . ($curlError !== '' ? ', curl: ' . $curlError : '')
                . $this->describeApiError($response)
            );

            return null;
        }

        return $response;
    }

    /**
     * Fehlerangabe aus der API-Antwort, sofern eine gemeldet wurde. Enthält
     * niemals den Schlüssel — nur Typ und Meldung des Dienstes.
     *
     * @param string|bool $response
     */
    private function describeApiError($response): string
    {
        if (!is_string($response) || $response === '') {
            return '';
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !isset($decoded['error'])) {
            return '';
        }

        $type = isset($decoded['error']['type']) ? (string) $decoded['error']['type'] : '';
        $message = isset($decoded['error']['message']) ? (string) $decoded['error']['message'] : '';

        // OpenAI maskiert den Schlüssel in seinen Fehlertexten nur teilweise
        // ("sk-proj-*****ltig") — Präfix und letzte Zeichen bleiben lesbar. Auch
        // dieses Fragment hat im Protokoll nichts verloren.
        $message = (string) preg_replace('/\bsk-[A-Za-z0-9_*\-]{4,}/', '[key]', $message);

        // Einzeilig halten: ein Zeilenumbruch im Fremdtext ließe sich sonst als
        // eigener Protokolleintrag ausgeben.
        $message = trim((string) preg_replace('/\s+/u', ' ', $message));

        return ', api: ' . trim($type . ' ' . mb_substr($message, 0, 300));
    }

    private function log(string $message): void
    {
        try {
            Registry::getLogger()->error('[tabslFeedback] ' . $message);
        } catch (\Throwable $exception) {
            // Ein fehlschlagendes Protokoll darf die Ticket-Anlage nicht kosten.
        }
    }

    /**
     * @return array{title:string,description:string}|null
     */
    private function parseResponse(string $response): ?array
    {
        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !isset($decoded['choices'][0]['message']['content'])) {
            $this->log('openai answered without usable content');

            return null;
        }

        $content = json_decode((string) $decoded['choices'][0]['message']['content'], true);

        if (!is_array($content)) {
            $this->log('openai content was not valid json');

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
            $this->log('openai returned no title' . ($description !== '' ? ' (description discarded)' : ''));

            return null;
        }

        return [
            // Zeilenumbrüche im Titel würden die Issue-Anlage verunstalten.
            'title' => mb_substr((string) preg_replace('/\s+/u', ' ', $title), 0, self::MAX_TITLE_LENGTH),
            'description' => $description,
        ];
    }
}
