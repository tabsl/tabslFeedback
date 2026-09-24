<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * weclapp: erst das Ticket anlegen, dann die Screenshots als Dokumente
 * anhängen. Fehlgeschlagene Uploads vermerkt ein interner Kommentar, weil die
 * Beschreibung zu diesem Zeitpunkt schon steht.
 */
class WeclappTicketTarget implements TicketTargetInterface
{
    /**
     * Das Ticket existiert schon, während die Screenshots noch laufen. Bricht in
     * dieser Phase ein Webserver-Timeout (nginx: 60 s) den Request ab, sieht der
     * Melder einen Fehler und sendet erneut — ein Doppel-Ticket. Nach Ablauf des
     * Budgets gelten restliche Bilder deshalb als nicht übertragen, statt die
     * Antwort weiter hinauszuzögern.
     */
    private const UPLOAD_BUDGET_SECONDS = 20;

    /** @var WeclappService */
    private $weclapp;

    /** @var WeclappDescriptionBuilder */
    private $description;

    /** @var MetadataCollector */
    private $metadata;

    public function __construct(
        ?ModuleSettings $settings = null,
        ?WeclappService $weclapp = null,
        ?WeclappDescriptionBuilder $description = null,
        ?MetadataCollector $metadata = null
    ) {
        $settings = $settings ?? new ModuleSettings();
        $this->weclapp = $weclapp ?? new WeclappService($settings);
        $this->description = $description ?? new WeclappDescriptionBuilder();
        $this->metadata = $metadata ?? new MetadataCollector($settings);
    }

    public function createTicket(TicketDraft $draft): void
    {
        $input = $draft->getInput();
        $labels = $draft->getLabels();
        $images = $draft->getImages();

        $description = $this->description->build(
            $input,
            $labels,
            $draft->getAiTicket(),
            count($images),
            $this->metadata->collectEnvironment($input->getClientMeta(), $draft->getContextKey(), $labels)
        );

        $ticketId = $this->weclapp->createTicket($draft->getTitle(), $description);

        $failed = 0;
        $deadline = microtime(true) + self::UPLOAD_BUDGET_SECONDS;

        foreach ($images as $image) {
            // Jeder Upload bekommt nur das Restbudget als Timeout — sonst könnte
            // ein einzelner, kurz vor Ablauf gestarteter Upload es weit überziehen.
            $remaining = (int) ceil($deadline - microtime(true));

            if ($remaining < 1) {
                $failed++;

                continue;
            }

            try {
                $this->weclapp->uploadDocument($ticketId, $image['bytes'], $image['filename'], $image['mime'], $remaining);
            } catch (FeedbackException $exception) {
                // Ein einzelner Screenshot darf das Ticket nicht kosten.
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->weclapp->addInternalComment($ticketId, sprintf($labels->get('upload_failed'), $failed));
        }
    }
}
