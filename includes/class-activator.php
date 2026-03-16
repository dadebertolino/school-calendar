<?php
namespace SchoolCalendar;

defined('ABSPATH') || exit;

class Activator {
    
    public static function activate() {
        self::create_tables();
        self::create_capabilities();
        self::seed_default_data();
        
        update_option('sc_version', SC_VERSION);
        flush_rewrite_rules();
    }
    
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // NOTA: Le tabelle plessi, classi, specializzazioni e anni_scolastici 
        // sono gestite dal plugin CBG (wp_cbg_plesso, wp_cbg_classe, etc.)
        // Questo plugin usa quelle tabelle esistenti.
        
        // Sub-calendari (per plesso)
        $sql_sub_calendari = "CREATE TABLE {$prefix}sub_calendari (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            plesso_id INT UNSIGNED NOT NULL,
            nome VARCHAR(100) NOT NULL,
            colore VARCHAR(7) NOT NULL DEFAULT '#2d7ff9',
            ordine INT UNSIGNED NOT NULL DEFAULT 0,
            attivo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY plesso_id (plesso_id),
            KEY attivo (attivo)
        ) $charset_collate;";
        
        // Calendari esterni (Google, iCal)
        $sql_calendari = "CREATE TABLE {$prefix}calendari_esterni (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(100) NOT NULL,
            tipo ENUM('google', 'ical') NOT NULL DEFAULT 'google',
            calendar_id VARCHAR(255) NOT NULL,
            credentials TEXT DEFAULT NULL,
            plesso_id INT UNSIGNED DEFAULT NULL,
            visibilita_default ENUM('pubblico', 'privato') NOT NULL DEFAULT 'pubblico',
            colore VARCHAR(7) DEFAULT NULL,
            sync_interval INT UNSIGNED NOT NULL DEFAULT 15,
            last_sync DATETIME DEFAULT NULL,
            attivo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY plesso_id (plesso_id)
        ) $charset_collate;";
        
        // Eventi
        $sql_eventi = "CREATE TABLE {$prefix}eventi (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            titolo VARCHAR(255) NOT NULL,
            descrizione TEXT DEFAULT NULL,
            data_inizio DATETIME NOT NULL,
            data_fine DATETIME NOT NULL,
            tutto_giorno TINYINT(1) NOT NULL DEFAULT 0,
            visibilita ENUM('pubblico', 'privato') NOT NULL DEFAULT 'pubblico',
            mostra_su_schermo TINYINT(1) NOT NULL DEFAULT 0,
            plesso_id INT UNSIGNED DEFAULT NULL,
            responsabile VARCHAR(255) DEFAULT NULL,
            luogo_scuola VARCHAR(255) DEFAULT NULL,
            luogo_fisico VARCHAR(255) DEFAULT NULL,
            luogo_lat DECIMAL(10,8) DEFAULT NULL,
            luogo_lng DECIMAL(11,8) DEFAULT NULL,
            risorsa VARCHAR(255) DEFAULT NULL,
            autore_id BIGINT UNSIGNED DEFAULT NULL,
            source ENUM('local', 'google', 'ical', 'booking') NOT NULL DEFAULT 'local',
            external_id VARCHAR(255) DEFAULT NULL,
            external_updated DATETIME DEFAULT NULL,
            calendar_id INT UNSIGNED DEFAULT NULL,
            booking_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY source_external (source, external_id),
            UNIQUE KEY booking_id (booking_id),
            KEY plesso_id (plesso_id),
            KEY autore_id (autore_id),
            KEY calendar_id (calendar_id),
            KEY data_inizio (data_inizio),
            KEY data_fine (data_fine),
            KEY visibilita (visibilita),
            KEY mostra_su_schermo (mostra_su_schermo)
        ) $charset_collate;";
        
        // Pivot eventi-classi (riferisce wp_cbg_classe.id)
        $sql_eventi_classi = "CREATE TABLE {$prefix}eventi_classi (
            evento_id BIGINT UNSIGNED NOT NULL,
            classe_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (evento_id, classe_id),
            KEY classe_id (classe_id)
        ) $charset_collate;";
        
        // Pivot eventi-sub_calendari
        $sql_eventi_sub_calendari = "CREATE TABLE {$prefix}eventi_sub_calendari (
            evento_id BIGINT UNSIGNED NOT NULL,
            sub_calendario_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (evento_id, sub_calendario_id),
            KEY sub_calendario_id (sub_calendario_id)
        ) $charset_collate;";
        
        // API Keys
        $sql_api_keys = "CREATE TABLE {$prefix}api_keys (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            api_key VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            nome VARCHAR(100) NOT NULL,
            permissions JSON DEFAULT NULL,
            last_used DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            attivo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql_sub_calendari);
        dbDelta($sql_calendari);
        dbDelta($sql_eventi);
        dbDelta($sql_eventi_classi);
        dbDelta($sql_eventi_sub_calendari);
        dbDelta($sql_api_keys);
    }
    
    private static function create_capabilities() {
        $caps = [
            'sc_view_public_events',
            'sc_view_private_events',
            'sc_create_events',
            'sc_edit_own_events',
            'sc_edit_all_events',
            'sc_delete_events',
            'sc_manage_settings',
            'sc_manage_api_keys',
            'sc_manage_sub_calendari',
        ];
        
        $role_caps = [
            'administrator' => $caps,
            'editor' => ['sc_view_public_events', 'sc_view_private_events', 'sc_create_events', 'sc_edit_own_events'],
            'author' => ['sc_view_public_events', 'sc_view_private_events', 'sc_create_events', 'sc_edit_own_events'],
            'subscriber' => ['sc_view_public_events', 'sc_view_private_events'],
        ];
        
        foreach ($role_caps as $role_name => $role_specific_caps) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($role_specific_caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }
    
    private static function seed_default_data() {
        // I plessi e le classi sono gestiti dal plugin CBG
        // Non è necessario creare dati di default
    }
}
