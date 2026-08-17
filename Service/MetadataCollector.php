<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Service;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\ShopVersion;
use OxidEsales\Eshop\Core\Theme;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ShopConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Bridge\ModuleActivationBridgeInterface;
use Tabsl\Feedback\Core\ModuleSettings;

/**
 * Sammelt die acht Umgebungsangaben und bringt sie in
 * eine im GitLab-Issue lesbare Form.
 *
 * Ausdrücklich NICHT erhoben: IP-Adresse, Warenkorb-/Bestellkontext und
 * Browser-Konsolenmeldungen. Der Bezug ist keine Ausnahme davon — er wird nicht
 * ermittelt, sondern von der aufrufenden Seite bewusst mitgegeben.
 * Eine nicht ermittelbare Angabe wird stillschweigend
 * ausgelassen und darf die Ticket-Anlage nie verhindern — deshalb ist jeder
 * Einzelabruf gegen Ausfall abgesichert.
 */
class MetadataCollector
{
    /** @var ModuleSettings */
    private $settings;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?? new ModuleSettings();
    }

    /**
     * Baut den eingeklappten Umgebungsblock für die Issue-Beschreibung.
     *
     * @param array<string,string> $clientMeta Geprüfte Angaben aus fb_meta
     * @param string $contextKey context_admin|context_frontend
     */
    public function buildEnvironmentSection(array $clientMeta, string $contextKey, TicketLabels $labels): string
    {
        $rows = $this->collectRows($clientMeta, $labels->get($contextKey), $labels);

        if ($rows === []) {
            return '';
        }

        $table = '| ' . $labels->get('column_key') . ' | ' . $labels->get('column_value') . " |\n| --- | --- |\n";

        foreach ($rows as $label => $value) {
            // Die Beschriftung stammt aus TicketLabels und ist fest; nur der Wert
            // kann aus dem Browser des Melders kommen und wird als Code gesetzt.
            $table .= '| ' . $this->escapeInline($label) . ' | ' . $this->escapeCell($value) . " |\n";
        }

        return "<details>\n<summary>" . $labels->get('environment') . "</summary>\n\n"
            . $table . "\n"
            . $this->buildModuleSection($labels)
            . '</details>';
    }

    /**
     * @param array<string,string> $clientMeta
     *
     * @return array<string,string>
     */
    private function collectRows(array $clientMeta, string $context, TicketLabels $labels): array
    {
        $rows = [$labels->get('context') => $context];

        // Steht vor der Seite: Wo gemeldet wurde, sagt die URL — worum es geht,
        // weiß nur die Stelle, die den Dialog geöffnet hat.
        if (isset($clientMeta['reference'])) {
            $rows[$labels->get('reference')] = $clientMeta['reference'];
        }

        if (isset($clientMeta['url'])) {
            $rows[$labels->get('page')] = $clientMeta['url'];
        }

        if (isset($clientMeta['referrer'])) {
            $rows[$labels->get('referrer')] = $clientMeta['referrer'];
        }

        $client = $this->detectClient();

        if ($client !== '') {
            $rows[$labels->get('client')] = $client;
        }

        if (isset($clientMeta['viewport'])) {
            $rows[$labels->get('viewport')] = $clientMeta['viewport'];
        }

        if (isset($clientMeta['screen'])) {
            $rows[$labels->get('screen')] = $clientMeta['screen'];
        }

        foreach ($this->collectShopRows($labels) as $label => $value) {
            $rows[$label] = $value;
        }

        $customer = $this->collectCustomer($labels);

        if ($customer !== '') {
            $rows[$labels->get('customer')] = $customer;
        }

        return $rows;
    }

    /**
     * @return array<string,string>
     */
    private function collectShopRows(TicketLabels $labels): array
    {
        $rows = [];
        $config = Registry::getConfig();

        try {
            $rows[$labels->get('shop_id')] = (string) $config->getShopId();
        } catch (\Throwable $e) {
            // Angabe entfällt.
        }

        try {
            $language = (string) Registry::getLang()->getLanguageAbbr();

            if ($language !== '') {
                $rows[$labels->get('language')] = $language;
            }
        } catch (\Throwable $e) {
            // Angabe entfällt.
        }

        try {
            $currency = $config->getActShopCurrencyObject();

            if ($currency !== null && isset($currency->name) && (string) $currency->name !== '') {
                $rows[$labels->get('currency')] = (string) $currency->name;
            }
        } catch (\Throwable $e) {
            // Angabe entfällt.
        }

        $theme = $this->detectTheme();

        if ($theme !== '') {
            $rows[$labels->get('theme')] = $theme;
        }

        try {
            $rows[$labels->get('shop_version')] = ShopVersion::getVersion();
        } catch (\Throwable $e) {
            // Angabe entfällt.
        }

        $rows[$labels->get('php_version')] = PHP_VERSION;

        return $rows;
    }

    private function detectTheme(): string
    {
        try {
            $theme = oxNew(Theme::class);
            $themeId = (string) $theme->getActiveThemeId();

            if ($themeId === '') {
                return '';
            }

            $theme->load($themeId);
            $version = (string) $theme->getInfo('version');

            return $version !== '' ? $themeId . ' ' . $version : $themeId;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Kundendaten nur bei angemeldetem Kunden UND aktivierter Einstellung.
     */
    private function collectCustomer(TicketLabels $labels): string
    {
        if (!$this->settings->sendCustomerData()) {
            return '';
        }

        try {
            $user = Registry::getSession()->getUser();

            if (!$user || !$user->getId()) {
                return '';
            }

            $parts = [];
            $customerNumber = (string) $user->getFieldData('oxcustnr');

            if ($customerNumber !== '') {
                $parts[] = sprintf($labels->get('customer_number'), $customerNumber);
            }

            $name = trim(
                (string) $user->getFieldData('oxfname') . ' ' . (string) $user->getFieldData('oxlname')
            );

            if ($name !== '') {
                $parts[] = $name;
            }

            $email = (string) $user->getFieldData('oxusername');

            if ($email !== '') {
                $parts[] = $email;
            }

            return implode(', ', $parts);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Browser inkl. Version und Betriebssystem aus dem User-Agent.
     *
     * Bewusst eine schlanke Heuristik statt einer Parser-Bibliothek: die Angabe
     * ist ein Hinweis für den Bearbeiter, keine Auswertungsgrundlage. Über den
     * hier erzeugten Text hinaus wird nichts aus dem Request übernommen — der
     * rohe User-Agent-String und die IP bleiben außen vor.
     */
    private function detectClient(): string
    {
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        if ($userAgent === '') {
            return '';
        }

        $browser = '';

        // Reihenfolge ist relevant: Edge und Opera nennen sich auch "Chrome",
        // Chrome nennt sich auch "Safari".
        $browsers = [
            'Edge' => '#Edg(?:e|A|iOS)?/([0-9.]+)#',
            'Opera' => '#OPR/([0-9.]+)#',
            'Firefox' => '#Firefox/([0-9.]+)#',
            'Chrome' => '#Chrome/([0-9.]+)#',
            'Safari' => '#Version/([0-9.]+).*Safari#',
        ];

        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                $browser = $name . ' ' . $matches[1];
                break;
            }
        }

        $system = '';
        $systems = [
            'Windows' => '#Windows NT#',
            'Android' => '#Android#',
            'iOS' => '#(?:iPhone|iPad|iPod)#',
            'macOS' => '#Mac OS X#',
            'Linux' => '#Linux#',
        ];

        foreach ($systems as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $system = $name;
                break;
            }
        }

        if ($browser === '' && $system === '') {
            return '';
        }

        if ($browser === '') {
            return $system;
        }

        return $system !== '' ? $browser . ' / ' . $system : $browser;
    }

    /**
     * Liste der aktiven Module mit Versionen, eingeklappt — in einem gewachsenen
     * Shop sind das schnell mehrere Dutzend Einträge.
     */
    private function buildModuleSection(TicketLabels $labels): string
    {
        $modules = $this->collectActiveModules();

        if ($modules === []) {
            return '';
        }

        $list = '';

        foreach ($modules as $id => $version) {
            $list .= '- ' . $this->escapeInline($id)
                . ($version !== '' ? ' `' . $this->escapeInline($version) . '`' : '')
                . "\n";
        }

        return "<details>\n<summary>" . $labels->get('active_modules') . ' (' . count($modules) . ")</summary>\n\n"
            . $list . "\n</details>\n\n";
    }

    /**
     * @return array<string,string> Modul-ID => Version
     */
    private function collectActiveModules(): array
    {
        try {
            $container = ContainerFactory::getInstance()->getContainer();
            $shopConfiguration = $container->get(ShopConfigurationDaoBridgeInterface::class)->get();
            $activationBridge = $container->get(ModuleActivationBridgeInterface::class);
            $shopId = (int) Registry::getConfig()->getShopId();

            $modules = [];

            // ModuleConfiguration kennt selbst kein isActivated() — der Aktivstatus
            // kommt ausschließlich über die Activation-Bridge.
            foreach ($shopConfiguration->getModuleConfigurations() as $moduleConfiguration) {
                $moduleId = $moduleConfiguration->getId();

                if (!$activationBridge->isActive($moduleId, $shopId)) {
                    continue;
                }

                $modules[$moduleId] = $moduleConfiguration->getVersion();
            }

            ksort($modules);

            return $modules;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Client-Angaben landen in einer Markdown-Tabelle. Zeilenumbrüche und
     * Pipe-Zeichen würden die Tabelle zerlegen, Backticks und spitze Klammern
     * könnten Markup einschleusen.
     *
     * Der Wert wird zusätzlich als Code-Span ausgegeben: URL und Referrer stammen
     * unverändert aus dem Browser des Melders und sind damit frei wählbar — ohne
     * Code-Span ließe sich darüber ein Markdown-Link ins Issue schreiben, den ein
     * Bearbeiter für eine automatisch erhobene Angabe hält. Da escapeInline()
     * Backticks ersetzt, kann der Wert die Span nicht verlassen.
     */
    private function escapeCell(string $value): string
    {
        $escaped = str_replace('|', '\\|', $this->escapeInline($value));

        return $escaped !== '' ? '`' . $escaped . '`' : '';
    }

    private function escapeInline(string $value): string
    {
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = str_replace(['`', '<', '>'], ["'", '&lt;', '&gt;'], $value);

        return trim($value);
    }
}
