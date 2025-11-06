<?php

if (!defined('ABSPATH')) {
    exit;
}


class MI_Helper_Reports
{
    private const INSIGHTS_PARENT_SLUG = 'monsterinsights_reports';

    /** @var string|null */
    private $submenu_hook = null;

    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('monsterinsights_api_request_body', [$this, 'clamp_future_dates']);
    }

    public function register_admin_menu(): void
    {
        if ($this->register_insights_menu_item()) {
            return;
        }

        // Fallback location when MonsterInsights is not available.
        add_management_page(
            __('MI Data Exports', 'mi-helper-reports'),
            __('MI Data Exports', 'mi-helper-reports'),
            'manage_options',
            'mi-helper-reports',
            [$this, 'render_tools_page']
        );
    }

    private function register_insights_menu_item(): bool
    {
        if (!defined('MONSTERINSIGHTS_VERSION')) {
            return false;
        }

        $this->submenu_hook = add_submenu_page(
            self::INSIGHTS_PARENT_SLUG,
            __('MI Data Exports', 'mi-helper-reports'),
            __('MI Data Exports', 'mi-helper-reports'),
            'manage_options',
            'mi-helper-reports',
            [$this, 'render_tools_page']
        );

        if (!$this->submenu_hook) {
            return false;
        }

        $this->move_submenu_to_top(self::INSIGHTS_PARENT_SLUG, 'mi-helper-reports');

        return true;
    }

    private function move_submenu_to_top(string $parent_slug, string $menu_slug): void
    {
        global $submenu;

        if (empty($submenu[$parent_slug])) {
            return;
        }

        foreach ($submenu[$parent_slug] as $index => $entry) {
            if (!isset($entry[2]) || $entry[2] !== $menu_slug) {
                continue;
            }

            $item = $entry;
            unset($submenu[$parent_slug][$index]);
            array_unshift($submenu[$parent_slug], $item);
            break;
        }
    }

    public function enqueue_assets(string $hook): void
    {
        if (!$this->should_enqueue_for_hook($hook)) {
            return;
        }

        $handle = 'mihr-admin';

        wp_register_script(
            $handle,
            MIHR_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            MIHR_VERSION,
            true
        );

        wp_localize_script(
            $handle,
            'mihrSettings',
            [
                'today'   => current_time('Y-m-d'),
                'strings' => [
                    'futureDateMessage' => __('Data for MonsterInsights is only available up to today. The end date has been reset to today.', 'mi-helper-reports'),
                ],
            ]
        );

        wp_enqueue_script($handle);
    }

    private function should_enqueue_for_hook(string $hook): bool
    {
        if (false !== strpos($hook, 'mi-helper-reports')) {
            return true;
        }

        return false !== strpos($hook, self::INSIGHTS_PARENT_SLUG);
    }

    public function clamp_future_dates(array $body): array
    {
        if (empty($body['end'])) {
            return $body;
        }

        $today = current_time('Y-m-d');
        $end   = $this->create_date_from_string($body['end']);
        $today_obj = $this->create_date_from_string($today);

        if (!$end || !$today_obj || $end <= $today_obj) {
            return $body;
        }

        $body['end'] = $today;

        if (!empty($body['start'])) {
            $start = $this->create_date_from_string($body['start']);
            if ($start && $start > $today_obj) {
                $body['start'] = $today;
            }
        }

        if (!empty($body['compare_end'])) {
            $compare_end = $this->create_date_from_string($body['compare_end']);
            if ($compare_end && $compare_end > $today_obj) {
                $body['compare_end'] = $today;
            }
        }

        if (!empty($body['compare_start'])) {
            $compare_start = $this->create_date_from_string($body['compare_start']);
            if ($compare_start && $compare_start > $today_obj) {
                $body['compare_start'] = $today;
            }
        }

        return $body;
    }

    private function create_date_from_string(string $date_string): ?\DateTimeImmutable
    {
        try {
            $date = new \DateTimeImmutable($date_string);
        } catch (\Exception $e) {
            return null;
        }

        return $date;
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
