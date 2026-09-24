<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

/**
 * Setzt die Issue-Beschreibung für GitLab als Markdown zusammen.
 *
 * Freitext und KI-Text stehen als Codeblock, Name und E-Mail als Code-Span, der
 * Umgebungsblock kommt fertig escaped aus MetadataCollector (siehe
 * docs/adr/ADR-002). Ein Blockquote allein schützt nicht: GitLab wertet darin
 * Links, Bilder, HTML, Erwähnungen und — am Zeilenanfang — Quick Actions wie
 * `/assign` weiter aus.
 */
class GitLabDescriptionBuilder
{
    /**
     * @param array{references:array<int,string>,failed:int} $uploads
     * @param string $environment Fertiger Umgebungsblock aus MetadataCollector
     */
    public function build(TicketDraft $draft, array $uploads, string $environment): string
    {
        $input = $draft->getInput();
        $labels = $draft->getLabels();
        $ticket = $draft->getAiTicket();

        $blocks = [];

        // Der KI-Text ist aus dem Freitext abgeleitet und per Prompt-Injection
        // steuerbar — er wird deshalb genauso behandelt wie der Freitext.
        if ($ticket !== null && $ticket['description'] !== '') {
            $blocks[] = $this->asCodeBlock($ticket['description']);
        }

        if ($ticket === null) {
            $blocks[] = '_' . $labels->get('no_ai') . '_';
        }

        // Die Originalmeldung steht IMMER im Issue — eine schlechte oder
        // ausgefallene Aufbereitung darf die Meldung nicht unbrauchbar machen.
        $blocks[] = '## ' . $labels->get('original_report') . "\n\n" . $this->asCodeBlock($input->getMessage());

        $contact = $this->buildContactLine($input);

        if ($contact !== '') {
            $blocks[] = '## ' . $labels->get('contact') . "\n\n" . $contact;
        }

        $screenshots = $this->buildScreenshotSection($labels, $uploads);

        if ($screenshots !== '') {
            $blocks[] = $screenshots;
        }

        if ($environment !== '') {
            $blocks[] = $environment;
        }

        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * Name und E-Mail sind frei eingegebene Werte und werden als Code gesetzt.
     * Andernfalls ließe sich als „Name" ein Markdown-Link hinterlegen, der im
     * Issue wie eine harmlose Kontaktangabe aussieht.
     */
    private function buildContactLine(FeedbackInput $input): string
    {
        $parts = [];

        foreach ([$input->getName(), $input->getEmail()] as $value) {
            $value = trim((string) preg_replace('/[\x00-\x1F\x7F`]+/u', ' ', $value));

            if ($value !== '') {
                $parts[] = '`' . $value . '`';
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * @param array{references:array<int,string>,failed:int} $uploads
     */
    private function buildScreenshotSection(TicketLabels $labels, array $uploads): string
    {
        if ($uploads['references'] === [] && $uploads['failed'] === 0) {
            return '';
        }

        $section = '## ' . $labels->get('screenshots') . "\n";

        foreach ($uploads['references'] as $reference) {
            $section .= "\n" . $reference . "\n";
        }

        if ($uploads['failed'] > 0) {
            $section .= "\n_" . sprintf($labels->get('upload_failed'), $uploads['failed']) . "_\n";
        }

        return rtrim($section);
    }

    /**
     * Der Zaun ist länger als jede Backtick-Folge im Text, damit der Text den
     * Codeblock nicht vorzeitig schließen kann.
     */
    private function asCodeBlock(string $text): string
    {
        $longestRun = 0;

        if (preg_match_all('/`+/', $text, $runs) > 0) {
            $longestRun = max(array_map('strlen', $runs[0]));
        }

        $fence = str_repeat('`', max(3, $longestRun + 1));
        $text = (string) preg_replace('/\r\n|\r/', "\n", $text);

        return $fence . "text\n" . $text . "\n" . $fence;
    }
}
