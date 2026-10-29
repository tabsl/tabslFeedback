<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;

/**
 * Erzeugt aus dem Freitext des Melders einen Ticket-Titel und eine lesbare
 * Beschreibung — über die OpenAI Chat-Completions-API.
 *
 * Der Dienst wirft nie — ein Ausfall (fehlender Key, Zeitüberschreitung,
 * Fehlerstatus, unbrauchbare Antwort) wird als null gemeldet. Die Ticket-Anlage
 * läuft dann ohne Aufbereitung weiter.
 *
 * Bilddaten werden ausdrücklich nicht übermittelt.
 */
class OpenAiService implements AiTicketGeneratorInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    private const TIMEOUT_SECONDS = 10;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /** @var ModuleSettings */
    private $settings;

    /** @var AiPromptBuilder */
    private $promptBuilder;

    public function __construct(?ModuleSettings $settings = null, ?AiPromptBuilder $promptBuilder = null)
    {
        $this->settings = $settings ?? new ModuleSettings();
        $this->promptBuilder = $promptBuilder ?? new AiPromptBuilder();
    }

    /**
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     *
     * @return array{title:string,description:string}|null null = keine Aufbereitung möglich
     */
    public function generateTicket(string $message, array $images = []): ?array
    {
        $apiKey = $this->settings->getOpenAiKey();

        if ($apiKey === '') {
            return null;
        }

        $payload = [
            'model' => $this->settings->getOpenAiModel(),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->promptBuilder->buildSystemPrompt($this->settings)],
                ['role' => 'user', 'content' => $message],
            ],
        ];

        $response = $this->request($apiKey, $payload);

        if ($response === null) {
            return null;
        }

        return $this->parseResponse($response);
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

        $ticket = $this->promptBuilder->normalize($content);

        if ($ticket === null) {
            $hasDescription = isset($content['description']) && is_scalar($content['description'])
                && trim((string) $content['description']) !== '';
            $this->log('openai returned no title' . ($hasDescription ? ' (description discarded)' : ''));
        }

        return $ticket;
    }
}
