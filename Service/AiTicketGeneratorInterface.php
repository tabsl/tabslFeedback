<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

/**
 * Vertrag der KI-Anbieter (OpenAI, Anthropic, eigener Endpunkt): aus Freitext
 * Titel und Beschreibung eines Tickets erzeugen.
 *
 * Die Bilder kommen bereits validiert und nach areScreenshotsEnabled()
 * gefiltert an. Ob ein Anbieter sie überträgt, entscheidet er selbst
 * (ADR-003).
 *
 * Wirft nie — ein Ausfall wird als null gemeldet, siehe die jeweilige
 * Implementierung.
 */
interface AiTicketGeneratorInterface
{
    /**
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     *
     * @return array{title:string,description:string}|null null = keine Aufbereitung möglich
     */
    public function generateTicket(string $message, array $images = []): ?array;
}
