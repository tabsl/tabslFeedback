<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

/**
 * Vertrag der KI-Anbieter (OpenAI, Anthropic): aus Freitext Titel und
 * Beschreibung eines Tickets erzeugen.
 *
 * Wirft nie — ein Ausfall wird als null gemeldet, siehe die jeweilige
 * Implementierung.
 */
interface AiTicketGeneratorInterface
{
    /**
     * @return array{title:string,description:string}|null null = keine Aufbereitung möglich
     */
    public function generateTicket(string $message): ?array;
}
