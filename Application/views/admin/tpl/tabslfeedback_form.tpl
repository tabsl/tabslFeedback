[{include file="headitem.tpl" title="TABSLFEEDBACK_TITLE"|oxmultilangassign}]

<link rel="stylesheet" type="text/css" href="[{$oViewConf->getTabslFeedbackAssetUrl('out/src/css/tabslfeedback.css')}]">

<div class="tabslfeedback-widget tabslfeedback-admin-page">

    [{if !$tabslfeedbackAvailable}]

        <p class="tabslfeedback-message tabslfeedback-message--error">
            [{oxmultilang ident="TABSLFEEDBACK_ADMIN_UNAVAILABLE"}]
        </p>

    [{else}]

        <h1 class="tabslfeedback-dialog__title">[{oxmultilang ident="TABSLFEEDBACK_TITLE"}]</h1>

        [{if $tabslfeedbackResultMessage}]
            <p class="tabslfeedback-message tabslfeedback-message--[{if $tabslfeedbackResultOk}]success[{else}]error[{/if}]">
                [{$tabslfeedbackResultMessage|escape:'html'}]
            </p>
        [{/if}]

        [{* Die Bilder reisen als base64 in normalen POST-Feldern mit, nicht als
            Datei-Upload — dieselbe Entscheidung wie im Frontend (planning.md §7 Nr. 2). *}]
        <form id="tabslfeedback-admin-form"
              action="[{$oViewConf->getSelfLink()}]"
              method="post"
              data-limits="[{$tabslfeedbackLimits|escape:'html'}]"
              data-texts="[{$tabslfeedbackTexts|escape:'html'}]">

            <input type="hidden" name="cl" value="tabslfeedback_form">
            <input type="hidden" name="fnc" value="submit">
            <input type="hidden" name="stoken" value="[{$oViewConf->getSessionChallengeToken()}]">
            <input type="hidden" name="fb_meta" value="">

            <p class="tabslfeedback-message tabslfeedback-message--error tabslfeedback-message--client" hidden></p>

            <div class="tabslfeedback-field">
                <label class="tabslfeedback-label" for="tabslfeedback-admin-message">
                    [{oxmultilang ident="TABSLFEEDBACK_MESSAGE_LABEL"}]
                </label>
                <textarea class="tabslfeedback-textarea"
                          id="tabslfeedback-admin-message"
                          name="fb_message"
                          rows="7">[{$tabslfeedbackValues.fb_message|escape:'html'}]</textarea>
            </div>

            <div class="tabslfeedback-field">
                <span class="tabslfeedback-label">[{oxmultilang ident="TABSLFEEDBACK_SCREENSHOTS_LABEL"}]</span>
                <span class="tabslfeedback-hint">[{oxmultilang ident="TABSLFEEDBACK_SCREENSHOTS_HINT"}]</span>
                <ul class="tabslfeedback-previews"></ul>
                <div class="tabslfeedback-image-values"></div>
            </div>

            [{if $tabslfeedbackShowContactFields}]
                <div class="tabslfeedback-field">
                    <label class="tabslfeedback-label" for="tabslfeedback-admin-name">
                        [{oxmultilang ident="TABSLFEEDBACK_NAME_LABEL"}]
                    </label>
                    <input class="tabslfeedback-input"
                           id="tabslfeedback-admin-name"
                           type="text"
                           name="fb_name"
                           value="[{$tabslfeedbackValues.fb_name|escape:'html'}]">
                </div>

                <div class="tabslfeedback-field">
                    <label class="tabslfeedback-label" for="tabslfeedback-admin-email">
                        [{oxmultilang ident="TABSLFEEDBACK_EMAIL_LABEL"}]
                    </label>
                    <input class="tabslfeedback-input"
                           id="tabslfeedback-admin-email"
                           type="email"
                           name="fb_email"
                           value="[{$tabslfeedbackValues.fb_email|escape:'html'}]">
                </div>
            [{/if}]

            [{if $tabslfeedbackShowAiNotice}]
                <p class="tabslfeedback-notice">[{oxmultilang ident="TABSLFEEDBACK_AI_NOTICE"}]</p>
            [{/if}]

            <div class="tabslfeedback-actions">
                <button class="tabslfeedback-button tabslfeedback-button--primary" type="submit">
                    [{oxmultilang ident="TABSLFEEDBACK_SUBMIT"}]
                </button>
            </div>
        </form>

        <script type="text/javascript" src="[{$oViewConf->getTabslFeedbackAssetUrl('out/src/js/tabslfeedback.js')}]" defer></script>

    [{/if}]

</div>

[{include file="bottomitem.tpl"}]
