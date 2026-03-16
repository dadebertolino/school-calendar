<?php
namespace SchoolCalendar\Models;

defined('ABSPATH') || exit;

/**
 * Model Classe - usa la tabella wp_cbg_classe del plugin Gestione Scuola
 */
class Classe {
    
    public $id;
    public $descrizione;
    public $aula;
    public $plesso;
    public $specializzazione;
    
    private static function getTable() {
        global $wpdb;
        return $wpdb->prefix . 'cbg_classe';
    }
    
    public function __construct($data = null) {
        if ($data) {
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }
    }
    
    /**
     * Trova classe per ID
     */
    public static function find($id) {
        global $wpdb;
        $table = self::getTable();
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));
        
        return $row ? new self($row) : null;
    }
    
    /**
     * Tutte le classi
     */
    public static function all($order = 'descrizione ASC') {
        global $wpdb;
        $table = self::getTable();
        
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY {$order}");
        
        return array_map(fn($row) => new self($row), $rows);
    }
    
    /**
     * Classi per plesso
     */
    public static function byPlesso($plesso_id, $specializzazione_id = null) {
        global $wpdb;
        $table = self::getTable();
        
        $sql = "SELECT * FROM {$table} WHERE plesso = %d";
        $params = [$plesso_id];
        
        if ($specializzazione_id) {
            $sql .= " AND specializzazione = %d";
            $params[] = $specializzazione_id;
        }
        
        $sql .= " ORDER BY descrizione";
        
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        return array_map(fn($row) => new self($row), $rows);
    }
    
    /**
     * Classi per specializzazione
     */
    public static function bySpecializzazione($specializzazione_id) {
        global $wpdb;
        $table = self::getTable();
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE specializzazione = %d ORDER BY descrizione",
            $specializzazione_id
        ));
        
        return array_map(fn($row) => new self($row), $rows);
    }
    
    /**
     * Recupera il plesso della classe
     */
    public function plesso() {
        return $this->plesso ? Plesso::find($this->plesso) : null;
    }
    
    /**
     * Recupera la specializzazione della classe
     */
    public function specializzazione() {
        if (!$this->specializzazione) {
            return null;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'cbg_specializzazione';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $this->specializzazione
        ));
    }
    
    // =========================================================================
    // METODI PER ANNO SCOLASTICO (dalla tabella cbg_anno_scolastico)
    // =========================================================================
    
    /**
     * Ottiene l'ID dell'anno scolastico corrente
     */
    public static function getAnnoCorrenteId() {
        if (function_exists('cbg_get_anno_corrente_id')) {
            return cbg_get_anno_corrente_id();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'cbg_anno_scolastico';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return null;
        }
        
        $id = $wpdb->get_var("SELECT id FROM {$table} WHERE corrente = 1 LIMIT 1");
        return $id ? (int) $id : null;
    }
    
    /**
     * Ottiene l'anno scolastico corrente (oggetto completo)
     */
    public static function getAnnoCorrente() {
        if (function_exists('cbg_get_anno_corrente')) {
            return cbg_get_anno_corrente();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'cbg_anno_scolastico';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return null;
        }
        
        return $wpdb->get_row("SELECT * FROM {$table} WHERE corrente = 1 LIMIT 1");
    }
    
    /**
     * Ottiene tutti gli anni scolastici
     */
    public static function getAllAnniScolastici($order = 'DESC') {
        if (function_exists('cbg_get_all_anni_scolastici')) {
            return cbg_get_all_anni_scolastici($order);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'cbg_anno_scolastico';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }
        
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY descrizione {$order}");
    }
    
    /**
     * Formatta per API response
     */
    public function toApiResponse() {
        $plesso = $this->plesso();
        $spec = $this->specializzazione();
        
        return [
            'id' => (int) $this->id,
            'nome' => $this->descrizione,
            'aula' => $this->aula,
            'plesso_id' => $this->plesso ? (int) $this->plesso : null,
            'plesso_nome' => $plesso ? $plesso->descrizione : null,
            'specializzazione_id' => $this->specializzazione ? (int) $this->specializzazione : null,
            'specializzazione_nome' => $spec ? $spec->descrizione : null,
        ];
    }
}
