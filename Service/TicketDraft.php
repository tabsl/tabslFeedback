<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

/**
 * Was FeedbackService vor der Zielwahl ermittelt hat — für alle Ziele dieselbe
 * Eingabe.
 */
class TicketDraft
{
    /** @var string */
    private $title;

    /** @var array{title:string,description:string}|null null = keine KI-Aufbereitung */
    private $aiTicket;

    /** @var FeedbackInput */
    private $input;

    /** @var string context_admin|context_frontend */
    private $contextKey;

    /** @var array<int,array{bytes:string,mime:string,filename:string}> */
    private $images;

    /** @var TicketLabels */
    private $labels;

    /**
     * @param array{title:string,description:string}|null $aiTicket
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images bereits nach dem Screenshot-Schalter gefiltert
     */
    public function __construct(
        string $title,
        ?array $aiTicket,
        FeedbackInput $input,
        string $contextKey,
        array $images,
        TicketLabels $labels
    ) {
        $this->title = $title;
        $this->aiTicket = $aiTicket;
        $this->input = $input;
        $this->contextKey = $contextKey;
        $this->images = $images;
        $this->labels = $labels;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array{title:string,description:string}|null
     */
    public function getAiTicket(): ?array
    {
        return $this->aiTicket;
    }

    /**
     * Bilder nie über FeedbackInput::getImages() lesen, sondern über
     * getImages() dieses Entwurfs — nur dort ist der Screenshot-Schalter
     * berücksichtigt.
     */
    public function getInput(): FeedbackInput
    {
        return $this->input;
    }

    public function getContextKey(): string
    {
        return $this->contextKey;
    }

    /**
     * @return array<int,array{bytes:string,mime:string,filename:string}>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    public function getLabels(): TicketLabels
    {
        return $this->labels;
    }
}
