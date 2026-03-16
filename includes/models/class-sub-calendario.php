<?php
namespace SchoolCalendar\Models;

defined('ABSPATH') || exit;

class SubCalendario extends Model {
    protected static $table = 'sub_calendari';
    
    protected static $fillable = [
        'plesso_id',
        'nome',
        'colore',
        'ordine',
        'attivo',
    ];
    
    /**
     * Recupera il plesso associato
     */
    public function plesso() {
        return $this->plesso_id ? Plesso::find($this->plesso_id) : null;
    }
    
    /**
     * Recupera sub-calendari attivi
     */
    public static function attivi($plesso_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . SC_TABLE_PREFIX . 'sub_calendari';
        
        $sql = "SELECT * FROM {$table} WHERE attivo = 1";
        
        if ($plesso_id) {
            $sql .= $wpdb->prepare(" AND plesso_id = %d", $plesso_id);
        }
        
        $sql .= " ORDER BY ordine ASC, nome ASC";
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(fn($row) => new static($row), $rows ?: []);
    }
    
    /**
     * Recupera sub-calendari per plesso
     */
    public static function perPlesso($plesso_id) {
        global $wpdb;
        $table = $wpdb->prefix . SC_TABLE_PREFIX . 'sub_calendari';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE plesso_id = %d ORDER BY ordine ASC, nome ASC",
            $plesso_id
        );
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(fn($row) => new static($row), $rows ?: []);
    }
    
    /**
     * Recupera eventi associati
     */
    public function eventi($start = null, $end = null) {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $sql = "SELECT e.* FROM {$prefix}eventi e
                INNER JOIN {$prefix}eventi_sub_calendari esc ON e.id = esc.evento_id
                WHERE esc.sub_calendario_id = %d";
        
        $params = [$this->id];
        
        if ($start) {
            $sql .= " AND e.data_fine >= %s";
            $params[] = $start;
        }
        
        if ($end) {
            $sql .= " AND e.data_inizio <= %s";
            $params[] = $end;
        }
        
        $sql .= " ORDER BY e.data_inizio ASC";
        
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        return array_map(fn($row) => new Evento($row), $rows);
    }
    
    /**
     * Formatta per API response
     */
    public function toApiResponse() {
        return [
            'id' => (int) $this->id,
            'plesso_id' => (int) $this->plesso_id,
            'nome' => $this->nome,
            'colore' => $this->colore,
            'ordine' => (int) $this->ordine,
            'attivo' => (bool) $this->attivo,
        ];
    }
}
