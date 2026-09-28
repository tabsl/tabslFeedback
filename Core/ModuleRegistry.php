<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Core;

use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ShopConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Bridge\ModuleActivationBridgeInterface;

/**
 * Aktivstatus und Versionen installierter Module über alle OXID-6-Versionen.
 *
 * Container und Activation-Bridge gibt es erst ab oxideshop-ce 6.5 (OXID eShop
 * 6.2). Davor liegt die ContainerFactory unter Internal\Application und die
 * Modulverwaltung läuft ausschließlich über Module/ModuleList. Ein reiner
 * Tausch des ContainerFactory-Namespace reicht deshalb nicht.
 */
class ModuleRegistry
{
    public function isActive(string $moduleId): bool
    {
        if ($this->hasModuleContainer()) {
            return $this->getActivationBridge()->isActive($moduleId, $this->getShopId());
        }

        $module = oxNew(Module::class);

        return $module->load($moduleId) && $module->isActive();
    }

    /**
     * @return array<string,string> Modul-ID => Version, nach ID sortiert
     */
    public function getActiveModuleVersions(): array
    {
        $modules = $this->hasModuleContainer()
            ? $this->collectFromContainer()
            : $this->collectFromModuleList();

        ksort($modules);

        return $modules;
    }

    private function hasModuleContainer(): bool
    {
        return class_exists(ContainerFactory::class)
            && interface_exists(ModuleActivationBridgeInterface::class);
    }

    /**
     * @return array<string,string>
     */
    private function collectFromContainer(): array
    {
        $container = ContainerFactory::getInstance()->getContainer();
        $shopConfiguration = $container->get(ShopConfigurationDaoBridgeInterface::class)->get();
        $activationBridge = $this->getActivationBridge();
        $shopId = $this->getShopId();

        $modules = [];

        // ModuleConfiguration kennt selbst kein isActivated() — der Aktivstatus
        // kommt ausschließlich über die Activation-Bridge.
        foreach ($shopConfiguration->getModuleConfigurations() as $moduleConfiguration) {
            $moduleId = $moduleConfiguration->getId();

            if ($activationBridge->isActive($moduleId, $shopId)) {
                $modules[$moduleId] = (string) $moduleConfiguration->getVersion();
            }
        }

        return $modules;
    }

    /**
     * @return array<string,string>
     */
    private function collectFromModuleList(): array
    {
        $moduleList = oxNew(ModuleList::class);
        $versions = $moduleList->getModuleConfigParametersByKey(ModuleList::MODULE_KEY_VERSIONS);

        $modules = [];

        foreach (array_keys((array) $moduleList->getActiveModuleInfo()) as $moduleId) {
            $modules[(string) $moduleId] = (string) ($versions[$moduleId] ?? '');
        }

        return $modules;
    }

    private function getActivationBridge(): ModuleActivationBridgeInterface
    {
        return ContainerFactory::getInstance()->getContainer()->get(ModuleActivationBridgeInterface::class);
    }

    private function getShopId(): int
    {
        return (int) Registry::getConfig()->getShopId();
    }
}
