<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Kapselt die beiden benötigten GitLab-Vorgänge: Datei hochladen und Issue anlegen.
 *
 * Die Fehler sind nach Vorgang unterscheidbar (TYPE_UPLOAD / TYPE_GITLAB), damit
 * FeedbackService einen fehlgeschlagenen Screenshot anders behandeln kann als
 * eine fehlgeschlagene Issue-Anlage. Kein Retry.
 */
class GitLabService
{
    private const TIMEOUT_SECONDS = 20;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /** @var ModuleSettings */
    private $settings;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?? new ModuleSettings();
    }

    /**
     * Lädt einen Screenshot in das konfigurierte Projekt und gibt die
     * Markdown-Einbettung zurück, die GitLab dafür vorsieht.
     *
     * @throws FeedbackException vom Typ UPLOAD oder CONFIG
     */
    public function uploadFile(string $bytes, string $filename, string $mime): string
    {
        $this->assertConfigured();

        $boundary = '----tabslFeedback' . bin2hex(random_bytes(16));
        $safeFilename = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        $body = '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="file"; filename="' . $safeFilename . '"' . "\r\n"
            . 'Content-Type: ' . $mime . "\r\n\r\n"
            . $bytes . "\r\n"
            . '--' . $boundary . "--\r\n";

        // Der Multipart-Verzicht betrifft nur den EINGEHENDEN Weg
        // Browser -> Shop. Ausgehend schreibt GitLab multipart/form-data vor.
        $response = $this->request(
            $this->buildProjectUrl('uploads'),
            $body,
            ['Content-Type: multipart/form-data; boundary=' . $boundary],
            201
        );

        if ($response === null) {
            $this->log('error', 'screenshot upload failed for ' . $safeFilename);

            throw FeedbackException::upload('upload of ' . $safeFilename . ' failed');
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !isset($decoded['markdown']) || !is_string($decoded['markdown'])) {
            throw FeedbackException::upload('upload response for ' . $safeFilename . ' had no markdown reference');
        }

        return $decoded['markdown'];
    }

    /**
     * Legt das Issue an. Scheitert das, scheitert der ganze Vorgang — nur hier.
     *
     * @throws FeedbackException vom Typ GITLAB oder CONFIG
     */
    public function createIssue(string $title, string $description): void
    {
        $this->assertConfigured();

        $payload = [
            'title' => $title,
            'description' => $description,
        ];

        $assigneeId = $this->settings->getGitLabAssigneeId();

        // Ohne hinterlegten Bearbeiter entfällt das Feld vollständig statt eine
        // leere oder 0-Zuweisung zu senden.
        if ($assigneeId !== '' && ctype_digit($assigneeId)) {
            $payload['assignee_ids'] = [(int) $assigneeId];
        }

        $body = json_encode($payload);

        if ($body === false) {
            throw FeedbackException::gitlab('issue payload could not be encoded');
        }

        $response = $this->request(
            $this->buildProjectUrl('issues'),
            $body,
            ['Content-Type: application/json'],
            201
        );

        if ($response === null) {
            $this->log('error', 'issue creation failed — no feedback was stored, the report is lost');

            throw FeedbackException::gitlab('issue creation failed');
        }
    }

    /**
     * @throws FeedbackException
     */
    private function assertConfigured(): void
    {
        if (!$this->settings->isGitLabConfigured()) {
            $this->log('error', 'GitLab url, project id or token is missing or malformed');

            throw FeedbackException::config('GitLab url, project id or token is missing');
        }

        if ($this->settings->usesUnencryptedGitLabUrl()) {
            $this->log('warning', 'GitLab address uses http — the access token is transmitted unencrypted');
        }
    }

    /**
     * Fehler beim Zielsystem sind für den Melder bewusst unsichtbar. Ohne
     * Protokoll hätte der Betreiber jedoch keine Möglichkeit zu erkennen, warum
     * keine Tickets ankommen. Es werden ausschließlich technische Angaben
     * festgehalten — weder Token noch Meldungstext noch Kontaktangaben.
     */
    private function log(string $level, string $message): void
    {
        try {
            Registry::getLogger()->{$level}('[tabslFeedback] ' . $message);
        } catch (\Throwable $exception) {
            // Ein fehlschlagendes Protokoll darf die Ticket-Anlage nicht kosten.
        }
    }

    private function buildProjectUrl(string $resource): string
    {
        return $this->settings->getGitLabUrl()
            . '/api/v4/projects/' . rawurlencode($this->settings->getGitLabProjectId())
            . '/' . $resource;
    }

    /**
     * @param array<int,string> $headers
     *
     * @return string|null null = Fehlerstatus, Zeitüberschreitung oder Verbindungsfehler
     */
    private function request(string $url, string $body, array $headers, int $expectedStatus): ?string
    {
        $curl = curl_init($url);

        if ($curl === false) {
            return null;
        }

        $headers[] = 'PRIVATE-TOKEN: ' . $this->settings->getGitLabToken();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($response) || $status !== $expectedStatus) {
            return null;
        }

        return $response;
    }
}
