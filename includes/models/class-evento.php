<?php
namespace SchoolCalendar\Models;

defined('ABSPATH') || exit;

class Evento extends Model {
    protected static $table = 'eventi';
    
    protected static $fillable = [
        'titolo',
        'descrizione',
        'data_inizio',
        'data_fine',
        'tutto_giorno',
        'visibilita',
        'mostra_su_schermo',
        'plesso_id',
        'responsabile',
        'luogo_scuola',
        'luogo_fisico',
        'luogo_lat',
        'luogo_lng',
        'risorsa',
        'autore_id',
        'source',
        'external_id',
        'external_updated',
        'calendar_id',
        'booking_id',
    ];
    
    /**
     * Recupera classi associate all'evento
     */
    public function classi() {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $classe_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT classe_id FROM {$prefix}eventi_classi WHERE evento_id = %d",
            $this->id
        ));
        
        if (empty($classe_ids)) {
            return [];
        }
        
        return Classe::query()->whereIn('id', $classe_ids)->get();
    }
    
    /**
     * Recupera ID classi associate
     */
    public function getClasseIds() {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT classe_id FROM {$prefix}eventi_classi WHERE evento_id = %d",
            $this->id
        ));
    }
    
    /**
     * Sincronizza classi associate
     */
    public function syncClassi(array $classe_ids) {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        // Rimuovi esistenti
        $wpdb->delete("{$prefix}eventi_classi", ['evento_id' => $this->id]);
        
        // Inserisci nuove
        foreach ($classe_ids as $classe_id) {
            $wpdb->insert("{$prefix}eventi_classi", [
                'evento_id' => $this->id,
                'classe_id' => $classe_id,
            ]);
        }
    }
    
    /**
     * Recupera sub-calendari associati
     */
    public function subCalendari() {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT sub_calendario_id FROM {$prefix}eventi_sub_calendari WHERE evento_id = %d",
            $this->id
        ));
        
        if (empty($ids)) {
            return [];
        }
        
        return array_filter(array_map(fn($id) => SubCalendario::find($id), $ids));
    }
    
    /**
     * Recupera ID sub-calendari associati
     */
    public function getSubCalendarioIds() {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT sub_calendario_id FROM {$prefix}eventi_sub_calendari WHERE evento_id = %d",
            $this->id
        ));
    }
    
    /**
     * Sincronizza sub-calendari associati
     */
    public function syncSubCalendari(array $sub_calendario_ids) {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        // Rimuovi esistenti
        $wpdb->delete("{$prefix}eventi_sub_calendari", ['evento_id' => $this->id]);
        
        // Inserisci nuovi
        foreach ($sub_calendario_ids as $sub_cal_id) {
            $wpdb->insert("{$prefix}eventi_sub_calendari", [
                'evento_id' => $this->id,
                'sub_calendario_id' => $sub_cal_id,
            ]);
        }
    }
    
    /**
     * Recupera il colore primario (dal primo sub-calendario o calendario esterno)
     */
    public function getColore() {
        // Prima prova dai sub-calendari
        $sub_calendari = $this->subCalendari();
        if (!empty($sub_calendari)) {
            return $sub_calendari[0]->colore;
        }
        
        // Poi dal calendario esterno
        if ($this->calendar_id) {
            $calendario = CalendarioEsterno::find($this->calendar_id);
            if ($calendario && $calendario->colore) {
                return $calendario->colore;
            }
        }
        
        return null;
    }
    
    /**
     * Recupera il plesso dell'evento
     */
    public function plesso() {
        return $this->plesso_id ? Plesso::find($this->plesso_id) : null;
    }
    
    /**
     * Recupera l'autore dell'evento
     */
    public function autore() {
        return $this->autore_id ? get_user_by('id', $this->autore_id) : null;
    }
    
    /**
     * Query eventi con filtri
     */
    public static function filter(array $params, $can_view_private = false) {
        $query = static::query();
        
        // Filtro visibilità
        if (!$can_view_private) {
            $query->where('visibilita', 'pubblico');
        }
        
        // Filtro date
        if (!empty($params['start'])) {
            $query->where('data_fine', '>=', $params['start']);
        }
        
        if (!empty($params['end'])) {
            $query->where('data_inizio', '<=', $params['end']);
        }
        
        // Filtro plesso - mostra eventi del plesso specifico + eventi globali (plesso_id NULL)
        if (!empty($params['plesso_id'])) {
            $query->whereRaw('(plesso_id = %d OR plesso_id IS NULL)', [$params['plesso_id']]);
        }
        
        // Filtro source
        if (!empty($params['source'])) {
            $sources = is_array($params['source']) ? $params['source'] : explode(',', $params['source']);
            $query->whereIn('source', $sources);
        }
        
        // Ordinamento
        $query->orderBy('data_inizio', 'ASC');
        
        // Paginazione
        if (!empty($params['limit'])) {
            $query->limit($params['limit']);
            
            if (!empty($params['offset'])) {
                $query->offset($params['offset']);
            }
        }
        
        $eventi = $query->get();
        
        // Filtro classe (post-query per gestire pivot)
        if (!empty($params['classe_id'])) {
            $eventi = array_filter($eventi, function($evento) use ($params) {
                $classe_ids = $evento->getClasseIds();
                // Se nessuna classe associata, l'evento è per tutti
                if (empty($classe_ids)) {
                    return true;
                }
                return in_array($params['classe_id'], $classe_ids);
            });
        }
        
        // Filtro sub-calendario (post-query per gestire pivot)
        if (!empty($params['sub_calendario_id'])) {
            $eventi = array_filter($eventi, function($evento) use ($params) {
                $sub_cal_ids = $evento->getSubCalendarioIds();
                if (empty($sub_cal_ids)) {
                    return false;
                }
                return in_array($params['sub_calendario_id'], $sub_cal_ids);
            });
        }
        
        return array_values($eventi);
    }
    
    /**
     * Verifica se è evento locale (editabile)
     */
    public function isLocal() {
        return $this->source === 'local';
    }
    
    /**
     * Override delete per pulire anche pivot
     */
    public function delete() {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $wpdb->delete("{$prefix}eventi_classi", ['evento_id' => $this->id]);
        $wpdb->delete("{$prefix}eventi_sub_calendari", ['evento_id' => $this->id]);
        
        return parent::delete();
    }
    
    /**
     * Formatta per API response
     */
    public function toApiResponse($include_relations = true) {
        $data = [
            'id' => (int) $this->id,
            'titolo' => $this->titolo,
            'descrizione' => $this->descrizione,
            'data_inizio' => $this->data_inizio,
            'data_fine' => $this->data_fine,
            'tutto_giorno' => (bool) $this->tutto_giorno,
            'visibilita' => $this->visibilita,
            'mostra_su_schermo' => (int) $this->mostra_su_schermo,
            'plesso_id' => $this->plesso_id ? (int) $this->plesso_id : null,
            'responsabile' => $this->responsabile,
            'luogo_scuola' => $this->luogo_scuola,
            'luogo_fisico' => $this->luogo_fisico,
            'luogo_lat' => $this->luogo_lat ? (float) $this->luogo_lat : null,
            'luogo_lng' => $this->luogo_lng ? (float) $this->luogo_lng : null,
            'risorsa' => $this->risorsa,
            'source' => $this->source,
            'booking_id' => $this->booking_id ? (int) $this->booking_id : null,
            'editable' => $this->isLocal(),
        ];
        
        if ($include_relations) {
            $data['classe_ids'] = array_map('intval', $this->getClasseIds());
            $data['sub_calendario_ids'] = array_map('intval', $this->getSubCalendarioIds());
            
            $plesso = $this->plesso();
            $data['plesso'] = $plesso ? $plesso->toApiResponse() : null;
            
            $sub_calendari = $this->subCalendari();
            $data['sub_calendari'] = array_map(fn($sc) => $sc->toApiResponse(), $sub_calendari);
            
            $autore = $this->autore();
            $data['autore'] = $autore ? [
                'id' => $autore->ID,
                'nome' => $autore->display_name,
            ] : null;
        }
        
        return $data;
    }
    
    /**
     * Formatta per FullCalendar
     */
    public function toFullCalendarEvent() {
        $colore = $this->getColore();
        $sub_calendari = $this->subCalendari();
        
        return [
            'id' => (int) $this->id,
            'title' => $this->titolo,
            'start' => $this->data_inizio,
            'end' => $this->data_fine,
            'allDay' => (bool) $this->tutto_giorno,
            'extendedProps' => [
                'descrizione' => $this->descrizione,
                'visibilita' => $this->visibilita,
                'plesso_id' => $this->plesso_id ? (int) $this->plesso_id : null,
                'classe_ids' => array_map('intval', $this->getClasseIds()),
                'sub_calendario_ids' => array_map('intval', $this->getSubCalendarioIds()),
                'sub_calendari' => array_map(fn($sc) => $sc->toApiResponse(), $sub_calendari),
                'responsabile' => $this->responsabile,
                'luogo_scuola' => $this->luogo_scuola,
                'luogo_fisico' => $this->luogo_fisico,
                'luogo_lat' => $this->luogo_lat ? (float) $this->luogo_lat : null,
                'luogo_lng' => $this->luogo_lng ? (float) $this->luogo_lng : null,
                'source' => $this->source,
                'autore_id' => $this->autore_id ? (int) $this->autore_id : null,
                'editable' => $this->isLocal(),
                'colore' => $colore,
            ],
            'classNames' => [
                'sc-event-' . $this->visibilita,
                'sc-event-source-' . $this->source,
            ],
        ];
    }
}
