<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

/**
 * Setzt die Ticket-Beschreibung für weclapp als HTML zusammen — weclapp
 * speichert die Beschreibung als HTML, das GitLab-Markdown stünde dort als
 * Rohtext.
 *
 * Jeder Wert gilt als untrusted und wird escaped: Freitext, Name, E-Mail und
 * Umgebungsangaben kommen aus dem Browser des Melders, der KI-Text ist aus dem
 * Freitext abgeleitet. Markup entsteht ausschließlich aus den festen Tags
 * dieser Klasse (siehe docs/adr/ADR-001). Bewusst ohne Tabellen, Links und
 * Bilder, weil nicht dokumentiert ist, wie weclapp HTML bereinigt.
 */
class WeclappDescriptionBuilder
{
    /**
     * @param array{title:string,description:string}|null $ticket
     * @param array{rows:array<string,string>,modules:array<string,string>} $environment
     */
    public function build(
        FeedbackInput $input,
        TicketLabels $labels,
        ?array $ticket,
        int $imageCount,
        array $environment
    ): string {
        $blocks = [];

        if ($ticket !== null && $ticket['description'] !== '') {
            $blocks[] = $this->renderAiDescription($ticket['description']);
        }

        if ($ticket === null) {
            $blocks[] = '<p><em>' . $this->escape($labels->get('no_ai')) . '</em></p>';
        }

        // Die Originalmeldung steht IMMER im Ticket — eine schlechte oder
        // ausgefallene Aufbereitung darf die Meldung nicht unbrauchbar machen.
        $blocks[] = $this->heading($labels->get('original_report'))
            . '<blockquote>' . $this->escapeMultiline($input->getMessage()) . '</blockquote>';

        $contact = $this->buildContactLine($input);

        if ($contact !== '') {
            $blocks[] = $this->heading($labels->get('contact')) . '<p>' . $contact . '</p>';
        }

        if ($imageCount > 0) {
            $blocks[] = $this->heading($labels->get('screenshots'))
                . '<p>' . $this->escape(sprintf($labels->get('screenshots_attached'), $imageCount)) . '</p>';
        }

        if ($environment['rows'] !== []) {
            $blocks[] = $this->heading($labels->get('environment')) . $this->renderRows($environment['rows']);
        }

        if ($environment['modules'] !== []) {
            $blocks[] = $this->heading($labels->get('active_modules') . ' (' . count($environment['modules']) . ')')
                . '<p>' . $this->renderModules($environment['modules']) . '</p>';
        }

        return implode("\n", $blocks);
    }

    /**
     * Die KI liefert kurze Markdown-Stichpunkte. Aufzählungszeilen werden zur
     * Liste, alles andere zu Absätzen; Hervorhebungen entfallen, statt als
     * Sternchen stehenzubleiben.
     */
    private function renderAiDescription(string $description): string
    {
        $html = '';
        $listItems = [];
        $paragraph = [];

        $flushList = function () use (&$html, &$listItems): void {
            if ($listItems !== []) {
                $html .= '<ul><li>' . implode('</li><li>', $listItems) . '</li></ul>';
                $listItems = [];
            }
        };

        $flushParagraph = function () use (&$html, &$paragraph): void {
            if ($paragraph !== []) {
                $html .= '<p>' . implode('<br>', $paragraph) . '</p>';
                $paragraph = [];
            }
        };

        $lines = preg_split('/\r\n|\r|\n/', $description);

        if ($lines === false) {
            return '<p>' . $this->escape($description) . '</p>';
        }

        foreach ($lines as $line) {
            $line = trim(str_replace('**', '', $line));

            if ($line === '') {
                $flushList();
                $flushParagraph();

                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/u', $line, $match) === 1) {
                $flushParagraph();
                $listItems[] = $this->escape($match[1]);

                continue;
            }

            $flushList();
            $paragraph[] = $this->escape($line);
        }

        $flushList();
        $flushParagraph();

        return $html;
    }

    /**
     * @param array<string,string> $rows
     */
    private function renderRows(array $rows): string
    {
        $items = [];

        foreach ($rows as $label => $value) {
            $items[] = '<li><strong>' . $this->escape($label) . ':</strong> ' . $this->escapeLine($value) . '</li>';
        }

        return '<ul>' . implode('', $items) . '</ul>';
    }

    /**
     * @param array<string,string> $modules Modul-ID => Version
     */
    private function renderModules(array $modules): string
    {
        $entries = [];

        foreach ($modules as $id => $version) {
            $entries[] = $this->escapeLine(trim($id . ' ' . $version));
        }

        return implode(', ', $entries);
    }

    private function buildContactLine(FeedbackInput $input): string
    {
        $parts = [];

        foreach ([$input->getName(), $input->getEmail()] as $value) {
            $value = $this->escapeLine($value);

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(' · ', $parts);
    }

    private function heading(string $text): string
    {
        return '<h3>' . $this->escape($text) . '</h3>';
    }

    private function escapeMultiline(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);

        if ($lines === false) {
            return $this->escape($text);
        }

        return implode('<br>', array_map([$this, 'escape'], $lines));
    }

    /**
     * Einzeilige Werte aus dem Browser (Bezug, Seite, Referrer, Kontakt):
     * Steuerzeichen würden im HTML still verschwinden oder roh im Ticket landen.
     */
    private function escapeLine(string $value): string
    {
        return $this->escape(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
