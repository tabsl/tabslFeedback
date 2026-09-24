<?php
/**
 * Backend language file — English.
 *
 * @package tabslFeedback
 **/

$sLangName = 'English';

$aLang = array_merge(
    [
        'charset' => 'UTF-8',

        // Navigation
        'TABSLFEEDBACK_ADMIN_LINK' => 'Feedback',
        'TABSLFEEDBACK_ADMIN_UNAVAILABLE' => 'The feedback form is not available. Check under Extensions → Modules → tabslFeedback whether the backend form is enabled and the selected ticket target is fully set up: for GitLab the address, project ID and token, for weclapp the https address and API token, for Jira Cloud the address, email, API token and project.',

        // Setting groups
        'SHOP_MODULE_GROUP_tabslfeedback_main' => 'General',
        'SHOP_MODULE_GROUP_tabslfeedback_gitlab' => 'GitLab',
        'SHOP_MODULE_GROUP_tabslfeedback_weclapp' => 'weclapp',
        'SHOP_MODULE_GROUP_tabslfeedback_jira' => 'Jira Cloud',
        'SHOP_MODULE_GROUP_tabslfeedback_openai' => 'AI processing',
        'SHOP_MODULE_GROUP_tabslfeedback_privacy' => 'Privacy',

        // General
        'SHOP_MODULE_tabslfeedback_admin_enabled' => 'Backend form enabled',
        'HELP_SHOP_MODULE_tabslfeedback_admin_enabled' => 'Adds a feedback link to the top left of the backend header. The link stays hidden while the selected ticket target is not fully set up.',

        'SHOP_MODULE_tabslfeedback_frontend_enabled' => 'Show feedback button in the storefront',
        'HELP_SHOP_MODULE_tabslfeedback_frontend_enabled' => 'Adds a feedback button to every storefront page. While disabled, the form remains reachable by calling any storefront page with ?tabslFeedback=1 — useful for testers without showing the button to every visitor. The setting therefore controls visibility, not availability: as soon as the selected ticket target is fully set up, the shop accepts feedback. Running it publicly without the tabslTurnstile module is at your own risk.',

        'SHOP_MODULE_tabslfeedback_button_position' => 'Button position',
        'HELP_SHOP_MODULE_tabslfeedback_button_position' => 'Where the feedback button appears in the storefront. "No button" shows no button — the form then only opens where a page calls window.tabslFeedback.open() itself. It is still embedded only while "Show feedback button in the storefront" is enabled.',
        'SHOP_MODULE_tabslfeedback_button_position_bottom-left' => 'Bottom left',
        'SHOP_MODULE_tabslfeedback_button_position_bottom-right' => 'Bottom right',
        'SHOP_MODULE_tabslfeedback_button_position_center' => 'Bottom centre',
        'SHOP_MODULE_tabslfeedback_button_position_none' => 'No button',

        'SHOP_MODULE_tabslfeedback_notice_text' => 'Notice text in the form',
        'HELP_SHOP_MODULE_tabslfeedback_notice_text' => 'Short text shown right before the submit button, in both the backend and storefront form. Leave empty to omit the notice. The default text mentions that screenshots are transmitted — adjust it once the scope or recipient of the transmission changes, for example with AI processing enabled.',

        'SHOP_MODULE_tabslfeedback_ticket_target' => 'Ticket target',
        'HELP_SHOP_MODULE_tabslfeedback_ticket_target' => 'Where a report turns into a ticket: as an issue in GitLab, as a helpdesk ticket in weclapp or as an issue in Jira Cloud. Only the settings of the matching group apply.',
        'SHOP_MODULE_tabslfeedback_ticket_target_gitlab' => 'GitLab',
        'SHOP_MODULE_tabslfeedback_ticket_target_weclapp' => 'weclapp',
        'SHOP_MODULE_tabslfeedback_ticket_target_jira' => 'Jira Cloud',

        // GitLab
        'SHOP_MODULE_tabslfeedback_gitlab_url' => 'GitLab address',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_url' => 'Base address of the GitLab instance including the scheme, e.g. https://gitlab.com — without /api/v4. Please use https: over http the access token travels unencrypted. Without a scheme the configuration counts as incomplete and no feedback entry point appears. Required.',

        'SHOP_MODULE_tabslfeedback_gitlab_project_id' => 'Project ID',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_project_id' => 'Numeric ID of the target project, available from the project overview page via "Copy project ID". Required.',

        'SHOP_MODULE_tabslfeedback_gitlab_token' => 'Access token',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_token' => 'Project access token with the "api" scope, limited to the target project. Preferable to a personal access token, whose reach would be much wider. Required.',

        'SHOP_MODULE_tabslfeedback_gitlab_assignee_id' => 'Assignee (user ID)',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_assignee_id' => 'Numeric GitLab user ID that new issues are assigned to, shown on that person\'s GitLab profile. Leave empty to create issues without an assignee.',

        // weclapp
        'SHOP_MODULE_tabslfeedback_weclapp_url' => 'weclapp address',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_url' => 'Address of the weclapp tenant, e.g. https://company.weclapp.com. A trailing /webapp/api/v2 is ignored. Only https is accepted; without https the configuration counts as incomplete and no feedback entry point appears. Required for ticket target weclapp.',

        'SHOP_MODULE_tabslfeedback_weclapp_token' => 'API token',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_token' => 'API token of a weclapp user, found under user name → My settings → API token. The token carries all rights of its user, so create a dedicated user with helpdesk rights only. Tickets are created as this user. Generating a new token invalidates the previous one. Required for ticket target weclapp.',

        'SHOP_MODULE_tabslfeedback_weclapp_ticket_status_id' => 'Ticket status (ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_ticket_status_id' => 'Numeric ID of the status new tickets start with. Shown in the weclapp address bar while the status is open in the helpdesk settings. Leave empty for the tenant default.',

        'SHOP_MODULE_tabslfeedback_weclapp_ticket_priority_id' => 'Priority (ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_ticket_priority_id' => 'Numeric ID of the priority for new tickets, found the same way as the status. Strongly recommended: the priority is a required ticket field in weclapp and no default is documented. Without it weclapp may reject every report; the shop log then names ticketPriorityId.',

        'SHOP_MODULE_tabslfeedback_weclapp_ticket_channel_id' => 'Channel (ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_ticket_channel_id' => 'Numeric ID of the ticket channel, for example a dedicated "Shop feedback" channel. Strongly recommended: the channel is a required ticket field in weclapp, and a default channel only exists if the tenant has set one.',

        'SHOP_MODULE_tabslfeedback_weclapp_ticket_category_id' => 'Category (ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_ticket_category_id' => 'Numeric ID of the ticket category. Leave empty for no category.',

        'SHOP_MODULE_tabslfeedback_weclapp_assignee_id' => 'Assignee (user ID)',
        'HELP_SHOP_MODULE_tabslfeedback_weclapp_assignee_id' => 'Numeric weclapp user ID that new tickets are assigned to. Leave empty for no assignment by the module; assignment rules in weclapp still apply.',

        // Jira Cloud
        'SHOP_MODULE_tabslfeedback_jira_url' => 'Jira address',
        'HELP_SHOP_MODULE_tabslfeedback_jira_url' => 'Address of the Jira Cloud site, e.g. https://company.atlassian.net. Jira Cloud only, no Data Center or Server. For the token of an Atlassian service account, enter the gateway address https://api.atlassian.com/ex/jira/{cloud ID} instead; the cloud ID is shown at https://company.atlassian.net/_edge/tenant_info. A trailing /rest/api/3 is ignored. Only https is accepted. Required for ticket target Jira Cloud.',

        'SHOP_MODULE_tabslfeedback_jira_email' => 'Account email',
        'HELP_SHOP_MODULE_tabslfeedback_jira_email' => 'Email address of the Atlassian account or service account the API token belongs to. Required for ticket target Jira Cloud.',

        'SHOP_MODULE_tabslfeedback_jira_token' => 'API token',
        'HELP_SHOP_MODULE_tabslfeedback_jira_token' => 'API token, created at id.atlassian.com → Security → API tokens. A token expires after one year at most; afterwards no issues are created and the shop log reports http 401. The token carries the rights of its account, so use a dedicated account that may only create issues in the target project. Required for ticket target Jira Cloud.',

        'SHOP_MODULE_tabslfeedback_jira_project_key' => 'Project key',
        'HELP_SHOP_MODULE_tabslfeedback_jira_project_key' => 'Key of the target project, e.g. SHOP, or its numeric ID. Required for ticket target Jira Cloud.',

        'SHOP_MODULE_tabslfeedback_jira_issue_type' => 'Issue type',
        'HELP_SHOP_MODULE_tabslfeedback_jira_issue_type' => 'Name of the issue type, e.g. Task or Bug, or its numeric ID. The ID does not depend on the language and is therefore more robust than the name. The type must exist in the project and must not have required fields besides summary and description, otherwise Jira rejects every report; the shop log then names the field. Leave empty for Task.',

        'SHOP_MODULE_tabslfeedback_jira_assignee_account_id' => 'Assignee (account ID)',
        'HELP_SHOP_MODULE_tabslfeedback_jira_assignee_account_id' => 'Atlassian account ID of the person new issues are assigned to, shown in the address of their Jira profile. The person must be assignable in the project, otherwise Jira rejects every report. Leave empty for the project default assignment.',

        // AI processing
        'SHOP_MODULE_tabslfeedback_ai_provider' => 'AI provider',
        'HELP_SHOP_MODULE_tabslfeedback_ai_provider' => 'Whether and through which service the report is turned into a title and description. "Without AI" skips any external transmission entirely — the form then shows a subject field instead, whose content is used directly as the ticket title. With OpenAI or Anthropic, the matching API key remains required; without it, the ticket is created without processing as well.',
        'SHOP_MODULE_tabslfeedback_ai_provider_none' => 'Without AI',
        'SHOP_MODULE_tabslfeedback_ai_provider_openai' => 'OpenAI',
        'SHOP_MODULE_tabslfeedback_ai_provider_anthropic' => 'Anthropic (Claude)',

        'SHOP_MODULE_tabslfeedback_openai_key' => 'OpenAI API key',
        'HELP_SHOP_MODULE_tabslfeedback_openai_key' => 'Only effective while AI provider is set to OpenAI. Without a key the ticket is still created — carrying the unchanged report instead of a processed version. Images are never sent to OpenAI.',

        'SHOP_MODULE_tabslfeedback_openai_model' => 'OpenAI model',
        'HELP_SHOP_MODULE_tabslfeedback_openai_model' => 'The model used for processing. The default gpt-4o-mini is sufficient for this task; change it only once the model is discontinued.',

        'SHOP_MODULE_tabslfeedback_anthropic_key' => 'Anthropic API key',
        'HELP_SHOP_MODULE_tabslfeedback_anthropic_key' => 'Only effective while AI provider is set to Anthropic. Without a key the ticket is still created — carrying the unchanged report instead of a processed version. Images are never sent to Anthropic.',

        'SHOP_MODULE_tabslfeedback_anthropic_model' => 'Anthropic model',
        'HELP_SHOP_MODULE_tabslfeedback_anthropic_model' => 'The model used for processing. The default claude-haiku-4-5-20251001 is sufficient for this task; change it only once the model is discontinued.',

        'SHOP_MODULE_tabslfeedback_ticket_language' => 'Ticket language',
        'HELP_SHOP_MODULE_tabslfeedback_ticket_language' => 'Whether title and description keep the language of the report or are always written in English. Also applies to the fixed ticket headings when AI processing is off.',
        'SHOP_MODULE_tabslfeedback_ticket_language_source' => 'Keep the language of the report',
        'SHOP_MODULE_tabslfeedback_ticket_language_en' => 'Always English',

        // Privacy
        'SHOP_MODULE_tabslfeedback_show_contact_fields' => 'Show name and email field',
        'HELP_SHOP_MODULE_tabslfeedback_show_contact_fields' => 'Adds two optional fields for follow-up questions. The form remains submittable without them.',

        'SHOP_MODULE_tabslfeedback_send_customer_data' => 'Include customer data',
        'HELP_SHOP_MODULE_tabslfeedback_send_customer_data' => 'Adds customer number, name and email of signed-in customers to the ticket. While disabled, no customer data is transmitted.',

        'SHOP_MODULE_tabslfeedback_screenshots_enabled' => 'Allow screenshots',
        'HELP_SHOP_MODULE_tabslfeedback_screenshots_enabled' => 'Shows the screenshot feature in the form. While disabled, the whole section is omitted — images sent regardless are discarded as well.',
    ],
    require __DIR__ . '/../../../messages/en.php'
);
