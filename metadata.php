<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @copyright (c) Tobias Merkl | 2026
 * @link https://oxid-module.eu
 * @link https://github.com/tabsl/tabslFeedback
 * @package tabslFeedback
 **/

/**
 * Metadata version
 */
$sMetadataVersion = '2.1';

// tabsl module id
$psModuleId = 'tabslFeedback';
$psModuleName = '<b>tabsl</b>Feedback';
$psModuleVersion = '1.2.0';

// tabsl module description
$psModuleDesc = 'Feedback-Formular für Backend und Frontend mit KI-Aufbereitung und Ticket-Anlage in GitLab oder weclapp.';

/**
 * Module information
 */
$aModule = [
    'id' => $psModuleId,
    'title' => [
        'de' => $psModuleName,
        'en' => $psModuleName,
    ],
    'description' => [
        'de' => $psModuleDesc,
        'en' => 'Feedback form for backend and frontend with AI processing and ticket creation in GitLab or weclapp.',
    ],
    'thumbnail' => '',
    'version' => $psModuleVersion,
    'author' => 'Tobias Merkl',
    'url' => 'https://github.com/tabsl/tabslFeedback',
    'email' => '',
    'extend' => [
        \OxidEsales\Eshop\Core\ViewConfig::class => \Tabsl\Feedback\Core\ViewConfig::class,
    ],
    'controllers' => [
        'tabslfeedback_submit' => \Tabsl\Feedback\Controller\SubmitController::class,
        'tabslfeedback_form' => \Tabsl\Feedback\Controller\Admin\FeedbackFormController::class,
    ],
    'templates' => [
        'tabslfeedback_form.tpl' => 'tabsl/tabslFeedback/Application/views/admin/tpl/tabslfeedback_form.tpl',
    ],
    'blocks' => [
        [
            'template' => 'layout/base.tpl',
            'block' => 'base_js',
            'file' => 'views/blocks/feedback_frontend_widget.tpl',
        ],
        [
            'template' => 'include/header_links.tpl',
            'block' => 'admin_header_links',
            'file' => 'views/blocks/feedback_admin_link.tpl',
        ],
    ],
    'settings' => [
        // Grundeinstellungen
        ['group' => 'tabslfeedback_main', 'name' => 'tabslfeedback_admin_enabled', 'type' => 'bool', 'value' => false],
        ['group' => 'tabslfeedback_main', 'name' => 'tabslfeedback_frontend_enabled', 'type' => 'bool', 'value' => false],
        ['group' => 'tabslfeedback_main', 'name' => 'tabslfeedback_button_position', 'type' => 'select', 'value' => 'bottom-right', 'constraints' => 'bottom-left|bottom-right|center|none'],
        ['group' => 'tabslfeedback_main', 'name' => 'tabslfeedback_ticket_target', 'type' => 'select', 'value' => 'gitlab', 'constraints' => 'gitlab|weclapp'],
        ['group' => 'tabslfeedback_main', 'name' => 'tabslfeedback_notice_text', 'type' => 'str', 'value' => 'Ihre Meldung wird inklusive eventuell angehängter Screenshots zur Bearbeitung verwendet.'],
        // GitLab
        ['group' => 'tabslfeedback_gitlab', 'name' => 'tabslfeedback_gitlab_url', 'type' => 'str', 'value' => ''],
        ['group' => 'tabslfeedback_gitlab', 'name' => 'tabslfeedback_gitlab_project_id', 'type' => 'str', 'value' => ''],
        ['group' => 'tabslfeedback_gitlab', 'name' => 'tabslfeedback_gitlab_token', 'type' => 'password', 'value' => ''],
        ['group' => 'tabslfeedback_gitlab', 'name' => 'tabslfeedback_gitlab_assignee_id', 'type' => 'str', 'value' => ''],
        // weclapp
        ['group' => 'tabslfeedback_weclapp', 'name' => 'tabslfeedback_weclapp_url', 'type' => 'str', 'value' => ''],
        ['group' => 'tabslfeedback_weclapp', 'name' => 'tabslfeedback_weclapp_token', 'type' => 'password', 'value' => ''],
        ['group' => 'tabslfeedback_weclapp', 'name' => 'tabslfeedback_weclapp_ticket_status_id', 'type' => 'str', 'value' => ''],
        ['group' => 'tabslfeedback_weclapp', 'name' => 'tabslfeedback_weclapp_ticket_priority_id', 'type' => 'str', 'value' => ''],
        ['group' => 'tabslfeedback_weclapp', 'name' => 'tabslfeedback_weclapp_ticket_channel_id', 'type' => 'str', 'value' => ''],
        ['group' => 'tabslfeedback_weclapp', 'name' => 'tabslfeedback_weclapp_ticket_category_id', 'type' => 'str', 'value' => ''],
        ['group' => 'tabslfeedback_weclapp', 'name' => 'tabslfeedback_weclapp_assignee_id', 'type' => 'str', 'value' => ''],
        // KI-Aufbereitung
        ['group' => 'tabslfeedback_openai', 'name' => 'tabslfeedback_ai_provider', 'type' => 'select', 'value' => 'openai', 'constraints' => 'none|openai|anthropic'],
        ['group' => 'tabslfeedback_openai', 'name' => 'tabslfeedback_openai_key', 'type' => 'password', 'value' => ''],
        ['group' => 'tabslfeedback_openai', 'name' => 'tabslfeedback_openai_model', 'type' => 'str', 'value' => 'gpt-4o-mini'],
        ['group' => 'tabslfeedback_openai', 'name' => 'tabslfeedback_anthropic_key', 'type' => 'password', 'value' => ''],
        ['group' => 'tabslfeedback_openai', 'name' => 'tabslfeedback_anthropic_model', 'type' => 'str', 'value' => 'claude-haiku-4-5-20251001'],
        ['group' => 'tabslfeedback_openai', 'name' => 'tabslfeedback_ticket_language', 'type' => 'select', 'value' => 'source', 'constraints' => 'source|en'],
        // Datenschutz
        ['group' => 'tabslfeedback_privacy', 'name' => 'tabslfeedback_show_contact_fields', 'type' => 'bool', 'value' => false],
        ['group' => 'tabslfeedback_privacy', 'name' => 'tabslfeedback_send_customer_data', 'type' => 'bool', 'value' => false],
        ['group' => 'tabslfeedback_privacy', 'name' => 'tabslfeedback_screenshots_enabled', 'type' => 'bool', 'value' => true],
    ],
    'events' => [
        'onActivate' => '\Tabsl\Feedback\Core\Setup::onActivate',
        'onDeactivate' => '\Tabsl\Feedback\Core\Setup::onDeactivate',
    ],
    'smartyPluginDirectories' => [],
];
