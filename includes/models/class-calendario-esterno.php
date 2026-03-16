<?php
namespace SchoolCalendar\Models;

defined('ABSPATH') || exit;

class CalendarioEsterno extends Model {
    protected static $table = 'calendari_esterni';
    
    protected static $fillable = [
        'nome',
        'tipo',
        'calendar_id',
        'credentials',
        'plesso_id',
        'visibilita_default',
        'colore',
        'sync_interval',
        'last_sync',
        'attivo',
    ];
    
    /**
     * Recupera il plesso associato
     */
    public function plesso() {
        return $this->plesso_id ? Plesso::find($this->plesso_id) : null;
    }
    
    /**
     * Recupera calendari attivi
     */
    public static function attivi() {
        return static::all(['attivo' => 1], 'nome ASC');
    }
    
    /**
     * Recupera calendari da sincronizzare
     */
    public static function daSincronizzare() {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $rows = $wpdb->get_results(
            "SELECT * FROM {$prefix}calendari_esterni 
             WHERE attivo = 1 
             AND (last_sync IS NULL OR last_sync < DATE_SUB(NOW(), INTERVAL sync_interval MINUTE))
             ORDER BY last_sync ASC",
            ARRAY_A
        );
        
        return array_map(fn($row) => new static($row), $rows);
    }
    
    /**
     * Aggiorna timestamp ultima sincronizzazione
     */
    public function updateLastSync() {
        global $wpdb;
        
        return $wpdb->update(
            static::table(),
            ['last_sync' => current_time('mysql')],
            ['id' => $this->id]
        );
    }
    
    /**
     * Get/Set credentials criptate
     */
    public function getCredentials() {
        if (empty($this->credentials)) {
            return null;
        }
        
        $decrypted = $this->decrypt($this->credentials);
        return $decrypted ? json_decode($decrypted, true) : null;
    }
    
    public function setCredentials($credentials) {
        if (is_array($credentials)) {
            $credentials = json_encode($credentials);
        }
        
        $this->attributes['credentials'] = $this->encrypt($credentials);
    }
    
    private function encrypt($data) {
        if (!defined('SECURE_AUTH_KEY')) {
            return base64_encode($data);
        }
        
        $key = hash('sha256', SECURE_AUTH_KEY, true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt($data) {
        if (!defined('SECURE_AUTH_KEY')) {
            return base64_decode($data);
        }
        
        $data = base64_decode($data);
        $key = hash('sha256', SECURE_AUTH_KEY, true);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Formatta per API response
     */
    public function toApiResponse() {
        $data = [
            'id' => (int) $this->id,
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'calendar_id' => $this->calendar_id,
            'plesso_id' => $this->plesso_id ? (int) $this->plesso_id : null,
            'visibilita_default' => $this->visibilita_default,
            'colore' => $this->colore,
            'sync_interval' => (int) $this->sync_interval,
            'last_sync' => $this->last_sync,
            'attivo' => (bool) $this->attivo,
            'has_credentials' => !empty($this->credentials),
        ];
        
        $plesso = $this->plesso();
        if ($plesso) {
            $data['plesso'] = $plesso->toApiResponse();
        }
        
        return $data;
    }
}
