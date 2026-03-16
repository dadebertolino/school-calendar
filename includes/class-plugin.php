<?php
namespace SchoolCalendar;

defined('ABSPATH') || exit;

class Plugin {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once SC_PLUGIN_DIR . 'includes/class-activator.php';
        require_once SC_PLUGIN_DIR . 'includes/class-auth.php';
        require_once SC_PLUGIN_DIR . 'includes/class-api.php';
        require_once SC_PLUGIN_DIR . 'includes/models/class-model.php';
        require_once SC_PLUGIN_DIR . 'includes/models/class-plesso.php';
        require_once SC_PLUGIN_DIR . 'includes/models/class-classe.php';
        require_once SC_PLUGIN_DIR . 'includes/models/class-evento.php';
        require_once SC_PLUGIN_DIR . 'includes/models/class-calendario-esterno.php';
        require_once SC_PLUGIN_DIR . 'includes/models/class-sub-calendario.php';
        require_once SC_PLUGIN_DIR . 'includes/class-google-sync.php';
        require_once SC_PLUGIN_DIR . 'includes/class-ical-sync.php';
        require_once SC_PLUGIN_DIR . 'includes/class-sync-manager.php';
        require_once SC_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once SC_PLUGIN_DIR . 'includes/class-widget.php';
    }
    
    private function init_hooks() {
        add_action('rest_api_init', [new Api(), 'register_routes']);
        
        // Inizializza sync manager
        $syncManager = new SyncManager();
        $syncManager->init();
        
        // Inizializza shortcodes
        new Shortcodes();
        
        if (is_admin()) {
            require_once SC_PLUGIN_DIR . 'admin/class-admin.php';
            new Admin();
        }
    }
}
