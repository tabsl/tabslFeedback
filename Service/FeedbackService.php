<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Fachlicher Kern: führt Freitext, Screenshots und Umgebungsdaten zu einem
 * GitLab-Issue zusammen.
 *
 * Beide Formulare nutzen diesen Service, damit Backend und Frontend garantiert
 * dasselbe Ticket-Format erzeugen. Hier — und nur hier — ist festgelegt, welcher
 * Ausfall den Vorgang beendet:
 *
 *   KI nicht verfügbar   -> Ersatztitel, Vermerk im Issue, weiter
 *   Screenshot-Upload    -> Vermerk im Issue, übrige Bilder und Issue laufen weiter
 *   Issue-Anlage         -> Abbruch, Fehler an den Aufrufer
 */
class FeedbackService
{
    private const MAX_FALLBACK_TITLE_LENGTH = 120;

    public const CONTEXT_ADMIN = 'context_admin';

    public const CONTEXT_FRONTEND = 'context_frontend';

    /** @var ModuleSettings */
    private $settings;

    /** @var OpenAiService */
    private $openAi;

    /** @var GitLabService */
    private $gitLab;

    /** @var MetadataCollector */
    private $metadata;

    public function __construct(
        ?ModuleSettings $settings = null,
        ?OpenAiService $openAi = null,
        ?GitLabService $gitLab = null,
        ?MetadataCollector $metadata = null
    ) {
        $this->settings = $settings ?? new ModuleSettings();
        $this->openAi = $openAi ?? new OpenAiService($this->settings);
        $this->gitLab = $gitLab ?? new GitLabService($this->settings);
        $this->metadata = $metadata ?? new MetadataCollector($this->settings);
    }

    /**
     * @param string $contextKey self::CONTEXT_ADMIN|self::CONTEXT_FRONTEND
     *
     * @throws FeedbackException vom Typ GITLAB oder CONFIG — nur diese beenden den Vorgang
     */
    public function submit(FeedbackInput $input, string $contextKey): void
    {
        $labels = new TicketLabels($this->settings->getTicketLanguage());

        $ticket = $this->openAi->generateTicket($input->getMessage());
        $title = $ticket !== null
            ? $ticket['title']
            : $this->buildFallbackTitle($input->getMessage(), $labels);

        $uploads = $this->uploadScreenshots($input->getImages());

        $description = $this->buildDescription($input, $contextKey, $labels, $ticket, $uploads);

        $this->gitLab->createIssue($title, $description);
    }

    /**
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     *
     * @return array{references:array<int,string>,failed:int}
     */
    private function uploadScreenshots(array $images): array
    {
        $references = [];
        $failed = 0;

        foreach ($images as $image) {
            try {
                $references[] = $this->gitLab->uploadFile($image['bytes'], $image['filename'], $image['mime']);
            } catch (FeedbackException $exception) {
                // Ein einzelner Screenshot darf das Ticket nicht kosten (B2).
                $failed++;
            }
        }

        return ['references' => $references, 'failed' => $failed];
    }

    /**
     * @param array{title:string,description:string}|null $ticket
     * @param array{references:array<int,string>,failed:int} $uploads
     */
    private function buildDescription(
        FeedbackInput $input,
        string $contextKey,
        TicketLabels $labels,
        ?array $ticket,
        array $uploads
    ): string {
        $blocks = [];

        if ($ticket !== null && $ticket['description'] !== '') {
            $blocks[] = $ticket['description'];
        }

        if ($ticket === null) {
            $blocks[] = '_' . $labels->get('no_ai') . '_';
        }

        // Die Originalmeldung steht IMMER im Issue — eine schlechte oder
        // ausgefallene Aufbereitung darf die Meldung nicht unbrauchbar machen (B1).
        $blocks[] = '## ' . $labels->get('original_report') . "\n\n" . $this->asBlockquote($input->getMessage());

        $contact = $this->buildContactLine($input);

        if ($contact !== '') {
            $blocks[] = '## ' . $labels->get('contact') . "\n\n" . $contact;
        }

        $screenshots = $this->buildScreenshotSection($labels, $uploads);

        if ($screenshots !== '') {
            $blocks[] = $screenshots;
        }

        $environment = $this->metadata->buildEnvironmentSection($input->getClientMeta(), $contextKey, $labels);

        if ($environment !== '') {
            $blocks[] = $environment;
        }

        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * Name und E-Mail sind frei eingegebene Werte und werden als Code gesetzt.
     * Andernfalls ließe sich als „Name" ein Markdown-Link hinterlegen, der im
     * Issue wie eine harmlose Kontaktangabe aussieht.
     */
    private function buildContactLine(FeedbackInput $input): string
    {
        $parts = [];

        foreach ([$input->getName(), $input->getEmail()] as $value) {
            $value = trim((string) preg_replace('/[\x00-\x1F\x7F`]+/u', ' ', $value));

            if ($value !== '') {
                $parts[] = '`' . $value . '`';
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * @param array{references:array<int,string>,failed:int} $uploads
     */
    private function buildScreenshotSection(TicketLabels $labels, array $uploads): string
    {
        if ($uploads['references'] === [] && $uploads['failed'] === 0) {
            return '';
        }

        $section = '## ' . $labels->get('screenshots') . "\n";

        foreach ($uploads['references'] as $reference) {
            $section .= "\n" . $reference . "\n";
        }

        if ($uploads['failed'] > 0) {
            $section .= "\n_" . sprintf($labels->get('upload_failed'), $uploads['failed']) . "_\n";
        }

        return rtrim($section);
    }

    /**
     * Ersatztitel, wenn keine KI-Aufbereitung vorliegt: die erste Zeile des
     * Rohtexts, gekürzt. Besser als ein generischer Titel, weil die Issue-Liste
     * dann immer noch unterscheidbar bleibt.
     */
    private function buildFallbackTitle(string $message, TicketLabels $labels): string
    {
        $firstLine = trim((string) strtok($message, "\r\n"));
        $firstLine = trim((string) preg_replace('/\s+/u', ' ', $firstLine));

        if ($firstLine === '') {
            return $labels->get('fallback_title');
        }

        if (mb_strlen($firstLine) <= self::MAX_FALLBACK_TITLE_LENGTH) {
            return $firstLine;
        }

        return mb_substr($firstLine, 0, self::MAX_FALLBACK_TITLE_LENGTH - 1) . '…';
    }

    private function asBlockquote(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);

        if ($lines === false) {
            return '> ' . $text;
        }

        return implode("\n", array_map(static function (string $line): string {
            return rtrim('> ' . $line);
        }, $lines));
    }
}
