<?php
/*
Plugin Name: MI Data Exports
Plugin URI: https://github.com/One-Hoopy-Frood/Monster-Insights-Lite-Export-Helper
Description: Early helper plugin to assist with exporting MonsterInsights Lite data. Work in progress.
Version: 0.1.0
Author: Your Name
Author URI: https://example.com
License: MIT
License URI: https://opensource.org/licenses/MIT
Text Domain: mi-helper-reports
Domain Path: /languages
Requires at least: 5.8
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('MIHR_VERSION', '0.1.0');
define('MIHR_PLUGIN_FILE', __FILE__);
define('MIHR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MIHR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload simple class file(s). For now, manually require.
require_once MIHR_PLUGIN_DIR . 'includes/class-mi-helper-reports.php';

// Activation/Deactivation hooks.
register_activation_hook(__FILE__, function () {
    // Placeholder for future setup (DB, caps, etc.)
});

register_deactivation_hook(__FILE__, function () {
    // Placeholder for cleanup on deactivation
});

// Bootstrap plugin.
add_action('plugins_loaded', function () {
    load_plugin_textdomain('mi-helper-reports', false, dirname(plugin_basename(__FILE__)) . '/languages');

    $plugin = new MI_Helper_Reports();
    $plugin->init();
});
