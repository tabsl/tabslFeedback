<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Jira Cloud: erst den Vorgang anlegen, dann die Screenshots anhängen.
 * Fehlgeschlagene Uploads vermerkt ein Kommentar, weil die Beschreibung zu
 * diesem Zeitpunkt schon steht.
 *
 * Verweist die Seite auf einen Vorgang des eingestellten Projekts und ist das
 * Kommentieren dort eingeschaltet, wird die Meldung stattdessen Kommentar an
 * diesem Vorgang. Lehnt Jira das ab, entsteht wie sonst ein neuer Vorgang.
 */
class JiraTicketTarget implements TicketTargetInterface
{
    /**
     * Wie bei weclapp: Der Vorgang existiert schon, während die Screenshots
     * noch laufen. Ein Webserver-Timeout in dieser Phase zeigt dem Melder einen
     * Fehler und verleitet zum erneuten Absenden.
     */
    private const UPLOAD_BUDGET_SECONDS = 20;

    /** @var ModuleSettings */
    private $settings;

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
        $this->settings = $settings;
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

        $issueKey = $this->findPageIssueKey($draft);

        if ($issueKey !== ''
            && $this->jira->commentOnIssue($issueKey, $this->description->buildPageIssueComment($draft, $environment))
        ) {
            // Am bestehenden Vorgang hängen schon Anhänge früherer Meldungen —
            // der Zeitstempel ordnet die Screenshots ihrem Kommentar zu.
            $this->uploadImages($issueKey, $draft, 'feedback-' . date('Ymd-His') . '-');

            return;
        }

        $issueKey = $this->jira->createIssue($draft->getTitle(), $this->description->buildDescription($draft, $environment));

        $this->uploadImages($issueKey, $draft, '');
    }

    /**
     * Der erste Vorgang des eingestellten Projekts in Reihenfolge des
     * Quelltexts. Die Keys kommen aus dem Browser; die Projektbindung begrenzt,
     * wo ein Besucher kommentieren lassen kann.
     */
    private function findPageIssueKey(TicketDraft $draft): string
    {
        if (!$this->settings->isJiraPageIssueCommentEnabled()
            || $draft->getContextKey() !== FeedbackService::CONTEXT_FRONTEND
        ) {
            return '';
        }

        $prefix = $this->settings->getJiraProject() . '-';

        foreach ($draft->getInput()->getPageIssueKeys() as $key) {
            if (strpos($key, $prefix) === 0 && ctype_digit(substr($key, strlen($prefix)))) {
                return $key;
            }
        }

        return '';
    }

    private function uploadImages(string $issueKey, TicketDraft $draft, string $filenamePrefix): void
    {
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
                $this->jira->uploadAttachment(
                    $issueKey,
                    $image['bytes'],
                    $filenamePrefix . $image['filename'],
                    $image['mime'],
                    $remaining
                );
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
