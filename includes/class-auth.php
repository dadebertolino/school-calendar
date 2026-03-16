<?php
namespace SchoolCalendar;

defined('ABSPATH') || exit;

class Auth {
    
    /**
     * Verifica autenticazione da API Key header
     */
    public static function authenticate_api_key(\WP_REST_Request $request) {
        $api_key = $request->get_header('X-SC-API-Key');
        
        if (empty($api_key)) {
            return null; // Fallback su autenticazione WP standard
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $key_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}api_keys WHERE api_key = %s AND attivo = 1",
            $api_key
        ));
        
        if (!$key_data) {
            return new \WP_Error('invalid_api_key', 'API Key non valida', ['status' => 401]);
        }
        
        // Verifica scadenza
        if ($key_data->expires_at && strtotime($key_data->expires_at) < time()) {
            return new \WP_Error('expired_api_key', 'API Key scaduta', ['status' => 401]);
        }
        
        // Aggiorna last_used
        $wpdb->update(
            "{$prefix}api_keys",
            ['last_used' => current_time('mysql')],
            ['id' => $key_data->id]
        );
        
        // Imposta utente corrente
        wp_set_current_user($key_data->user_id);
        
        return true;
    }
    
    /**
     * Genera nuova API Key
     */
    public static function generate_api_key($user_id, $nome, $permissions = null, $expires_at = null) {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $api_key = wp_generate_password(64, false);
        
        $result = $wpdb->insert("{$prefix}api_keys", [
            'api_key' => $api_key,
            'user_id' => $user_id,
            'nome' => $nome,
            'permissions' => $permissions ? json_encode($permissions) : null,
            'expires_at' => $expires_at,
        ]);
        
        return $result ? $api_key : false;
    }
    
    /**
     * Revoca API Key
     */
    public static function revoke_api_key($key_id) {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        return $wpdb->update(
            "{$prefix}api_keys",
            ['attivo' => 0],
            ['id' => $key_id]
        );
    }
    
    /**
     * Lista API Keys per utente
     */
    public static function get_user_api_keys($user_id) {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, nome, LEFT(api_key, 8) as key_preview, last_used, expires_at, attivo, created_at 
             FROM {$prefix}api_keys 
             WHERE user_id = %d 
             ORDER BY created_at DESC",
            $user_id
        ));
    }
    
    /**
     * Verifica permesso specifico
     */
    public static function can($capability) {
        return current_user_can($capability);
    }
    
    /**
     * Verifica se utente può vedere eventi privati
     * Tutti gli utenti loggati possono vedere eventi privati
     */
    public static function can_view_private() {
        return is_user_logged_in();
    }
    
    /**
     * Verifica se utente può modificare evento
     */
    public static function can_edit_event($evento) {
        if (current_user_can('sc_edit_all_events')) {
            return true;
        }
        
        if (current_user_can('sc_edit_own_events') && $evento->autore_id == get_current_user_id()) {
            return true;
        }
        
        return false;
    }
}
