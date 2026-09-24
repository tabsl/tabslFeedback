<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Kapselt die weclapp-Vorgänge: Ticket anlegen, Screenshot als Dokument
 * anhängen, interner Kommentar.
 *
 * Anders als bei GitLab entsteht das Ticket zuerst; die Bilder hängen danach
 * am Ticket. Die Fehler sind nach Vorgang unterscheidbar (TYPE_WECLAPP /
 * TYPE_UPLOAD), damit WeclappTicketTarget einen fehlgeschlagenen Screenshot anders
 * behandeln kann als eine fehlgeschlagene Ticket-Anlage. Kein Retry.
 */
class WeclappService
{
    private const API_PATH = '/webapp/api/v2';

    private const MAX_SUBJECT_LENGTH = 150;

    /**
     * weclapp stellt Anfragen bei Last bis zu 30 Sekunden in eine Warteschlange
     * und empfiehlt deshalb 60 Sekunden. Der Melder wartet aber synchron — mehr
     * als die Warteschlangenzeit verzögert bei Überlast nur die Fehlermeldung.
     */
    private const TIMEOUT_SECONDS = 30;

    /**
     * Wartet eine Anfrage länger in der weclapp-Warteschlange, lehnt weclapp sie
     * mit 429 ab, bevor die Verarbeitung beginnt. Ohne diese Grenze könnte ein
     * Ticket noch entstehen, nachdem das Modul aufgegeben hat — der Melder sähe
     * einen Fehler und sendete erneut.
     */
    private const QUEUE_WAIT_MILLISECONDS = 10000;

    /** Der Vermerk ist entbehrlich; er darf die Antwort an den Melder kaum verzögern. */
    private const COMMENT_TIMEOUT_SECONDS = 10;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /** @var ModuleSettings */
    private $settings;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?? new ModuleSettings();
    }

    /**
     * Legt das Ticket an. Scheitert das, scheitert der ganze Vorgang — nur hier.
     *
     * @return string Ticket-ID für die anschließenden Anhänge
     *
     * @throws FeedbackException vom Typ WECLAPP oder CONFIG
     */
    public function createTicket(string $subject, string $htmlDescription): string
    {
        $this->assertConfigured();

        $payload = [
            'subject' => mb_substr($subject, 0, self::MAX_SUBJECT_LENGTH),
            'description' => $htmlDescription,
        ];

        // Nicht gesetzte IDs entfallen vollständig — weclapp setzt dann die im
        // Mandanten hinterlegte Voreinstellung.
        $optionalIds = [
            'ticketStatusId' => $this->settings->getWeclappTicketStatusId(),
            'ticketPriorityId' => $this->settings->getWeclappTicketPriorityId(),
            'ticketChannelId' => $this->settings->getWeclappTicketChannelId(),
            'ticketCategoryId' => $this->settings->getWeclappTicketCategoryId(),
            'assignedUserId' => $this->settings->getWeclappAssigneeId(),
        ];

        foreach ($optionalIds as $field => $id) {
            if ($id !== '') {
                $payload[$field] = $id;
            }
        }

        $body = json_encode($payload);

        if ($body === false) {
            throw FeedbackException::weclapp('ticket payload could not be encoded');
        }

        $response = $this->request('/ticket', $body, 'application/json');

        if (!$response['ok'] && $response['status'] < 300) {
            // Ohne vollständige Antwort (Zeitüberschreitung, abgebrochene
            // Verbindung, auch nach dem Erfolgsstatus) kann weclapp das Ticket
            // trotzdem angelegt haben — die Warteschlange arbeitet weiter, wenn
            // der Client schon aufgegeben hat.
            $this->log('error', 'ticket creation got no answer — ticket state unknown, check weclapp before resubmitting'
                . $this->describeFailure($response));

            throw FeedbackException::weclapp('ticket creation got no answer');
        }

        if (!$response['ok']) {
            $this->log('error', 'ticket creation failed — no feedback was stored, the report is lost'
                . $this->describeFailure($response));

            throw FeedbackException::weclapp('ticket creation failed');
        }

        $decoded = json_decode($response['body'], true);
        $ticketId = is_array($decoded) && isset($decoded['id']) && is_scalar($decoded['id'])
            ? (string) $decoded['id']
            : '';

        if ($ticketId === '') {
            $this->log('error', 'ticket creation answered without ticket id — ticket state unknown');

            throw FeedbackException::weclapp('ticket creation response had no id');
        }

        return $ticketId;
    }

    /**
     * Hängt einen Screenshot als Dokument an das Ticket.
     *
     * @throws FeedbackException vom Typ UPLOAD oder CONFIG
     */
    public function uploadDocument(
        string $ticketId,
        string $bytes,
        string $filename,
        string $mime,
        int $timeoutSeconds = self::TIMEOUT_SECONDS
    ): void {
        $this->assertConfigured();

        $safeFilename = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        $query = http_build_query([
            'entityName' => 'ticket',
            'entityId' => $ticketId,
            'name' => $safeFilename,
        ], '', '&', PHP_QUERY_RFC3986);

        // Roher Body mit dem Bildtyp als Content-Type: bei multipart speichert
        // weclapp "multipart/form-data" als Medientyp des Dokuments.
        $response = $this->request('/document/upload?' . $query, $bytes, $mime, $timeoutSeconds);

        if (!$response['ok']) {
            $this->log('error', 'screenshot upload failed for ' . $safeFilename . $this->describeFailure($response));

            throw FeedbackException::upload('upload of ' . $safeFilename . ' failed');
        }
    }

    /**
     * Interner Vermerk am Ticket. Wirft nie: Das Ticket existiert zu diesem
     * Zeitpunkt bereits, ein fehlender Vermerk darf es nicht in Frage stellen.
     */
    public function addInternalComment(string $ticketId, string $comment): void
    {
        if (!$this->settings->isWeclappConfigured()) {
            return;
        }

        $body = json_encode([
            'entityName' => 'ticket',
            'entityId' => $ticketId,
            'comment' => $comment,
            // Alle drei sind im Schema Pflicht; ob weclapp für fehlende
            // Werte einen Default setzt, ist nicht dokumentiert.
            'publicComment' => false,
            'privateComment' => false,
            'solution' => false,
        ]);

        if ($body === false) {
            return;
        }

        $response = $this->request('/comment', $body, 'application/json', self::COMMENT_TIMEOUT_SECONDS);

        if (!$response['ok']) {
            $this->log('error', 'internal comment on ticket failed' . $this->describeFailure($response));
        }
    }

    /**
     * @throws FeedbackException
     */
    private function assertConfigured(): void
    {
        if (!$this->settings->isWeclappConfigured()) {
            $this->log('error', 'weclapp https address or api token is missing or malformed');

            throw FeedbackException::config('weclapp address or token is missing');
        }
    }

    /**
     * @return array{ok:bool,status:int,body:string,curlError:string}
     */
    private function request(
        string $resource,
        string $body,
        string $contentType,
        int $timeoutSeconds = self::TIMEOUT_SECONDS
    ): array
    {
        $curl = curl_init($this->settings->getWeclappUrl() . self::API_PATH . $resource);

        if ($curl === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'curlError' => 'init failed'];
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'AuthenticationToken: ' . $this->settings->getWeclappToken(),
            'Accept: application/json',
            'Content-Type: ' . $contentType,
            'User-Agent: tabslFeedback',
            'X-Weclapp-Wait-Timeout-Ms: ' . self::QUEUE_WAIT_MILLISECONDS,
        ]);
        // weclapp komprimiert auch ohne Anforderung; leer = cURL dekomprimiert
        // jedes unterstützte Verfahren.
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeoutSeconds);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        // Die Spec nennt für die Anlage 201, für den Upload 200 — jeder 2xx gilt als Erfolg.
        return [
            'ok' => is_string($response) && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_string($response) ? $response : '',
            'curlError' => $curlError,
        ];
    }

    /**
     * HTTP-Status und — bei einer RFC-7807-Antwort — Titel sowie Feld und Regel
     * jedes Validierungsfehlers. Daran sieht der Betreiber etwa, welche
     * Ticket-ID der Mandant verlangt. Bewusst NICHT "detail": der Freitext
     * kann eingereichte Werte zurückspiegeln.
     *
     * @param array{ok:bool,status:int,body:string,curlError:string} $response
     */
    private function describeFailure(array $response): string
    {
        $description = ' — http ' . $response['status'];

        if ($response['curlError'] !== '') {
            $description .= ', curl: ' . $response['curlError'];
        }

        $decoded = json_decode($response['body'], true);

        if (!is_array($decoded)) {
            return $description;
        }

        $parts = [];

        if (isset($decoded['title']) && is_scalar($decoded['title'])) {
            $parts[] = (string) $decoded['title'];
        }

        if (isset($decoded['validationErrors']) && is_array($decoded['validationErrors'])) {
            foreach ($decoded['validationErrors'] as $error) {
                if (!is_array($error)) {
                    continue;
                }

                $location = isset($error['location']) && is_scalar($error['location']) ? (string) $error['location'] : '?';
                $rule = isset($error['type']) && is_scalar($error['type'])
                    ? (string) preg_replace('#^.*/#', '', (string) $error['type'])
                    : '';

                // errorCode (etwa platform.unknown_property) nennt die Ursache
                // auch dann, wenn location fehlt, und spiegelt keine Werte.
                $code = isset($error['errorCode']) && is_scalar($error['errorCode']) ? (string) $error['errorCode'] : '';

                $parts[] = $location . ($rule !== '' ? ' (' . $rule . ')' : '') . ($code !== '' ? ' [' . $code . ']' : '');
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
     * noch Meldungstext noch Kontaktangaben.
     */
    private function log(string $level, string $message): void
    {
        try {
            Registry::getLogger()->{$level}('[tabslFeedback] ' . $message);
        } catch (\Throwable $exception) {
            // Ein fehlschlagendes Protokoll darf die Ticket-Anlage nicht kosten.
        }
    }
}
