<?php
/**
 * Plugin Name: School Calendar
 * Description: Calendario scolastico multi-plesso con API REST e integrazione Google Calendar
 * Version: 1.8.8
 * Author: Davide "the Prof" Bertolino
 * Author URI: https://www.davidebertolino.it
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('SC_VERSION', '1.8.8');
define('SC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SC_TABLE_PREFIX', 'sc_');

// Autoload
spl_autoload_register(function ($class) {
    $prefix = 'SchoolCalendar\\';
    $base_dir = SC_PLUGIN_DIR . 'includes/';
    
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . strtolower(str_replace('\\', '-', $relative_class)) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Activation/Deactivation
register_activation_hook(__FILE__, ['SchoolCalendar\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['SchoolCalendar\\Activator', 'deactivate']);

// Initialize plugin at plugins_loaded
add_action('plugins_loaded', function () {
    SchoolCalendar\Plugin::instance();
});
