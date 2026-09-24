<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Vertrag der Ticket-Ziele (GitLab, weclapp, Jira): aus einem Entwurf ein
 * Ticket anlegen. Die Reihenfolge von Anlage und Screenshot-Upload bestimmt das
 * Ziel selbst.
 *
 * Fehlerpolitik, für jede Implementierung verbindlich (docs/adr/ADR-002):
 *
 *   Anlage nicht bestätigt       -> FeedbackException, nur hier
 *   Screenshot-Upload scheitert  -> kein Wurf; zählen und am Ticket vermerken
 *   Vermerk scheitert            -> nur protokollieren
 *
 * Alle Werte aus Browser und KI gelten als untrusted und werden nach der Regel
 * des Zielformats escaped.
 */
interface TicketTargetInterface
{
    /**
     * @throws FeedbackException vom Zieltyp oder CONFIG — nur wenn die Anlage nicht bestätigt werden konnte
     */
    public function createTicket(TicketDraft $draft): void;
}
