<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

/**
 * Feste Texte im Ticket (Überschriften, Tabellen-Beschriftungen, Hinweise).
 *
 * Sie folgen der Einstellung "Ticket-Sprache", nicht der Shop-Sprache des
 * Melders.
 * Sonst entstünde bei "auf Englisch vereinheitlichen" ein Ticket mit englischem
 * KI-Text in deutschem Gerüst — und im Frontend wechselte die Gerüstsprache je
 * nach Besucher.
 */
class TicketLabels
{
    private const LABELS = [
        'source' => [
            'original_report' => 'Originalmeldung',
            'contact' => 'Rückfrage möglich bei',
            'screenshots' => 'Screenshots',
            'upload_failed' => 'Hinweis: %d Screenshot(s) konnten nicht übertragen werden.',
            'screenshots_attached' => '%d Screenshot(s) übermittelt, siehe Dokumente am Ticket.',
            'no_ai' => 'Hinweis: automatisch angelegt, ohne KI-Aufbereitung.',
            'environment' => 'Umgebung',
            'active_modules' => 'Aktive Module',
            'column_key' => 'Angabe',
            'column_value' => 'Wert',
            'context' => 'Kontext',
            'context_admin' => 'Backend',
            'context_frontend' => 'Shop-Frontend',
            'reference' => 'Bezug',
            'page' => 'Seite',
            'referrer' => 'Referrer',
            'client' => 'Browser / System',
            'viewport' => 'Fenster',
            'screen' => 'Bildschirm',
            'shop_id' => 'Shop-ID',
            'language' => 'Sprache',
            'currency' => 'Währung',
            'theme' => 'Theme',
            'shop_version' => 'Shop-Version',
            'php_version' => 'PHP-Version',
            'customer' => 'Kunde',
            'customer_number' => 'Nr. %s',
            'fallback_title' => 'Feedback aus dem Shop',
        ],
        'en' => [
            'original_report' => 'Original report',
            'contact' => 'Contact for questions',
            'screenshots' => 'Screenshots',
            'upload_failed' => 'Note: %d screenshot(s) could not be transferred.',
            'screenshots_attached' => '%d screenshot(s) submitted, see the documents on this ticket.',
            'no_ai' => 'Note: created automatically, without AI processing.',
            'environment' => 'Environment',
            'active_modules' => 'Active modules',
            'column_key' => 'Item',
            'column_value' => 'Value',
            'context' => 'Context',
            'context_admin' => 'Backend',
            'context_frontend' => 'Storefront',
            'reference' => 'Reference',
            'page' => 'Page',
            'referrer' => 'Referrer',
            'client' => 'Browser / system',
            'viewport' => 'Window',
            'screen' => 'Screen',
            'shop_id' => 'Shop ID',
            'language' => 'Language',
            'currency' => 'Currency',
            'theme' => 'Theme',
            'shop_version' => 'Shop version',
            'php_version' => 'PHP version',
            'customer' => 'Customer',
            'customer_number' => 'No. %s',
            'fallback_title' => 'Feedback from the shop',
        ],
    ];

    /** @var string source|en */
    private $language;

    public function __construct(string $language)
    {
        $this->language = isset(self::LABELS[$language]) ? $language : 'source';
    }

    public function get(string $key): string
    {
        return self::LABELS[$this->language][$key] ?? self::LABELS['source'][$key] ?? $key;
    }
}
