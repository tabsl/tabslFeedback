<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

/**
 * Geprüfte Eingabe einer Feedback-Absendung.
 *
 * Wird ausschließlich von InputValidator erzeugt; ab hier gelten Freitext,
 * Kontaktangaben und Bilddaten als validiert.
 */
class FeedbackInput
{
    /** @var string */
    private $message;

    /** @var string Nur befüllt, wenn das Betreff-Feld eingeblendet ist (KI-Aufbereitung aus) */
    private $subject;

    /** @var string */
    private $name;

    /** @var string */
    private $email;

    /** @var array<int,array{bytes:string,mime:string,filename:string}> */
    private $images;

    /** @var array<string,string> Vom Client gemeldete Umgebung (url, referrer, viewport, screen) */
    private $clientMeta;

    /** @var array<int,string> Jira-Vorgangs-Keys aus dem Seitenquelltext, in Fundreihenfolge */
    private $pageIssueKeys;

    /**
     * @param array<int,array{bytes:string,mime:string,filename:string}> $images
     * @param array<string,string> $clientMeta
     * @param array<int,string> $pageIssueKeys
     */
    public function __construct(
        string $message,
        string $subject,
        string $name,
        string $email,
        array $images,
        array $clientMeta,
        array $pageIssueKeys = []
    ) {
        $this->message = $message;
        $this->subject = $subject;
        $this->name = $name;
        $this->email = $email;
        $this->images = $images;
        $this->clientMeta = $clientMeta;
        $this->pageIssueKeys = $pageIssueKeys;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return array<int,array{bytes:string,mime:string,filename:string}>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @return array<string,string>
     */
    public function getClientMeta(): array
    {
        return $this->clientMeta;
    }

    /**
     * Vom Browser gemeldet und damit frei wählbar — nur das Format ist geprüft.
     *
     * @return array<int,string>
     */
    public function getPageIssueKeys(): array
    {
        return $this->pageIssueKeys;
    }
}
