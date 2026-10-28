<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Core;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ShopConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Bridge\ModuleActivationBridgeInterface;

/**
 * Aktivstatus und Versionen installierter Module über die Container-Bridges
 * von OXID 7.
 */
class ModuleRegistry
{
    public function isActive(string $moduleId): bool
    {
        return $this->getActivationBridge()->isActive($moduleId, $this->getShopId());
    }

    /**
     * @return array<string,string> Modul-ID => Version, nach ID sortiert
     */
    public function getActiveModuleVersions(): array
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

        ksort($modules);

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
