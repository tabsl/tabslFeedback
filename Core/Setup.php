<?php

declare(strict_types=1);

namespace Tabsl\Feedback\Core;

use OxidEsales\Eshop\Core\Registry;

/**
 * Aktivierungs- und Deaktivierungs-Events des Moduls.
 *
 * Das Modul legt keine Tabellen an und speichert nichts im Shop — die einzige
 * nötige Aufräumarbeit ist der Smarty-Cache, damit die Template-Blocks
 * (Frontend-Button, Admin-Header-Link) sofort erscheinen bzw. verschwinden.
 */
class Setup
{
    public static function onActivate(): void
    {
        self::clearTemplateCache();
    }

    public static function onDeactivate(): void
    {
        self::clearTemplateCache();
    }

    private static function clearTemplateCache(): void
    {
        $utilsView = Registry::getUtilsView();

        if (method_exists($utilsView, 'getSmarty')) {
            $smarty = $utilsView->getSmarty();

            if ($smarty !== null && method_exists($smarty, 'clear_compiled_tpl')) {
                $smarty->clear_compiled_tpl();
            }
        }
    }
}
