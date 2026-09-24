<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Jira Cloud: erst den Vorgang anlegen, dann die Screenshots anhängen.
 * Fehlgeschlagene Uploads vermerkt ein Kommentar, weil die Beschreibung zu
 * diesem Zeitpunkt schon steht.
 */
class JiraTicketTarget implements TicketTargetInterface
{
    /**
     * Wie bei weclapp: Der Vorgang existiert schon, während die Screenshots
     * noch laufen. Ein Webserver-Timeout in dieser Phase zeigt dem Melder einen
     * Fehler und verleitet zum erneuten Absenden.
     */
    private const UPLOAD_BUDGET_SECONDS = 20;

    /** @var JiraService */
    private $jira;

    /** @var JiraDescriptionBuilder */
    private $description;

    /** @var MetadataCollector */
    private $metadata;

    public function __construct(
        ?ModuleSettings $settings = null,
        ?JiraService $jira = null,
        ?JiraDescriptionBuilder $description = null,
        ?MetadataCollector $metadata = null
    ) {
        $settings = $settings ?? new ModuleSettings();
        $this->jira = $jira ?? new JiraService($settings);
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

        $issueKey = $this->jira->createIssue($draft->getTitle(), $this->description->buildDescription($draft, $environment));

        $failed = 0;
        $deadline = microtime(true) + self::UPLOAD_BUDGET_SECONDS;

        foreach ($draft->getImages() as $image) {
            // Jeder Upload bekommt nur das Restbudget als Timeout — sonst könnte
            // ein einzelner, kurz vor Ablauf gestarteter Upload es weit überziehen.
            $remaining = (int) ceil($deadline - microtime(true));

            if ($remaining < 1) {
                $failed++;

                continue;
            }

            try {
                $this->jira->uploadAttachment($issueKey, $image['bytes'], $image['filename'], $image['mime'], $remaining);
            } catch (FeedbackException $exception) {
                // Ein einzelner Screenshot darf den Vorgang nicht kosten.
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->jira->addComment($issueKey, $this->description->buildUploadFailedComment($draft->getLabels(), $failed));
        }
    }
}
