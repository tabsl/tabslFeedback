<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Fachlicher Kern: führt Freitext, Screenshots und Umgebungsdaten zu einem
 * GitLab-Issue oder weclapp-Ticket zusammen.
 *
 * Beide Formulare nutzen diesen Service, damit Backend und Frontend garantiert
 * dasselbe Ticket-Format erzeugen. Hier — und nur hier — ist festgelegt, welcher
 * Ausfall den Vorgang beendet, für beide Ziele gleich:
 *
 *   KI nicht verfügbar   -> Ersatztitel, Vermerk im Ticket, weiter
 *   Screenshot-Upload    -> Vermerk im Ticket, übrige Bilder und Ticket laufen weiter
 *   Ticket-Anlage        -> Abbruch, Fehler an den Aufrufer
 *
 * Bei weclapp entsteht das Ticket vor den Uploads; der Upload-Vermerk kommt
 * deshalb als interner Kommentar statt in die Beschreibung.
 */
class FeedbackService
{
    private const MAX_FALLBACK_TITLE_LENGTH = 120;

    /**
     * Bei weclapp existiert das Ticket schon, während die Screenshots noch
     * laufen. Bricht in dieser Phase ein Webserver-Timeout (nginx: 60 s) den
     * Request ab, sieht der Melder einen Fehler und sendet erneut — ein
     * Doppel-Ticket. Nach Ablauf des Budgets gelten restliche Bilder deshalb
     * als nicht übertragen, statt die Antwort weiter hinauszuzögern.
     */
    private const WECLAPP_UPLOAD_BUDGET_SECONDS = 20;

    public const CONTEXT_ADMIN = 'context_admin';

    public const CONTEXT_FRONTEND = 'context_frontend';

    /** @var ModuleSettings */
    private $settings;

    /** @var AiTicketGeneratorInterface|null null = Betreiber hat „ohne KI" gewählt */
    private $aiService;

    /** @var GitLabService */
    private $gitLab;

    /** @var MetadataCollector */
    private $metadata;

    /** @var WeclappService */
    private $weclapp;

    /** @var WeclappDescriptionBuilder */
    private $weclappDescription;

    public function __construct(
        ?ModuleSettings $settings = null,
        ?AiTicketGeneratorInterface $aiService = null,
        ?GitLabService $gitLab = null,
        ?MetadataCollector $metadata = null,
        ?WeclappService $weclapp = null,
        ?WeclappDescriptionBuilder $weclappDescription = null
    ) {
        $this->settings = $settings ?? new ModuleSettings();
        $this->aiService = $aiService ?? $this->resolveAiService($this->settings);
        $this->gitLab = $gitLab ?? new GitLabService($this->settings);
        $this->metadata = $metadata ?? new MetadataCollector($this->settings);
        $this->weclapp = $weclapp ?? new WeclappService($this->settings);
        $this->weclappDescription = $weclappDescription ?? new WeclappDescriptionBuilder();
    }

    /**
     * @param string $contextKey self::CONTEXT_ADMIN|self::CONTEXT_FRONTEND
     *
     * @throws FeedbackException vom Typ GITLAB, WECLAPP oder CONFIG — nur diese beenden den Vorgang
     */
    public function submit(FeedbackInput $input, string $contextKey): void
    {
        $labels = new TicketLabels($this->settings->getTicketLanguage());

        $ticket = $this->aiService !== null ? $this->aiService->generateTicket($input->getMessage()) : null;
        $title = $this->resolveTitle($ticket, $input, $labels);

        $images = $this->settings->areScreenshotsEnabled() ? $input->getImages() : [];

        if ($this->settings->getTicketTarget() === ModuleSettings::TICKET_TARGET_WECLAPP) {
            $this->submitToWeclapp($input, $contextKey, $labels, $ticket, $title, $images);

            return;
        }

        $this->submitToGitLab($input, $contextKey, $labels, $ticket, $title, $images);
    }

    /**
     * @param array{title:string,description:string}|null $ticket
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     */
    private function submitToGitLab(
        FeedbackInput $input,
        string $contextKey,
        TicketLabels $labels,
        ?array $ticket,
        string $title,
        array $images
    ): void {
        $uploads = $this->uploadScreenshots($images);

        $description = $this->buildDescription($input, $contextKey, $labels, $ticket, $uploads);

        $this->gitLab->createIssue($title, $description);
    }

    /**
     * @param array{title:string,description:string}|null $ticket
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     */
    private function submitToWeclapp(
        FeedbackInput $input,
        string $contextKey,
        TicketLabels $labels,
        ?array $ticket,
        string $title,
        array $images
    ): void {
        $description = $this->weclappDescription->build(
            $input,
            $labels,
            $ticket,
            count($images),
            $this->metadata->collectEnvironment($input->getClientMeta(), $contextKey, $labels)
        );

        $ticketId = $this->weclapp->createTicket($title, $description);

        $failed = 0;
        $deadline = microtime(true) + self::WECLAPP_UPLOAD_BUDGET_SECONDS;

        foreach ($images as $image) {
            if (microtime(true) > $deadline) {
                $failed++;

                continue;
            }

            try {
                $this->weclapp->uploadDocument($ticketId, $image['bytes'], $image['filename'], $image['mime']);
            } catch (FeedbackException $exception) {
                // Ein einzelner Screenshot darf das Ticket nicht kosten.
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->weclapp->addInternalComment($ticketId, sprintf($labels->get('upload_failed'), $failed));
        }
    }

    private function resolveAiService(ModuleSettings $settings): ?AiTicketGeneratorInterface
    {
        switch ($settings->getAiProvider()) {
            case ModuleSettings::AI_PROVIDER_OPENAI:
                return new OpenAiService($settings);
            case ModuleSettings::AI_PROVIDER_ANTHROPIC:
                return new AnthropicService($settings);
            default:
                return null;
        }
    }

    /**
     * @param array{title:string,description:string}|null $ticket
     */
    private function resolveTitle(?array $ticket, FeedbackInput $input, TicketLabels $labels): string
    {
        if ($ticket !== null) {
            return $ticket['title'];
        }

        // Ohne KI-Aufbereitung ist das Betreff-Feld eingeblendet — trägt der
        // Melder dort etwas ein, ist das ein besserer Titel als die erste Zeile
        // des Freitexts.
        if ($input->getSubject() !== '') {
            return $input->getSubject();
        }

        return $this->buildFallbackTitle($input->getMessage(), $labels);
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
                // Ein einzelner Screenshot darf das Ticket nicht kosten.
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
        // ausgefallene Aufbereitung darf die Meldung nicht unbrauchbar machen.
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
