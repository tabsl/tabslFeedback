[{$smarty.block.parent}]

[{* Zusätzlicher Eintrag in der Header-Liste des Backends.
    Der Header ist ein eigener, wenige Pixel hoher Frame — das Formular öffnet
    deshalb im Hauptframe, genau wie "Home" und "Logout" es tun. *}]
[{if $oViewConf->isTabslFeedbackAdminAvailable()}]
    <li class="sep">
        <a href="[{$oViewConf->getSelfLink()}]&amp;cl=tabslfeedback_form" id="tabslfeedbacklink" target="basefrm" class="rc">
            <b>[{oxmultilang ident="TABSLFEEDBACK_ADMIN_LINK"}]</b>
        </a>
    </li>
[{/if}]
