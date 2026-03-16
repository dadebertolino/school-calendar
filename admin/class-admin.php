<?php
namespace SchoolCalendar;

defined('ABSPATH') || exit;

class Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function add_menu() {
        add_menu_page(
            'School Calendar',
            'Calendario',
            'sc_view_public_events',
            'school-calendar',
            [$this, 'render_dashboard'],
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'school-calendar',
            'Dashboard',
            'Dashboard',
            'sc_view_public_events',
            'school-calendar',
            [$this, 'render_dashboard']
        );
        
        add_submenu_page(
            'school-calendar',
            'Eventi',
            'Eventi',
            'sc_create_events',
            'school-calendar-eventi',
            [$this, 'render_eventi']
        );
        
        add_submenu_page(
            'school-calendar',
            'Plessi e Classi',
            'Plessi e Classi',
            'sc_manage_settings',
            'school-calendar-plessi',
            [$this, 'render_plessi']
        );
        
        add_submenu_page(
            'school-calendar',
            'Calendari Esterni',
            'Calendari Esterni',
            'sc_manage_settings',
            'school-calendar-esterni',
            [$this, 'render_calendari_esterni']
        );
        
        add_submenu_page(
            'school-calendar',
            'API Keys',
            'API Keys',
            'sc_manage_api_keys',
            'school-calendar-api-keys',
            [$this, 'render_api_keys']
        );
        
        add_submenu_page(
            'school-calendar',
            'Sub-calendari',
            'Sub-calendari',
            'sc_manage_settings',
            'school-calendar-sub-calendari',
            [$this, 'render_sub_calendari']
        );
        
        add_submenu_page(
            'school-calendar',
            'Permessi Utenti',
            'Permessi',
            'sc_manage_settings',
            'school-calendar-permessi',
            [$this, 'render_permessi']
        );
        
        add_submenu_page(
            'school-calendar',
            'Importa ICS',
            'Importa ICS',
            'sc_manage_settings',
            'school-calendar-importa',
            [$this, 'render_importa']
        );
    }
    
    public function enqueue_assets($hook) {
        if (strpos($hook, 'school-calendar') === false) {
            return;
        }
        
        wp_enqueue_style(
            'sc-admin',
            SC_PLUGIN_URL . 'admin/css/admin.css',
            [],
            SC_VERSION
        );
        
        wp_enqueue_script(
            'sc-admin',
            SC_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            SC_VERSION,
            true
        );
        
        wp_localize_script('sc-admin', 'scAdmin', [
            'apiUrl' => rest_url('school-calendar/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
    
    public function render_dashboard() {
        include SC_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public function render_eventi() {
        include SC_PLUGIN_DIR . 'admin/views/eventi.php';
    }
    
    public function render_plessi() {
        include SC_PLUGIN_DIR . 'admin/views/plessi.php';
    }
    
    public function render_calendari_esterni() {
        include SC_PLUGIN_DIR . 'admin/views/calendari-esterni.php';
    }
    
    public function render_api_keys() {
        include SC_PLUGIN_DIR . 'admin/views/api-keys.php';
    }
    
    public function render_sub_calendari() {
        include SC_PLUGIN_DIR . 'admin/views/sub-calendari.php';
    }
    
    public function render_permessi() {
        include SC_PLUGIN_DIR . 'admin/views/permessi.php';
    }
    
    public function render_importa() {
        include SC_PLUGIN_DIR . 'admin/views/importa.php';
    }
}
