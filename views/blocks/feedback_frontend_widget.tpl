[{$smarty.block.parent}]

[{* Feedback-Widget. Liegt auf base_js, weil dieser Block in ps und wave
    vorhanden ist — after_body gibt es nur im ps-Theme.
    Die Konfiguration wandert über data-Attribute ins JavaScript; im Template
    steht bewusst keine Logik. *}]
[{if $oViewConf->isTabslFeedbackFrontendAvailable()}]
    <link rel="stylesheet" type="text/css" href="[{$oViewConf->getTabslFeedbackAssetUrl('out/src/css/tabslfeedback.min.css')}]">

    [{* Die JSON-Werte müssen HTML-escaped werden: ihre strukturellen
        Anführungszeichen würden das Attribut sonst vorzeitig beenden. *}]
    <div id="tabslfeedback-config"
         hidden
         data-url="[{$oViewConf->getTabslFeedbackSubmitUrl()|escape:'html'}]"
         data-stoken="[{$oViewConf->getSessionChallengeToken()|escape:'html'}]"
         data-position="[{$oViewConf->getTabslFeedbackButtonPosition()|escape:'html'}]"
         data-contact="[{if $oViewConf->showTabslFeedbackContactFields()}]1[{else}]0[{/if}]"
         data-subject="[{if $oViewConf->showTabslFeedbackSubjectField()}]1[{else}]0[{/if}]"
         data-screenshots="[{if $oViewConf->showTabslFeedbackScreenshots()}]1[{else}]0[{/if}]"
         data-autoopen="[{if $oViewConf->isTabslFeedbackAutoOpen()}]1[{else}]0[{/if}]"
         data-notice="[{$oViewConf->getTabslFeedbackNoticeText()|escape:'html'}]"
         data-turnstile-sitekey="[{if $oViewConf->isTabslFeedbackTurnstileActive()}][{$oViewConf->getTabslFeedbackTurnstileSiteKey()|escape:'html'}][{/if}]"
         data-limits="[{$oViewConf->getTabslFeedbackLimitsJson()|escape:'html'}]"
         data-texts="[{$oViewConf->getTabslFeedbackTextsJson()|escape:'html'}]"></div>

    [{if $oViewConf->shouldLoadTabslFeedbackTurnstileScript()}]
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    [{/if}]

    <script type="text/javascript" src="[{$oViewConf->getTabslFeedbackAssetUrl('out/src/js/tabslfeedback.min.js')}]" defer></script>
[{/if}]
