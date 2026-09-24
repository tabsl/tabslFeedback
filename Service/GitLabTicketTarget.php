<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Core\ModuleSettings;
use Tabsl\Feedback\Exception\FeedbackException;

/**
 * GitLab: erst die Screenshots hochladen, dann das Issue mit eingebetteten
 * Bildern anlegen. Fehlgeschlagene Uploads stehen als Vermerk in der
 * Beschreibung.
 */
class GitLabTicketTarget implements TicketTargetInterface
{
    /** @var GitLabService */
    private $gitLab;

    /** @var GitLabDescriptionBuilder */
    private $description;

    /** @var MetadataCollector */
    private $metadata;

    public function __construct(
        ?ModuleSettings $settings = null,
        ?GitLabService $gitLab = null,
        ?GitLabDescriptionBuilder $description = null,
        ?MetadataCollector $metadata = null
    ) {
        $settings = $settings ?? new ModuleSettings();
        $this->gitLab = $gitLab ?? new GitLabService($settings);
        $this->description = $description ?? new GitLabDescriptionBuilder();
        $this->metadata = $metadata ?? new MetadataCollector($settings);
    }

    public function createTicket(TicketDraft $draft): void
    {
        $uploads = $this->uploadScreenshots($draft->getImages());

        $environment = $this->metadata->buildEnvironmentSection(
            $draft->getInput()->getClientMeta(),
            $draft->getContextKey(),
            $draft->getLabels()
        );

        $this->gitLab->createIssue($draft->getTitle(), $this->description->build($draft, $uploads, $environment));
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
}
