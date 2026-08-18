<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use Tabsl\Feedback\Exception\FeedbackException;

/**
 * Gemeinsame Eingabeprüfung für Frontend- und Backend-Formular.
 *
 * Beide Controller nutzen diese Klasse, damit die Regeln nicht
 * auseinanderlaufen. Sämtliche Grenzwerte sind hier als Konstanten hinterlegt —
 * sie sind bewusst keine Einstellungen.
 *
 * Alle Grenzen werden serverseitig durchgesetzt; die zusätzliche Prüfung im
 * Browser dient nur der Meldung ohne Roundtrip und ist keine Absicherung.
 */
class InputValidator
{
    /** Deckt "mehrere Screenshots" ab und begrenzt die GitLab-Uploads im synchronen Pfad. */
    public const MAX_IMAGES = 5;

    /** 10 MB je Bild, gemessen an den DEKODIERTEN Bytes. */
    public const MAX_IMAGE_BYTES = 10485760;

    /** 20 MB Summe — muss unter post_max_size/memory_limit typischer Hostings bleiben. */
    public const MAX_TOTAL_BYTES = 20971520;

    /** Was Browser beim Einfügen aus der Zwischenablage liefern. */
    public const ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public const MAX_MESSAGE_LENGTH = 5000;

    public const MAX_CONTACT_FIELD_LENGTH = 255;

    /**
     * Der Bezug benennt den Gegenstand der Meldung (Kennung eines Entwurfs, einer
     * Bestellung, eines Artikels) — eine Kennung, kein zweites Meldungsfeld.
     */
    public const MAX_REFERENCE_LENGTH = 200;

    /** Erlaubte Schlüssel aus fb_meta; alles andere wird verworfen. */
    private const CLIENT_META_KEYS = ['url', 'referrer', 'viewport', 'screen', 'reference'];

    /**
     * Query-Parameter, die aus URL und Referrer entfernt werden, bevor sie ins
     * Ticket gelangen.
     *
     * OXID hängt je nach Konfiguration die Sitzungskennung an Links; ein Melder
     * kann eine Seite also mit gültigem `sid` oder `stoken` in der Adresszeile
     * aufrufen. Unbereinigt stünde diese Kennung im GitLab-Issue und wäre für
     * jeden lesbar, der das Projekt einsehen darf — solange die Sitzung läuft,
     * ließe sich damit deren Übernahme versuchen.
     */
    /** Durchgängig klein geschrieben — der Vergleich erfolgt kleingeschrieben. */
    private const STRIPPED_QUERY_PARAMS = [
        'sid', 'force_sid', 'admin_sid', 'force_admin_sid', 'stoken',
        'sdeliveryaddressmd5',
        'token', 'access_token', 'auth', 'key', 'apikey', 'api_key',
        'password', 'passwd', 'pwd', 'secret', 'signature', 'hash',
    ];

    /**
     * @param array<int|string,mixed> $rawImages Daten-URLs aus fb_images[]
     *
     * @throws FeedbackException vom Typ VALIDATION
     */
    public function validate(
        string $rawMessage,
        array $rawImages,
        string $rawName,
        string $rawEmail,
        string $rawMeta
    ): FeedbackInput {
        $message = trim($rawMessage);

        if ($message === '') {
            throw FeedbackException::validation('TABSLFEEDBACK_ERROR_MESSAGE_REQUIRED');
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw FeedbackException::validation(
                'TABSLFEEDBACK_ERROR_MESSAGE_TOO_LONG',
                ['max' => self::MAX_MESSAGE_LENGTH]
            );
        }

        return new FeedbackInput(
            $message,
            $this->validateContactField($rawName),
            $this->validateEmail($rawEmail),
            $this->validateImages($rawImages),
            $this->extractClientMeta($rawMeta)
        );
    }

    /**
     * @throws FeedbackException
     */
    private function validateContactField(string $raw): string
    {
        $value = trim($raw);

        if (mb_strlen($value) > self::MAX_CONTACT_FIELD_LENGTH) {
            throw FeedbackException::validation(
                'TABSLFEEDBACK_ERROR_CONTACT_TOO_LONG',
                ['max' => self::MAX_CONTACT_FIELD_LENGTH]
            );
        }

        return $value;
    }

    /**
     * Leer ist zulässig; gesetzt und unplausibel weist die Absendung ab, bevor ein
     * externer Dienst angesprochen wird.
     *
     * @throws FeedbackException
     */
    private function validateEmail(string $raw): string
    {
        $email = $this->validateContactField($raw);

        if ($email === '') {
            return '';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw FeedbackException::validation('TABSLFEEDBACK_ERROR_EMAIL_INVALID');
        }

        return $email;
    }

    /**
     * @param array<int|string,mixed> $rawImages
     *
     * @return array<int,array{bytes:string,mime:string,filename:string}>
     *
     * @throws FeedbackException
     */
    private function validateImages(array $rawImages): array
    {
        $dataUrls = [];

        foreach ($rawImages as $rawImage) {
            if (is_string($rawImage) && trim($rawImage) !== '') {
                $dataUrls[] = trim($rawImage);
            }
        }

        if (count($dataUrls) > self::MAX_IMAGES) {
            throw FeedbackException::validation(
                'TABSLFEEDBACK_ERROR_TOO_MANY_IMAGES',
                ['max' => self::MAX_IMAGES]
            );
        }

        $images = [];
        $totalBytes = 0;
        $index = 0;

        foreach ($dataUrls as $dataUrl) {
            $index++;
            $bytes = $this->decodeDataUrl($dataUrl);

            if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
                throw FeedbackException::validation(
                    'TABSLFEEDBACK_ERROR_IMAGE_TOO_LARGE',
                    ['max' => (int) round(self::MAX_IMAGE_BYTES / 1048576)]
                );
            }

            $totalBytes += strlen($bytes);

            if ($totalBytes > self::MAX_TOTAL_BYTES) {
                throw FeedbackException::validation(
                    'TABSLFEEDBACK_ERROR_IMAGES_TOO_LARGE',
                    ['max' => (int) round(self::MAX_TOTAL_BYTES / 1048576)]
                );
            }

            // MIME am echten Inhalt bestimmen, nicht am deklarierten Daten-URL-Typ:
            // der Präfix ist frei wählbar und damit keine Aussage über die Bytes.
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

            if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
                throw FeedbackException::validation('TABSLFEEDBACK_ERROR_IMAGE_TYPE');
            }

            $images[] = [
                'bytes' => $bytes,
                'mime' => $mime,
                'filename' => 'screenshot-' . $index . '.' . $this->mimeToExtension($mime),
            ];
        }

        return $images;
    }

    /**
     * @throws FeedbackException
     */
    private function decodeDataUrl(string $dataUrl): string
    {
        if (!preg_match('#^data:image/[a-z0-9.+-]+;base64,#i', $dataUrl)) {
            throw FeedbackException::validation('TABSLFEEDBACK_ERROR_IMAGE_TYPE');
        }

        $payload = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $bytes = base64_decode($payload, true);

        if ($bytes === false || $bytes === '') {
            throw FeedbackException::validation('TABSLFEEDBACK_ERROR_IMAGE_TYPE');
        }

        return $bytes;
    }

    /**
     * Entfernt Sitzungskennungen und ähnliche Geheimnisse aus einer Adresse,
     * behält aber alle fachlich nützlichen Parameter (Kategorie, Suchbegriff,
     * Seitenzahl) — sie sind für das Nachstellen des Problems oft entscheidend.
     *
     * Entfernte Parameter werden durch `…` ersetzt statt gelöscht, damit im
     * Ticket erkennbar bleibt, dass die Adresse gekürzt wurde.
     */
    private function stripSensitiveQueryParams(string $url): string
    {
        $queryStart = strpos($url, '?');

        if ($queryStart === false) {
            return $url;
        }

        $base = substr($url, 0, $queryStart);
        $tail = substr($url, $queryStart + 1);

        // Ein Fragment gehört nicht zur Query und bleibt unangetastet.
        $fragment = '';
        $fragmentStart = strpos($tail, '#');

        if ($fragmentStart !== false) {
            $fragment = substr($tail, $fragmentStart);
            $tail = substr($tail, 0, $fragmentStart);
        }

        if ($tail === '') {
            return $url;
        }

        $parts = [];

        foreach (explode('&', $tail) as $pair) {
            if ($pair === '') {
                continue;
            }

            $name = strpos($pair, '=') !== false ? substr($pair, 0, strpos($pair, '=')) : $pair;

            // Auf den Basisnamen normalisieren: "token[]" und "token[a]" tragen
            // denselben Wert wie "token" und müssen ebenso entfernt werden.
            $comparable = strtolower(rawurldecode($name));
            $bracket = strpos($comparable, '[');

            if ($bracket !== false) {
                $comparable = substr($comparable, 0, $bracket);
            }

            $parts[] = in_array($comparable, self::STRIPPED_QUERY_PARAMS, true)
                ? $name . '=…'
                : $pair;
        }

        return $base . ($parts !== [] ? '?' . implode('&', $parts) : '') . $fragment;
    }

    private function mimeToExtension(string $mime): string
    {
        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        return $map[$mime] ?? 'png';
    }

    /**
     * Übernimmt ausschließlich die erwarteten Angaben aus fb_meta. Werte
     * bleiben unbereinigt — die Ausgabe-Maskierung übernimmt MetadataCollector,
     * der als einziger weiß, in welchen Kontext sie geschrieben werden.
     *
     * @return array<string,string>
     */
    private function extractClientMeta(string $rawMeta): array
    {
        if (trim($rawMeta) === '') {
            return [];
        }

        $decoded = json_decode($rawMeta, true);

        if (!is_array($decoded)) {
            return [];
        }

        $meta = [];

        foreach (self::CLIENT_META_KEYS as $key) {
            if (!isset($decoded[$key])) {
                continue;
            }

            $value = $decoded[$key];

            // viewport/screen kommen als {w,h}; alles andere als Skalar.
            if (is_array($value)) {
                $width = isset($value['w']) && is_scalar($value['w']) ? (int) $value['w'] : 0;
                $height = isset($value['h']) && is_scalar($value['h']) ? (int) $value['h'] : 0;

                if ($width > 0 && $height > 0) {
                    $meta[$key] = $width . ' × ' . $height;
                }

                continue;
            }

            if (is_scalar($value)) {
                $limit = $key === 'reference' ? self::MAX_REFERENCE_LENGTH : 2000;
                $plain = mb_substr(trim((string) $value), 0, $limit);

                // Eine Angabe aus reinem Leerraum ist keine Angabe: Sie erzeugt
                // sonst eine Tabellenzeile mit leerer Zelle im Ticket.
                if ($plain === '') {
                    continue;
                }

                $meta[$key] = in_array($key, ['url', 'referrer'], true)
                    ? $this->stripSensitiveQueryParams($plain)
                    : $plain;
            }
        }

        return $meta;
    }
}
