<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;

/**
 * Erzeugt Ticket-Titel und Beschreibung über einen vom Betreiber eingetragenen
 * Endpunkt. Anders als OpenAiService und AnthropicService baut
 * dieser Dienst keinen Prompt: Der Endpunkt erhält Freitext, Shop-Sprache und
 * Ticket-Sprache und liefert Titel und Beschreibung fertig zurück.
 *
 * Der Dienst wirft nie — ein Ausfall (fehlende Adresse oder Token, unzulässige
 * Adresse, Zeitüberschreitung, Fehlerstatus, unbrauchbare Antwort) wird als
 * null gemeldet. Die Ticket-Anlage läuft dann ohne Aufbereitung weiter.
 *
 * Bilddaten werden übermittelt, sofern FeedbackService welche übergibt — also
 * nur bei erlaubten Screenshots (ADR-003).
 */
class CustomAiService implements AiTicketGeneratorInterface
{
    // Der Endpunkt rechnet mit bis zu 20 s für die KI; dazu kommt der Upload.
    private const TIMEOUT_SECONDS = 25;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    // Nur auf demselben Rechner darf der Token unverschlüsselt gehen — für
    // Entwicklung und Mock-Endpunkte. Ein externer Dienst über http wäre ein
    // Tippfehler, der den Token offenlegt.
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]', '::1'];

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
     */
    public function generateTicket(string $message, array $images = []): ?array
    {
        $url = $this->settings->getCustomAiUrl();
        $token = $this->settings->getCustomAiToken();

        if ($url === '' || $token === '') {
            return null;
        }

        $urlError = $this->validateUrl($url);

        if ($urlError !== null) {
            $this->log('custom ai url rejected — ' . $urlError);

            return null;
        }

        $fields = [
            'text' => $message,
            'language' => $this->promptBuilder->getShopLanguage(),
            'ticketLanguage' => $this->settings->getTicketLanguage(),
        ];

        $request = $images === [] ? $this->buildJsonBody($fields) : $this->buildMultipartBody($fields, $images);

        if ($request === null) {
            return null;
        }

        $secrets = ['token' => $token, 'text' => trim($message), 'url' => $url];

        try {
            $response = $this->request($url, $token, $request['body'], $request['contentType'], $secrets);
        } catch (\Throwable $exception) {
            // Nur die Klasse: Die Meldung einer curl-Exception kann die Adresse enthalten.
            $this->log('custom ai request failed — ' . get_class($exception));

            return null;
        }

        if ($response === null) {
            return null;
        }

        return $this->parseResponse($response);
    }

    /**
     * @return string|null null = zulässig, sonst der Grund für das Log
     */
    private function validateUrl(string $url): ?string
    {
        // parse_url() nimmt Steuerzeichen hin, curl_init() wirft bei einem Nullbyte.
        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return 'invalid url';
        }

        $parts = parse_url($url);

        if (!is_array($parts) || (string) ($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass'])) {
            return 'invalid url';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme === 'https') {
            return null;
        }

        if ($scheme !== 'http') {
            return 'invalid url';
        }

        return in_array(strtolower((string) $parts['host']), self::LOCAL_HOSTS, true) ? null : 'https required';
    }

    /**
     * @param array<string,string> $fields
     *
     * @return array{body:string,contentType:string}|null
     */
    private function buildJsonBody(array $fields): ?array
    {
        $body = json_encode($fields);

        if ($body === false) {
            return null;
        }

        return ['body' => $body, 'contentType' => 'application/json'];
    }

    /**
     * Von Hand statt CURLStringFile: Die Bilder liegen nur im Speicher, und
     * CURLStringFile gibt es erst ab PHP 8.1.
     *
     * @param array<string,string> $fields
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     *
     * @return array{body:string,contentType:string}
     */
    private function buildMultipartBody(array $fields, array $images): array
    {
        $boundary = '----tabslFeedback' . bin2hex(random_bytes(16));
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n"
                . $value . "\r\n";
        }

        foreach ($images as $image) {
            $safeFilename = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $image['filename']);

            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="images[]"; filename="' . $safeFilename . '"' . "\r\n"
                . 'Content-Type: ' . $image['mime'] . "\r\n\r\n"
                . $image['bytes'] . "\r\n";
        }

        $body .= '--' . $boundary . "--\r\n";

        return ['body' => $body, 'contentType' => 'multipart/form-data; boundary=' . $boundary];
    }

    /**
     * @param array<string,string> $secrets
     */
    private function request(string $url, string $token, string $body, string $contentType, array $secrets): ?string
    {
        $curl = curl_init($url);

        if ($curl === false) {
            return null;
        }

        $retryAfter = '';

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $contentType,
            'Accept: application/json',
            'X-API-Token: ' . $token,
        ]);
        // Eine Weiterleitung schickte Token und Bilder an eine Adresse, die der
        // Betreiber nicht eingetragen hat.
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, static function ($handle, string $header) use (&$retryAfter): int {
            if (stripos($header, 'Retry-After:') === 0) {
                $retryAfter = trim(substr($header, strlen('Retry-After:')));
            }

            return strlen($header);
        });

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);

        if (!is_string($response) || $status !== 200) {
            // Ohne Protokoll ist im Nachhinein nicht feststellbar, warum ein
            // Ticket ohne Aufbereitung entstanden ist — falscher Token,
            // Ratenbegrenzung und Zeitüberschreitung sähen alle gleich aus.
            $this->log(
                'custom ai request failed'
                . ' — http ' . $status
                . ($curlError !== '' ? ', curl: ' . $this->singleLine($curlError, 300) : '')
                . ($status === 429 && $this->isRetryAfterValue($retryAfter) ? ', retry-after: ' . $this->maskSecrets($retryAfter, $secrets) : '')
                . $this->describeApiError($response, $secrets)
            );

            return null;
        }

        return $response;
    }

    /**
     * Nur Sekunden oder ein HTTP-Datum — ein freier Wert könnte einen
     * zurückgespiegelten Token tragen.
     */
    private function isRetryAfterValue(string $value): bool
    {
        return preg_match('/^\d{1,10}$/', $value) === 1
            || preg_match('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/', $value) === 1;
    }

    /**
     * Fehlerangabe aus der API-Antwort, sofern eine gemeldet wurde.
     *
     * @param string|bool $response curl_exec() liefert bei einem Transportfehler
     *                              (Timeout, DNS, …) bool false statt eines Strings.
     * @param array<string,string> $secrets
     */
    private function describeApiError($response, array $secrets): string
    {
        if (!is_string($response) || $response === '') {
            return '';
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return '';
        }

        $code = isset($decoded['code']) && is_string($decoded['code']) ? $decoded['code'] : '';
        $message = isset($decoded['message']) && is_string($decoded['message']) ? $decoded['message'] : '';

        // Der Endpunkt ist frei gewählt und könnte Token, Meldungstext oder die
        // eigene Adresse in seiner Fehlermeldung zurückspiegeln — nichts davon
        // gehört ins Shop-Log. Erst einzeilig machen, dann maskieren: Ein
        // Endpunkt, der die Zeilenumbrüche des Meldungstexts zusammenzieht,
        // spiegelt sonst einen Text, der dem Original nicht mehr gleicht.
        $code = mb_substr($this->maskSecrets($this->singleLine($code, PHP_INT_MAX), $secrets), 0, 64);
        $message = mb_substr($this->maskSecrets($this->singleLine($message, PHP_INT_MAX), $secrets), 0, 300);

        if ($code === '' && $message === '') {
            return '';
        }

        return ', api: ' . trim($code . ' ' . $message);
    }

    /**
     * @param array<string,string> $secrets
     */
    private function maskSecrets(string $value, array $secrets): string
    {
        $host = (string) parse_url($secrets['url'], PHP_URL_HOST);

        $replacements = [$secrets['token'] => '[token]', $secrets['url'] => '[url]'];

        if ($secrets['text'] !== '') {
            $replacements[$secrets['text']] = '[text]';
            $replacements[$this->singleLine($secrets['text'], PHP_INT_MAX)] = '[text]';
        }

        if ($host !== '') {
            $replacements[$host] = '[url]';
        }

        // strtr ersetzt den längsten Treffer zuerst — die Adresse vor ihrem Host.
        return strtr($value, $replacements);
    }

    /**
     * Einzeilig halten: ein Zeilenumbruch im Fremdtext ließe sich sonst als
     * eigener Protokolleintrag ausgeben.
     */
    private function singleLine(string $value, int $maxLength): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $value)), 0, $maxLength);
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
        $content = json_decode($response, true);

        if (!is_array($content)) {
            $this->log('custom ai answered without usable content — http 200');

            return null;
        }

        $ticket = $this->promptBuilder->normalize($content);

        if ($ticket === null) {
            $hasDescription = isset($content['description']) && is_scalar($content['description'])
                && trim((string) $content['description']) !== '';
            $this->log('custom ai returned no title — http 200' . ($hasDescription ? ' (description discarded)' : ''));
        }

        return $ticket;
    }
}
