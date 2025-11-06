<?php

if (!defined('ABSPATH')) {
    exit;
}

class MI_Helper_Reports
{
    public function init(): void
    {
        // Hook placeholders: add admin screens, export actions, etc.
        add_action('admin_menu', [$this, 'register_admin_menu']);
    }

    public function register_admin_menu(): void
    {
        add_management_page(
            __('MI Data Exports', 'mi-helper-reports'),
            __('MI Data Exports', 'mi-helper-reports'),
            'manage_options',
            'mi-helper-reports',
            [$this, 'render_tools_page']
        );
    }

    public function render_tools_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mi-helper-reports'));
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('MI Data Exports', 'mi-helper-reports') . '</h1>';
        echo '<p>' . esc_html__('This is a work-in-progress helper to export MonsterInsights Lite data.', 'mi-helper-reports') . '</p>';
        echo '</div>';
    }
}
