<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Fachlicher Kern: bereitet Freitext, Titel und Screenshots zu einem Entwurf auf
 * und übergibt ihn an das gewählte Ticket-Ziel.
 *
 * Beide Formulare nutzen diesen Service, damit Backend und Frontend garantiert
 * dasselbe Ticket erzeugen. Hier ist festgelegt, dass ein Ausfall der KI den
 * Vorgang nie beendet (Ersatztitel, Vermerk im Ticket). Welcher Ausfall beim
 * Ticket-Ziel den Vorgang beendet, ist Vertrag von TicketTargetInterface.
 */
class FeedbackService
{
    private const MAX_FALLBACK_TITLE_LENGTH = 120;

    public const CONTEXT_ADMIN = 'context_admin';

    public const CONTEXT_FRONTEND = 'context_frontend';

    /** @var ModuleSettings */
    private $settings;

    /** @var AiTicketGeneratorInterface|null null = Betreiber hat „ohne KI" gewählt */
    private $aiService;

    /** @var TicketTargetInterface */
    private $ticketTarget;

    public function __construct(
        ?ModuleSettings $settings = null,
        ?AiTicketGeneratorInterface $aiService = null,
        ?TicketTargetInterface $ticketTarget = null
    ) {
        $this->settings = $settings ?? new ModuleSettings();
        $this->aiService = $aiService ?? $this->resolveAiService($this->settings);
        $this->ticketTarget = $ticketTarget ?? $this->resolveTicketTarget($this->settings);
    }

    /**
     * @param string $contextKey self::CONTEXT_ADMIN|self::CONTEXT_FRONTEND
     *
     * @throws FeedbackException vom Typ GITLAB, WECLAPP, JIRA oder CONFIG — nur diese beenden den Vorgang
     */
    public function submit(FeedbackInput $input, string $contextKey): void
    {
        $labels = new TicketLabels($this->settings->getTicketLanguage());

        $ticket = $this->aiService !== null ? $this->aiService->generateTicket($input->getMessage()) : null;
        $title = $this->resolveTitle($ticket, $input, $labels);

        $images = $this->settings->areScreenshotsEnabled() ? $input->getImages() : [];

        $this->ticketTarget->createTicket(new TicketDraft($title, $ticket, $input, $contextKey, $images, $labels));
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

    private function resolveTicketTarget(ModuleSettings $settings): TicketTargetInterface
    {
        switch ($settings->getTicketTarget()) {
            case ModuleSettings::TICKET_TARGET_WECLAPP:
                return new WeclappTicketTarget($settings);
            case ModuleSettings::TICKET_TARGET_JIRA:
                return new JiraTicketTarget($settings);
            default:
                return new GitLabTicketTarget($settings);
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
}
