<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Core\ModuleSettings;

/**
 * Eigener Endpunkt: Anlage, Screenshots und Upload-Vermerk laufen in einem
 * Aufruf, die Gegenstelle übernimmt auch den Rückfall vom Kommentar auf einen
 * neuen Vorgang. Das Format ist dasselbe ADF wie bei Jira (docs/adr/ADR-004).
 */
class CustomTicketTarget implements TicketTargetInterface
{
    /** Der Vertrag lehnt längere Keys samt der ganzen Meldung ab. */
    private const MAX_ISSUE_KEY_LENGTH = 50;

    /** @var ModuleSettings */
    private $settings;

    /** @var CustomTicketService */
    private $service;

    /** @var JiraDescriptionBuilder */
    private $description;

    /** @var MetadataCollector */
    private $metadata;

    public function __construct(
        ?ModuleSettings $settings = null,
        ?CustomTicketService $service = null,
        ?JiraDescriptionBuilder $description = null,
        ?MetadataCollector $metadata = null
    ) {
        $settings = $settings ?? new ModuleSettings();
        $this->settings = $settings;
        $this->service = $service ?? new CustomTicketService($settings);
        $this->description = $description ?? new JiraDescriptionBuilder();
        $this->metadata = $metadata ?? new MetadataCollector($settings);
    }

    public function createTicket(TicketDraft $draft): void
    {
        $environment = $this->metadata->collectEnvironment(
            $draft->getInput()->getClientMeta(),
            $draft->getContextKey(),
            $draft->getLabels()
        );

        $issueKey = $this->findPageIssueKey($draft);

        $this->service->createIssue(
            $draft->getTitle(),
            $this->description->buildDescription($draft, $environment),
            $draft->getImages(),
            $issueKey,
            $issueKey !== '' ? $this->description->buildPageIssueComment($draft, $environment) : []
        );
    }

    /**
     * Die Keys kommen aus dem Browser; die Projektbindung begrenzt, wo ein
     * Besucher kommentieren lassen kann.
     */
    private function findPageIssueKey(TicketDraft $draft): string
    {
        if (!$this->settings->isCustomTicketPageIssueCommentEnabled()
            || $draft->getContextKey() !== FeedbackService::CONTEXT_FRONTEND
        ) {
            return '';
        }

        $prefix = $this->settings->getCustomTicketProject() . '-';

        foreach ($draft->getInput()->getPageIssueKeys() as $key) {
            if (strlen($key) <= self::MAX_ISSUE_KEY_LENGTH
                && strpos($key, $prefix) === 0
                && ctype_digit(substr($key, strlen($prefix)))
            ) {
                return $key;
            }
        }

        return '';
    }
}
