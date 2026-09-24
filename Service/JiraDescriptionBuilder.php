<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

/**
 * Setzt Beschreibung und Upload-Vermerk für Jira als Atlassian Document Format
 * (ADF) zusammen — die REST API v3 lehnt einen einfachen String ab.
 *
 * Jeder Wert aus Browser und KI steht ausschließlich als Text eines
 * `text`-Knotens oder eines `codeBlock` (siehe docs/adr/ADR-002). Er kann damit
 * strukturell weder zu einem Link noch zu einer Erwähnung werden. Umgebungs- und
 * Kontaktwerte tragen die Mark `code`, damit eine URL aus dem Browser nicht wie
 * ein geprüfter Link aussieht.
 */
class JiraDescriptionBuilder
{
    /**
     * Jira begrenzt Textfelder auf 32.767 Zeichen. Ob Text oder JSON gemessen
     * wird, ist nicht dokumentiert — gemessen wird deshalb das JSON, mit
     * Abstand, und zwar in UTF-16-Einheiten wie in Java: Ein Emoji zählt dort
     * doppelt.
     */
    private const MAX_DOCUMENT_LENGTH = 32000;

    /**
     * Unbegrenzt sind nur Modulliste und KI-Text; sie entfallen in dieser
     * Reihenfolge. Alle Angaben aus dem Browser sind durch InputValidator
     * begrenzt und bleiben ohne beide deutlich unter dem Limit — die
     * Originalmeldung steht damit immer im Ticket.
     *
     * @param array{rows:array<string,string>,modules:array<string,string>} $environment
     *
     * @return array<string,mixed>
     */
    public function buildDescription(TicketDraft $draft, array $environment): array
    {
        $document = $this->document($this->buildBlocks($draft, $environment, true, true));

        if ($this->length($document) <= self::MAX_DOCUMENT_LENGTH) {
            return $document;
        }

        $document = $this->document($this->buildBlocks($draft, $environment, false, true));

        if ($this->length($document) <= self::MAX_DOCUMENT_LENGTH) {
            return $document;
        }

        return $this->document($this->buildBlocks($draft, $environment, false, false));
    }

    /**
     * @return array<string,mixed>
     */
    public function buildUploadFailedComment(TicketLabels $labels, int $failed): array
    {
        return $this->document([$this->paragraph([$this->text(sprintf($labels->get('upload_failed'), $failed))])]);
    }

    /**
     * @param array{rows:array<string,string>,modules:array<string,string>} $environment
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildBlocks(TicketDraft $draft, array $environment, bool $withModules, bool $withAi): array
    {
        $input = $draft->getInput();
        $labels = $draft->getLabels();
        $ticket = $draft->getAiTicket();

        $blocks = [];

        if ($ticket !== null && $ticket['description'] !== '') {
            if ($withAi) {
                foreach ($this->renderAiDescription($ticket['description']) as $block) {
                    $blocks[] = $block;
                }
            } else {
                $blocks[] = $this->paragraph([$this->text($labels->get('ai_omitted'), ['em'])]);
            }
        }

        if ($ticket === null) {
            $blocks[] = $this->paragraph([$this->text($labels->get('no_ai'), ['em'])]);
        }

        // Die Originalmeldung steht IMMER im Ticket — eine schlechte oder
        // ausgefallene Aufbereitung darf die Meldung nicht unbrauchbar machen.
        $blocks[] = $this->heading($labels->get('original_report'));
        $blocks[] = ['type' => 'blockquote', 'content' => [$this->paragraph($this->multiline($input->getMessage()))]];

        $contact = $this->buildContactLine($input);

        if ($contact !== []) {
            $blocks[] = $this->heading($labels->get('contact'));
            $blocks[] = $this->paragraph($contact);
        }

        $imageCount = count($draft->getImages());

        if ($imageCount > 0) {
            $blocks[] = $this->heading($labels->get('screenshots'));
            $blocks[] = $this->paragraph([$this->text(sprintf($labels->get('screenshots_attached'), $imageCount))]);
        }

        if ($environment['rows'] !== []) {
            $blocks[] = $this->heading($labels->get('environment'));
            $blocks[] = $this->renderRows($environment['rows']);
        }

        if ($environment['modules'] !== []) {
            $blocks[] = $this->heading($labels->get('active_modules') . ' (' . count($environment['modules']) . ')');
            $blocks[] = $withModules
                ? $this->renderModules($environment['modules'])
                : $this->paragraph([$this->text($labels->get('modules_omitted'), ['em'])]);
        }

        return $blocks;
    }

    /**
     * Die KI liefert kurze Markdown-Stichpunkte. Aufzählungszeilen werden zur
     * Liste, alles andere zu Absätzen; Hervorhebungen entfallen, statt als
     * Sternchen stehenzubleiben.
     *
     * @return array<int,array<string,mixed>>
     */
    private function renderAiDescription(string $description): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $description);

        if ($lines === false) {
            return [$this->paragraph($this->multiline($description))];
        }

        $blocks = [];
        $listItems = [];
        $paragraph = [];

        $flushList = function () use (&$blocks, &$listItems): void {
            if ($listItems !== []) {
                $blocks[] = ['type' => 'bulletList', 'content' => $listItems];
                $listItems = [];
            }
        };

        $flushParagraph = function () use (&$blocks, &$paragraph): void {
            if ($paragraph !== []) {
                $blocks[] = $this->paragraph($paragraph);
                $paragraph = [];
            }
        };

        foreach ($lines as $line) {
            $line = trim(str_replace('**', '', $line));

            if ($line === '') {
                $flushList();
                $flushParagraph();

                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/u', $line, $match) === 1) {
                $flushParagraph();
                $listItems[] = ['type' => 'listItem', 'content' => [$this->paragraph([$this->text($this->singleLine($match[1]))])]];

                continue;
            }

            $flushList();

            if ($paragraph !== []) {
                $paragraph[] = ['type' => 'hardBreak'];
            }

            $paragraph[] = $this->text($this->singleLine($line));
        }

        $flushList();
        $flushParagraph();

        return $blocks;
    }

    /**
     * @param array<string,string> $rows
     *
     * @return array<string,mixed>
     */
    private function renderRows(array $rows): array
    {
        $items = [];

        foreach ($rows as $label => $value) {
            $items[] = [
                'type' => 'listItem',
                'content' => [$this->paragraph([
                    $this->text($label . ':', ['strong']),
                    $this->text(' '),
                    $this->text($this->singleLine($value), ['code']),
                ])],
            ];
        }

        return ['type' => 'bulletList', 'content' => $items];
    }

    /**
     * @param array<string,string> $modules Modul-ID => Version
     *
     * @return array<string,mixed>
     */
    private function renderModules(array $modules): array
    {
        $lines = [];

        foreach ($modules as $id => $version) {
            $lines[] = $this->singleLine(trim($id . ' ' . $version));
        }

        return ['type' => 'codeBlock', 'content' => [$this->text(implode("\n", $lines))]];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildContactLine(FeedbackInput $input): array
    {
        $nodes = [];

        foreach ([$input->getName(), $input->getEmail()] as $value) {
            $value = $this->singleLine($value);

            if ($value === '') {
                continue;
            }

            if ($nodes !== []) {
                $nodes[] = $this->text(' · ');
            }

            $nodes[] = $this->text($value, ['code']);
        }

        return $nodes;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function multiline(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);

        if ($lines === false) {
            $lines = [$text];
        }

        $nodes = [];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $nodes[] = ['type' => 'hardBreak'];
            }

            $nodes[] = $this->text($this->singleLine($line));
        }

        return $nodes;
    }

    /**
     * @return array<string,mixed>
     */
    private function heading(string $text): array
    {
        return ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [$this->text($text)]];
    }

    /**
     * ADF verbietet leere `text`-Knoten; sie werden hier aussortiert.
     *
     * @param array<int,array<string,mixed>|null> $nodes
     *
     * @return array<string,mixed>
     */
    private function paragraph(array $nodes): array
    {
        return ['type' => 'paragraph', 'content' => array_values(array_filter($nodes))];
    }

    /**
     * @param array<int,string> $marks strong|em|code
     *
     * @return array<string,mixed>|null null = leerer Text, kein Knoten
     */
    private function text(string $value, array $marks = []): ?array
    {
        if ($value === '') {
            return null;
        }

        $node = ['type' => 'text', 'text' => $value];

        if ($marks !== []) {
            $node['marks'] = array_map(static function (string $mark): array {
                return ['type' => $mark];
            }, $marks);
        }

        return $node;
    }

    private function singleLine(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));
    }

    /**
     * @param array<string,mixed> $document
     */
    private function length(array $document): int
    {
        $json = (string) json_encode(
            $document,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return intdiv(strlen((string) mb_convert_encoding($json, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * @param array<int,array<string,mixed>> $content
     *
     * @return array<string,mixed>
     */
    private function document(array $content): array
    {
        return ['type' => 'doc', 'version' => 1, 'content' => $content];
    }
}
