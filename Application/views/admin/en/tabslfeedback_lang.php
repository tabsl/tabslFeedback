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
        'TABSLFEEDBACK_ADMIN_UNAVAILABLE' => 'The feedback form is not available. Check under Extensions → Modules → tabslFeedback whether the backend form is enabled and the GitLab address, project ID and token are set.',

        // Setting groups
        'SHOP_MODULE_GROUP_tabslfeedback_main' => 'General',
        'SHOP_MODULE_GROUP_tabslfeedback_gitlab' => 'GitLab',
        'SHOP_MODULE_GROUP_tabslfeedback_openai' => 'AI processing',
        'SHOP_MODULE_GROUP_tabslfeedback_privacy' => 'Privacy',

        // General
        'SHOP_MODULE_tabslfeedback_admin_enabled' => 'Backend form enabled',
        'HELP_SHOP_MODULE_tabslfeedback_admin_enabled' => 'Adds a feedback link to the top left of the backend header. The link stays hidden while the GitLab settings are incomplete.',

        'SHOP_MODULE_tabslfeedback_frontend_enabled' => 'Show feedback button in the storefront',
        'HELP_SHOP_MODULE_tabslfeedback_frontend_enabled' => 'Adds a feedback button to every storefront page. While disabled, the form remains reachable by calling any storefront page with ?tabslFeedback=1 — useful for testers without showing the button to every visitor. The setting therefore controls visibility, not availability: as soon as the GitLab settings are complete, the shop accepts feedback. Running it publicly without the tabslTurnstile module is at your own risk.',

        'SHOP_MODULE_tabslfeedback_button_position' => 'Button position',
        'HELP_SHOP_MODULE_tabslfeedback_button_position' => 'Where the feedback button appears in the storefront. "No button" shows no button — the form then only opens where a page calls window.tabslFeedback.open() itself. It is still embedded only while "Show feedback button in the storefront" is enabled.',
        'SHOP_MODULE_tabslfeedback_button_position_bottom-left' => 'Bottom left',
        'SHOP_MODULE_tabslfeedback_button_position_bottom-right' => 'Bottom right',
        'SHOP_MODULE_tabslfeedback_button_position_center' => 'Bottom centre',
        'SHOP_MODULE_tabslfeedback_button_position_none' => 'No button',

        // GitLab
        'SHOP_MODULE_tabslfeedback_gitlab_url' => 'GitLab address',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_url' => 'Base address of the GitLab instance including the scheme, e.g. https://gitlab.com — without /api/v4. Please use https: over http the access token travels unencrypted. Without a scheme the configuration counts as incomplete and no feedback entry point appears. Required.',

        'SHOP_MODULE_tabslfeedback_gitlab_project_id' => 'Project ID',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_project_id' => 'Numeric ID of the target project, available from the project overview page via "Copy project ID". Required.',

        'SHOP_MODULE_tabslfeedback_gitlab_token' => 'Access token',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_token' => 'Project access token with the "api" scope, limited to the target project. Preferable to a personal access token, whose reach would be much wider. Required.',

        'SHOP_MODULE_tabslfeedback_gitlab_assignee_id' => 'Assignee (user ID)',
        'HELP_SHOP_MODULE_tabslfeedback_gitlab_assignee_id' => 'Numeric GitLab user ID that new issues are assigned to, shown on that person\'s GitLab profile. Leave empty to create issues without an assignee.',

        // AI processing
        'SHOP_MODULE_tabslfeedback_openai_key' => 'OpenAI API key',
        'HELP_SHOP_MODULE_tabslfeedback_openai_key' => 'Without a key the issue is still created — carrying the unchanged report instead of a processed version. Images are never sent to OpenAI.',

        'SHOP_MODULE_tabslfeedback_openai_model' => 'Model',
        'HELP_SHOP_MODULE_tabslfeedback_openai_model' => 'The model used for processing. The default gpt-4o-mini is sufficient for this task; change it only once the model is discontinued.',

        'SHOP_MODULE_tabslfeedback_ticket_language' => 'Issue language',
        'HELP_SHOP_MODULE_tabslfeedback_ticket_language' => 'Whether title and description keep the language of the report or are always written in English.',
        'SHOP_MODULE_tabslfeedback_ticket_language_source' => 'Keep the language of the report',
        'SHOP_MODULE_tabslfeedback_ticket_language_en' => 'Always English',

        // Privacy
        'SHOP_MODULE_tabslfeedback_show_contact_fields' => 'Show name and email field',
        'HELP_SHOP_MODULE_tabslfeedback_show_contact_fields' => 'Adds two optional fields for follow-up questions. The form remains submittable without them.',

        'SHOP_MODULE_tabslfeedback_send_customer_data' => 'Include customer data',
        'HELP_SHOP_MODULE_tabslfeedback_send_customer_data' => 'Adds customer number, name and email of signed-in customers to the issue. While disabled, no customer data is transmitted.',
    ],
    require __DIR__ . '/../../../messages/en.php'
);
