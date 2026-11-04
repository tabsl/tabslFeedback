<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Der eine Aufruf an den eigenen Endpunkt: fester multipart-Vertrag, kein
 * Retry, keine Weiterleitung (docs/adr/ADR-004).
 *
 * Erfolg ist allein 201 mit einem Key des eingestellten Projekts. Alles andere
 * wirft und gilt im Log als "state unknown", solange die Antwort nicht eindeutig
 * sagt, dass nichts entstanden ist.
 */
class CustomTicketService
{
    /** Liegt über der Frist der Gegenstelle: Kommentar, Anlage und Anhänge dauern dort zusammen bis 70 s. */
    private const TIMEOUT_SECONDS = 95;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const MAX_SUMMARY_LENGTH = 255;

    /**
     * Antworten der Gegenstelle gehen nur über diese Listen ins Log. Eine Maske
     * erkennt nur vollständige Zeichenfolgen und ließe Ausschnitte einer
     * gespiegelten Meldung durch.
     */
    private const KNOWN_REASONS = ['unknown_project', 'internal_not_configured', 'instance_not_ready', 'jira_rejected', 'jira_error'];

    private const KNOWN_FIELDS = ['project', 'issuetype', 'summary', 'description', 'assignee', 'reporter', 'priority', 'labels', 'components', 'duedate', 'parent'];

    /** @var ModuleSettings */
    private $settings;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?? new ModuleSettings();
    }

    /**
     * @param array<string,mixed> $description ADF-Dokument
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     * @param string $commentOnIssue leer = keine Meldung als Kommentar
     * @param array<string,mixed> $comment ADF-Dokument, nur zusammen mit $commentOnIssue
     *
     * @throws FeedbackException vom Typ CUSTOM_TICKET oder CONFIG
     */
    public function createIssue(string $summary, array $description, array $images, string $commentOnIssue, array $comment): void
    {
        if (!$this->settings->isCustomTicketConfigured()) {
            $this->log('custom ticket endpoint address, token or project is missing or malformed');

            throw FeedbackException::config('custom ticket configuration is incomplete');
        }

        $fields = [
            'action' => 'create_issue',
            'projectKey' => $this->settings->getCustomTicketProject(),
            'issueType' => $this->settings->getCustomTicketIssueType(),
            'summary' => mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $summary)), 0, self::MAX_SUMMARY_LENGTH),
            'description' => $this->encode($description),
            'ticketLanguage' => $this->settings->getTicketLanguage(),
        ];

        if ($commentOnIssue !== '') {
            $fields['commentOnIssue'] = $commentOnIssue;
            $fields['comment'] = $this->encode($comment);
        }

        $this->evaluate($this->request($fields, $images), $images);
    }

    /**
     * @param array<string,mixed> $document
     *
     * @throws FeedbackException
     */
    private function encode(array $document): string
    {
        $encoded = json_encode($document, JSON_INVALID_UTF8_SUBSTITUTE);

        if ($encoded === false) {
            throw FeedbackException::customTicket('document could not be encoded');
        }

        return $encoded;
    }

    /**
     * Auch ohne Bild multipart: Die Gegenstelle lehnt JSON für diese Aktion ab.
     * Von Hand statt CURLStringFile, das es erst ab PHP 8.1 gibt.
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
     * @param array<string,string> $fields
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     *
     * @return array{complete:bool,status:int,body:string,curlError:string,retryAfter:string}
     */
    private function request(array $fields, array $images): array
    {
        $result = ['complete' => false, 'status' => 0, 'body' => '', 'curlError' => '', 'retryAfter' => ''];
        $request = $this->buildMultipartBody($fields, $images);

        try {
            $curl = curl_init($this->settings->getCustomTicketUrl());
        } catch (\Throwable $exception) {
            // Nur die Klasse: Die Meldung kann die Adresse enthalten.
            $result['curlError'] = get_class($exception);

            return $result;
        }

        if ($curl === false) {
            $result['curlError'] = 'init failed';

            return $result;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request['body']);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $request['contentType'],
            'Accept: application/json',
            'X-API-Token: ' . $this->settings->getCustomTicketToken(),
            'User-Agent: tabslFeedback',
        ]);
        // Eine Weiterleitung schickte Token und Meldung an eine Adresse, die der
        // Betreiber nicht eingetragen hat.
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, static function ($handle, string $header) use (&$result): int {
            if (stripos($header, 'Retry-After:') === 0) {
                $result['retryAfter'] = trim(substr($header, strlen('Retry-After:')));
            }

            return strlen($header);
        });

        $response = curl_exec($curl);
        $result['status'] = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $result['curlError'] = curl_error($curl);
        $result['complete'] = is_string($response);
        $result['body'] = is_string($response) ? $response : '';

        return $result;
    }

    /**
     * @param array{complete:bool,status:int,body:string,curlError:string,retryAfter:string} $response
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     *
     * @throws FeedbackException
     */
    private function evaluate(array $response, array $images): void
    {
        $status = $response['status'];
        $decoded = json_decode($response['body'], true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($response['complete'] && $status === 201 && $this->isOwnIssueKey($decoded['key'] ?? null)) {
            if (is_int($decoded['attachmentsFailed'] ?? null) && $decoded['attachmentsFailed'] > 0) {
                $this->log('screenshots not attached to ' . $decoded['key'] . ': ' . $decoded['attachmentsFailed']);
            }

            return;
        }

        if ($response['complete'] && $this->isSafeRejection($status, $decoded)) {
            $this->log('ticket creation rejected — no feedback was stored, the report is lost' . $this->describe($response, $decoded, $images));

            throw FeedbackException::customTicket('ticket creation rejected');
        }

        $this->log('ticket creation got no clear answer — ticket state unknown, check before resubmitting' . $this->describe($response, $decoded, $images));

        throw FeedbackException::customTicket('ticket state unknown');
    }

    /**
     * @param mixed $key
     */
    private function isOwnIssueKey($key): bool
    {
        return is_string($key)
            && preg_match('/^' . $this->settings->getCustomTicketProject() . '-\d+$/D', $key) === 1;
    }

    /**
     * @param array<mixed> $decoded
     */
    private function isSafeRejection(int $status, array $decoded): bool
    {
        return ($status >= 300 && $status < 500)
            || ($status === 502 && ($decoded['issueStateUnknown'] ?? null) === false)
            || ($status === 503 && ($decoded['reason'] ?? null) === 'instance_not_ready');
    }

    /**
     * @param array{complete:bool,status:int,body:string,curlError:string,retryAfter:string} $response
     * @param array<mixed> $decoded
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     */
    private function describe(array $response, array $decoded, array $images): string
    {
        $description = ' — http ' . $response['status'];

        if ($response['curlError'] !== '') {
            $description .= ', curl: ' . $this->maskCurlError($response['curlError']);
        }

        if (isset($decoded['reason'])) {
            $description .= ', reason: ' . (in_array($decoded['reason'], self::KNOWN_REASONS, true) ? $decoded['reason'] : 'other');
        }

        if (is_int($decoded['jiraStatus'] ?? null)) {
            $description .= ', ticket system status: ' . $decoded['jiraStatus'];
        }

        if (isset($decoded['fields']) && is_array($decoded['fields']) && $decoded['fields'] !== []) {
            $description .= $this->describeFields($decoded['fields']);
        }

        if ($response['status'] === 429 && $this->isRetryAfterValue($response['retryAfter'])) {
            $description .= ', retry-after: ' . $response['retryAfter'];
        }

        if ($response['status'] === 400 || $response['status'] === 413) {
            $bytes = array_sum(array_map(static function (array $image): int {
                return strlen($image['bytes']);
            }, $images));

            $description .= ', images: ' . count($images) . ' (' . $bytes . ' bytes)';
        }

        return $description;
    }

    /**
     * @param array<mixed> $fields
     */
    private function describeFields(array $fields): string
    {
        $known = array_filter($fields, static function ($field): bool {
            return is_string($field)
                && (in_array($field, self::KNOWN_FIELDS, true) || preg_match('/^customfield_\d+$/D', $field) === 1);
        });

        $other = count($fields) - count($known);

        return ', fields: ' . implode(', ', $known) . ($other > 0 ? ' (+' . $other . ' other)' : '');
    }

    /**
     * Nur Sekunden oder ein HTTP-Datum — ein freier Wert könnte einen
     * zurückgespiegelten Token tragen.
     */
    private function isRetryAfterValue(string $value): bool
    {
        return preg_match('/^\d{1,10}$/D', $value) === 1
            || preg_match('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/D', $value) === 1;
    }

    /**
     * Einzeilig: Ein Zeilenumbruch ließe sich sonst als eigener Protokolleintrag
     * ausgeben. Die Masken in strtr ersetzen den längsten Treffer zuerst, die
     * Adresse also vor ihrem Host.
     */
    private function maskCurlError(string $error): string
    {
        $url = $this->settings->getCustomTicketUrl();
        $replacements = [$this->settings->getCustomTicketToken() => '[token]', $url => '[url]'];
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host !== '') {
            $replacements[$host] = '[url]';
        }

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', strtr($error, $replacements))), 0, 300);
    }

    /**
     * Es werden ausschließlich technische Angaben festgehalten — weder Token
     * noch Adresse noch Meldungstext. Die Listen und Formatprüfungen halten einen
     * rein numerischen Token nicht auf: Als Retry-After oder Zahl der Gegenstelle
     * wäre er gültig, deshalb die Maske auf der fertigen Zeile.
     */
    private function log(string $message): void
    {
        try {
            Registry::getLogger()->error('[tabslFeedback] ' . str_replace($this->settings->getCustomTicketToken(), '[token]', $message));
        } catch (\Throwable $exception) {
            // Ein fehlschlagendes Protokoll darf die Ticket-Anlage nicht kosten.
        }
    }
}
