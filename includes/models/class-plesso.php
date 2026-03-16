<?php
namespace SchoolCalendar\Models;

defined('ABSPATH') || exit;

/**
 * Model Plesso - usa la tabella wp_cbg_plesso del plugin Gestione Scuola
 */
class Plesso {
    
    public $id;
    public $descrizione;
    public $descrizione_pubblica;
    
    private static function getTable() {
        global $wpdb;
        return $wpdb->prefix . 'cbg_plesso';
    }
    
    public function __construct($data = null) {
        if ($data) {
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }
    }
    
    /**
     * Trova plesso per ID
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
     * Tutti i plessi
     */
    public static function all($order = 'descrizione ASC') {
        global $wpdb;
        $table = self::getTable();
        
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY {$order}");
        
        return array_map(fn($row) => new self($row), $rows);
    }
    
    /**
     * Plessi attivi (tutti, non c'è campo attivo nella tabella CBG)
     */
    public static function attivi() {
        return self::all();
    }
    
    /**
     * Specializzazioni di questo plesso
     */
    public function specializzazioni() {
        global $wpdb;
        $table = $wpdb->prefix . 'cbg_specializzazione';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE plesso = %d ORDER BY descrizione",
            $this->id
        ));
    }
    
    /**
     * Classi di questo plesso
     */
    public function classi() {
        return Classe::byPlesso($this->id);
    }
    
    /**
     * Formatta per API response
     */
    public function toApiResponse($include_classi = false, $include_specializzazioni = false) {
        $response = [
            'id' => (int) $this->id,
            'nome' => $this->descrizione,
            'descrizione_pubblica' => $this->descrizione_pubblica,
        ];
        
        if ($include_specializzazioni) {
            $response['specializzazioni'] = array_map(function($s) {
                return [
                    'id' => (int) $s->id,
                    'nome' => $s->descrizione,
                    'descrizione_pubblica' => $s->descrizione_pubblica,
                ];
            }, $this->specializzazioni());
        }
        
        if ($include_classi) {
            $response['classi'] = array_map(fn($c) => $c->toApiResponse(), $this->classi());
        }
        
        return $response;
    }
}
