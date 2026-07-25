<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Bridge\ModuleActivationBridgeInterface;

/**
 * Weiche Anbindung an das optionale Modul tabslTurnstile.
 *
 * Diese Klasse ist die EINZIGE Stelle, die den Schutzzustand ermittelt — Template
 * (Widget anzeigen) und SubmitController (serverseitig prüfen) fragen dieselbe
 * Methode. Liefen beide auseinander, entstünde entweder ein Widget ohne Prüfung
 * oder eine Prüfung ohne Widget, die jede Absendung blockiert.
 *
 * Zustände (planning.md §4, Component 1):
 *   Klasse fehlt / Modul deaktiviert / Site-Key leer -> isActive() = false, keine Prüfung
 *   aktiviert und konfiguriert                       -> isActive() = true, verify() entscheidet
 *
 * Es wird bewusst KEINE Client-IP an Turnstile übergeben (requirements.md B3);
 * das optionale Feld remoteip bleibt leer.
 */
class TurnstileGate
{
    private const MODULE_ID = 'tabslTurnstile';

    /** Als String, damit die Klasse keine harte Kopplung an das fremde Modul erzeugt. */
    private const SERVICE_CLASS = 'Tabsl\\Turnstile\\Service\\TurnstileService';

    /** Feldname, unter dem das Turnstile-Widget seinen Token sendet. */
    public const TOKEN_FIELD = 'cf-turnstile-response';

    /** Kein Schutz vorgesehen: Modul fehlt, ist deaktiviert oder unkonfiguriert. */
    public const STATE_OFF = 'off';

    /** Schutz aktiv: Widget anzeigen und Token prüfen. */
    public const STATE_ON = 'on';

    /** Zustand nicht ermittelbar — weder verlässlich an noch verlässlich aus. */
    public const STATE_ERROR = 'error';

    /**
     * Ermittelt den Schutzzustand.
     *
     * Der Zustand ERROR wird bewusst von OFF unterschieden: Fiele ein interner
     * Fehler mit „kein Schutz vorgesehen" zusammen, würde der einzige
     * Missbrauchsschutz des Moduls still abgeschaltet. Absendungen werden in
     * diesem Fall abgewiesen (siehe SubmitController), während ein gar nicht
     * installiertes Turnstile weiterhin folgenlos bleibt (requirements.md C2).
     *
     * @return string self::STATE_OFF|self::STATE_ON|self::STATE_ERROR
     */
    public function resolveState(): string
    {
        // Ohne die Klasse ist das Modul nicht vorhanden — das ist eine klare
        // Aussage und kein Fehler.
        if (!class_exists(self::SERVICE_CLASS)) {
            return self::STATE_OFF;
        }

        try {
            // Die Klasse kann über Composer im Dateisystem liegen, während das
            // Modul im Shop deaktiviert ist — Klassen-Existenz allein genügt nicht.
            $container = ContainerFactory::getInstance()->getContainer();
            $activationBridge = $container->get(ModuleActivationBridgeInterface::class);

            if (!$activationBridge->isActive(self::MODULE_ID, (int) Registry::getConfig()->getShopId())) {
                return self::STATE_OFF;
            }

            $service = $this->createService();

            // Fehlt eine der erwarteten Methoden, hat das fremde Modul seine
            // Schnittstelle geändert. Das ist kein regulärer Aus-Zustand.
            if ($service === null
                || !method_exists($service, 'getSiteKey')
                || !method_exists($service, 'verifyToken')
            ) {
                Registry::getLogger()->error(
                    '[tabslFeedback] tabslTurnstile is active but does not provide the expected service interface'
                );

                return self::STATE_ERROR;
            }

            // Aktiviert, aber ohne Site-Key: der Betreiber hat die Einrichtung
            // nicht abgeschlossen. Das Formular soll deshalb nicht blockieren.
            return trim((string) $service->getSiteKey()) !== '' ? self::STATE_ON : self::STATE_OFF;
        } catch (\Throwable $exception) {
            Registry::getLogger()->error(
                '[tabslFeedback] could not determine the tabslTurnstile state: ' . $exception->getMessage()
            );

            return self::STATE_ERROR;
        }
    }

    /**
     * Für die Anzeige im Template: nur bei gesichert aktivem Schutz erscheint das
     * Widget.
     */
    public function isActive(): bool
    {
        return $this->resolveState() === self::STATE_ON;
    }

    public function getSiteKey(): string
    {
        try {
            $service = $this->createService();

            return $service !== null && method_exists($service, 'getSiteKey')
                ? trim((string) $service->getSiteKey())
                : '';
        } catch (\Throwable $exception) {
            return '';
        }
    }

    /**
     * Prüft den Token. Ein nicht erreichbarer Turnstile-Dienst gilt als "nicht
     * bestanden" — bei aktivem Schutz wird im Zweifel abgewiesen, nicht
     * durchgelassen.
     */
    public function verify(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $service = $this->createService();

            if ($service === null) {
                return false;
            }

            return (bool) $service->verifyToken($token, '');
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * @return object|null
     */
    private function createService()
    {
        if (!class_exists(self::SERVICE_CLASS)) {
            return null;
        }

        $class = self::SERVICE_CLASS;

        return new $class();
    }
}
