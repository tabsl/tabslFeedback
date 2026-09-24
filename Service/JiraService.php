<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Kapselt die Jira-Cloud-Vorgänge (REST API v3): Vorgang anlegen, Screenshot
 * anhängen, Kommentar.
 *
 * Wie bei weclapp entsteht der Vorgang zuerst; die Bilder hängen danach am
 * Vorgang. Die Fehler sind nach Vorgang unterscheidbar (TYPE_JIRA /
 * TYPE_UPLOAD). Kein Retry.
 */
class JiraService
{
    private const API_PATH = '/rest/api/3';

    private const MAX_SUMMARY_LENGTH = 255;

    private const TIMEOUT_SECONDS = 20;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /** Der Vermerk ist entbehrlich; er darf die Antwort an den Melder kaum verzögern. */
    private const COMMENT_TIMEOUT_SECONDS = 10;

    /** @var ModuleSettings */
    private $settings;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?? new ModuleSettings();
    }

    /**
     * Legt den Vorgang an. Scheitert das, scheitert der ganze Vorgang — nur hier.
     *
     * @param array<string,mixed> $description ADF-Dokument
     *
     * @return string Vorgangs-Key für die anschließenden Anhänge
     *
     * @throws FeedbackException vom Typ JIRA oder CONFIG
     */
    public function createIssue(string $summary, array $description): string
    {
        $this->assertConfigured();

        $project = $this->settings->getJiraProject();
        $issueType = $this->settings->getJiraIssueType();

        $fields = [
            'project' => ctype_digit($project) ? ['id' => $project] : ['key' => $project],
            'issuetype' => ctype_digit($issueType) ? ['id' => $issueType] : ['name' => $issueType],
            'summary' => mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $summary)), 0, self::MAX_SUMMARY_LENGTH),
            'description' => $description,
        ];

        $accountId = $this->settings->getJiraAssigneeAccountId();

        // Ohne hinterlegte Person entfällt das Feld vollständig — Jira wendet
        // dann die im Projekt eingestellte Standardzuweisung an.
        if ($accountId !== '') {
            $fields['assignee'] = ['accountId' => $accountId];
        }

        $response = $this->request('/issue', $this->encode(['fields' => $fields]), ['Content-Type: application/json']);

        if (!$response['ok'] && $response['status'] < 300) {
            // Ohne vollständige Antwort kann Jira den Vorgang trotzdem angelegt
            // haben — auch ein Abbruch nach dem Erfolgsstatus zählt dazu.
            $this->log('ticket creation got no answer — issue state unknown, check Jira before resubmitting'
                . $this->describeFailure($response));

            throw FeedbackException::jira('issue creation got no answer');
        }

        if (!$response['ok']) {
            $this->log('ticket creation failed — no feedback was stored, the report is lost'
                . $this->describeFailure($response));

            throw FeedbackException::jira('issue creation failed');
        }

        $decoded = json_decode($response['body'], true);
        $key = is_array($decoded) && isset($decoded['key']) && is_string($decoded['key']) ? $decoded['key'] : '';

        if ($key === '') {
            $this->log('ticket creation answered without issue key — issue state unknown');

            throw FeedbackException::jira('issue creation response had no key');
        }

        return $key;
    }

    /**
     * Hängt einen Screenshot an den Vorgang.
     *
     * @throws FeedbackException vom Typ UPLOAD oder CONFIG
     */
    public function uploadAttachment(
        string $issueKey,
        string $bytes,
        string $filename,
        string $mime,
        int $timeoutSeconds = self::TIMEOUT_SECONDS
    ): void {
        $this->assertConfigured();

        $boundary = '----tabslFeedback' . bin2hex(random_bytes(16));
        $safeFilename = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        $body = '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="file"; filename="' . $safeFilename . '"' . "\r\n"
            . 'Content-Type: ' . $mime . "\r\n\r\n"
            . $bytes . "\r\n"
            . '--' . $boundary . "--\r\n";

        // Ohne diesen Header weist Jira jeden Upload als möglichen CSRF-Versuch ab.
        $response = $this->request(
            '/issue/' . rawurlencode($issueKey) . '/attachments',
            $body,
            ['Content-Type: multipart/form-data; boundary=' . $boundary, 'X-Atlassian-Token: no-check'],
            $timeoutSeconds
        );

        if (!$response['ok']) {
            $this->log('screenshot upload failed for ' . $safeFilename . $this->describeFailure($response));

            throw FeedbackException::upload('upload of ' . $safeFilename . ' failed');
        }
    }

    /**
     * Vermerk am Vorgang. Wirft nie: Der Vorgang existiert zu diesem Zeitpunkt
     * bereits, ein fehlender Vermerk darf ihn nicht in Frage stellen.
     *
     * @param array<string,mixed> $body ADF-Dokument
     */
    public function addComment(string $issueKey, array $body): void
    {
        if (!$this->settings->isJiraConfigured()) {
            return;
        }

        try {
            $payload = $this->encode(['body' => $body]);
        } catch (FeedbackException $exception) {
            return;
        }

        $response = $this->request(
            '/issue/' . rawurlencode($issueKey) . '/comment',
            $payload,
            ['Content-Type: application/json'],
            self::COMMENT_TIMEOUT_SECONDS
        );

        if (!$response['ok']) {
            $this->log('comment on issue failed' . $this->describeFailure($response));
        }
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @throws FeedbackException
     */
    private function encode(array $payload): string
    {
        $body = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);

        if ($body === false) {
            throw FeedbackException::jira('payload could not be encoded');
        }

        return $body;
    }

    /**
     * @throws FeedbackException
     */
    private function assertConfigured(): void
    {
        if (!$this->settings->isJiraConfigured()) {
            $this->log('Jira https address, email, token or project is missing or malformed');

            throw FeedbackException::config('Jira configuration is incomplete');
        }
    }

    /**
     * @param array<int,string> $headers
     *
     * @return array{ok:bool,status:int,body:string,curlError:string,retryAfter:string,rateLimitReason:string}
     */
    private function request(string $resource, string $body, array $headers, int $timeoutSeconds = self::TIMEOUT_SECONDS): array
    {
        $result = ['ok' => false, 'status' => 0, 'body' => '', 'curlError' => '', 'retryAfter' => '', 'rateLimitReason' => ''];

        $curl = curl_init($this->settings->getJiraUrl() . self::API_PATH . $resource);

        if ($curl === false) {
            $result['curlError'] = 'init failed';

            return $result;
        }

        $headers[] = 'Authorization: Basic '
            . base64_encode($this->settings->getJiraEmail() . ':' . $this->settings->getJiraToken());
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: tabslFeedback';

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, static function ($handle, string $line) use (&$result): int {
            $parts = explode(':', $line, 2);

            if (count($parts) === 2) {
                $name = strtolower(trim($parts[0]));

                if ($name === 'retry-after') {
                    $result['retryAfter'] = trim($parts[1]);
                } elseif ($name === 'ratelimit-reason') {
                    $result['rateLimitReason'] = trim($parts[1]);
                }
            }

            return strlen($line);
        });

        $response = curl_exec($curl);
        $result['status'] = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $result['curlError'] = curl_error($curl);
        curl_close($curl);

        $result['body'] = is_string($response) ? $response : '';
        $result['ok'] = is_string($response) && $result['status'] >= 200 && $result['status'] < 300;

        return $result;
    }

    /**
     * HTTP-Status mit Hinweis auf die häufigsten Ursachen, dazu die Feld-IDs
     * aus `errors` und die systemseitigen `errorMessages`. Bewusst NICHT die
     * Werte aus `errors`: Sie können eingereichte Angaben zurückspiegeln.
     *
     * @param array{ok:bool,status:int,body:string,curlError:string,retryAfter:string,rateLimitReason:string} $response
     */
    private function describeFailure(array $response): string
    {
        $hints = [
            401 => 'authentication failed — API token expired or revoked?',
            403 => 'account lacks permission in project',
            404 => 'project, issue type or issue not found',
            413 => 'attachment too large',
        ];

        $description = ' — http ' . $response['status'];

        if (isset($hints[$response['status']])) {
            $description .= ' (' . $hints[$response['status']] . ')';
        }

        if ($response['status'] === 429) {
            $description .= ' (rate limited, retry-after: ' . ($response['retryAfter'] !== '' ? $response['retryAfter'] : '?')
                . ($response['rateLimitReason'] !== '' ? ', reason: ' . $response['rateLimitReason'] : '') . ')';
        }

        if ($response['curlError'] !== '') {
            $description .= ', curl: ' . $response['curlError'];
        }

        $decoded = json_decode($response['body'], true);

        if (!is_array($decoded)) {
            return $description;
        }

        $parts = [];

        if (isset($decoded['errors']) && is_array($decoded['errors']) && $decoded['errors'] !== []) {
            $parts[] = 'fields: ' . implode(', ', array_map('strval', array_keys($decoded['errors'])));
        }

        if (isset($decoded['errorMessages']) && is_array($decoded['errorMessages'])) {
            foreach ($decoded['errorMessages'] as $message) {
                if (is_scalar($message)) {
                    $parts[] = (string) $message;
                }
            }
        }

        if ($parts === []) {
            return $description;
        }

        // Einzeilig halten: ein Zeilenumbruch im Fremdtext ließe sich sonst als
        // eigener Protokolleintrag ausgeben.
        $api = trim((string) preg_replace('/\s+/u', ' ', implode('; ', $parts)));

        return $description . ', api: ' . mb_substr($api, 0, 300);
    }

    /**
     * Es werden ausschließlich technische Angaben festgehalten — weder Token
     * noch E-Mail noch Meldungstext.
     */
    private function log(string $message): void
    {
        try {
            Registry::getLogger()->error('[tabslFeedback] ' . $message);
        } catch (\Throwable $exception) {
            // Ein fehlschlagendes Protokoll darf die Ticket-Anlage nicht kosten.
        }
    }
}
