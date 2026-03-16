<?php
namespace SchoolCalendar;

use SchoolCalendar\Models\Plesso;
use SchoolCalendar\Models\Classe;
use SchoolCalendar\Models\Evento;
use SchoolCalendar\Models\CalendarioEsterno;
use SchoolCalendar\Models\SubCalendario;

defined('ABSPATH') || exit;

class Api {
    
    const NAMESPACE = 'school-calendar/v1';
    
    public function register_routes() {
        // Middleware autenticazione API Key
        add_filter('rest_pre_dispatch', [$this, 'authenticate_request'], 10, 3);
        
        // Plessi
        register_rest_route(self::NAMESPACE, '/plessi', [
            'methods' => 'GET',
            'callback' => [$this, 'get_plessi'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route(self::NAMESPACE, '/plessi/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_plesso'],
            'permission_callback' => '__return_true',
        ]);
        
        // Classi
        register_rest_route(self::NAMESPACE, '/classi', [
            'methods' => 'GET',
            'callback' => [$this, 'get_classi'],
            'permission_callback' => '__return_true',
        ]);
        
        // Anni scolastici (dalla tabella CBG esterna)
        register_rest_route(self::NAMESPACE, '/anni-scolastici', [
            'methods' => 'GET',
            'callback' => [$this, 'get_anni_scolastici'],
            'permission_callback' => '__return_true',
        ]);
        
        // Specializzazioni (dalla tabella CBG esterna)
        register_rest_route(self::NAMESPACE, '/specializzazioni', [
            'methods' => 'GET',
            'callback' => [$this, 'get_specializzazioni'],
            'permission_callback' => '__return_true',
        ]);
        
        // Sub-calendari
        register_rest_route(self::NAMESPACE, '/sub-calendari', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_sub_calendari'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_sub_calendario'],
                'permission_callback' => [$this, 'can_manage_sub_calendari'],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/sub-calendari/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_sub_calendario'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'PUT,PATCH',
                'callback' => [$this, 'update_sub_calendario'],
                'permission_callback' => [$this, 'can_manage_sub_calendari'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_sub_calendario'],
                'permission_callback' => [$this, 'can_manage_sub_calendari'],
            ],
        ]);
        
        // Eventi - CRUD
        register_rest_route(self::NAMESPACE, '/eventi', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_eventi'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_evento'],
                'permission_callback' => [$this, 'can_create_evento'],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/eventi/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_evento'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'PUT,PATCH',
                'callback' => [$this, 'update_evento'],
                'permission_callback' => [$this, 'can_edit_evento'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_evento'],
                'permission_callback' => [$this, 'can_delete_evento'],
            ],
        ]);
        
        // I miei eventi
        register_rest_route(self::NAMESPACE, '/eventi/miei', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_miei_eventi'],
                'permission_callback' => [$this, 'can_create_evento'],
            ],
        ]);
        
        // Calendari esterni (solo admin)
        register_rest_route(self::NAMESPACE, '/calendari-esterni', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_calendari_esterni'],
                'permission_callback' => [$this, 'is_admin'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_calendario_esterno'],
                'permission_callback' => [$this, 'is_admin'],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/calendari-esterni/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_calendario_esterno'],
                'permission_callback' => [$this, 'is_admin'],
            ],
            [
                'methods' => 'PUT,PATCH',
                'callback' => [$this, 'update_calendario_esterno'],
                'permission_callback' => [$this, 'is_admin'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_calendario_esterno'],
                'permission_callback' => [$this, 'is_admin'],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/calendari-esterni/(?P<id>\d+)/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_calendario_esterno'],
            'permission_callback' => [$this, 'is_admin'],
        ]);
        
        // API Keys management
        register_rest_route(self::NAMESPACE, '/api-keys', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_api_keys'],
                'permission_callback' => [$this, 'can_manage_api_keys'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_api_key'],
                'permission_callback' => [$this, 'can_manage_api_keys'],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/api-keys/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'revoke_api_key'],
            'permission_callback' => [$this, 'can_manage_api_keys'],
        ]);
        
        // =========================================================================
        // ENDPOINT BOOKING (per integrazione con sistemi esterni di prenotazione)
        // =========================================================================
        register_rest_route(self::NAMESPACE, '/eventi/booking', [
            'methods' => 'POST',
            'callback' => [$this, 'create_booking_event'],
            'permission_callback' => [$this, 'authenticate_api_key_for_booking'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/eventi/booking/(?P<booking_id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_booking_event'],
                'permission_callback' => [$this, 'authenticate_api_key_for_booking'],
            ],
            [
                'methods' => 'PUT,PATCH',
                'callback' => [$this, 'update_booking_event'],
                'permission_callback' => [$this, 'authenticate_api_key_for_booking'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_booking_event'],
                'permission_callback' => [$this, 'authenticate_api_key_for_booking'],
            ],
        ]);
        
        // =========================================================================
        // ENDPOINT SCHERMO (eventi da mostrare su display)
        // =========================================================================
        register_rest_route(self::NAMESPACE, '/schermo', [
            'methods' => 'GET',
            'callback' => [$this, 'get_eventi_schermo'],
            'permission_callback' => '__return_true', // Pubblico per i display
        ]);
        
        // =========================================================================
        // ENDPOINT CBG (compatibile con CBGChat)
        // =========================================================================
        register_rest_route('cbg/v1', '/calendar', [
            'methods' => ['GET', 'OPTIONS'],
            'callback' => [$this, 'cbg_get_calendar'],
            'permission_callback' => [$this, 'cbg_authenticate'],
        ]);
        
        // Aggiungi CORS headers per endpoint CBG
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', [$this, 'add_cors_headers'], 15);
        }, 15);
    }
    
    /**
     * Aggiungi CORS headers
     */
    public function add_cors_headers($value) {
        $origin = get_http_origin();
        
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: X-API-Key, Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Max-Age: 86400');
        
        return $value;
    }
    
    /**
     * Autenticazione CBG API Key
     */
    public function cbg_authenticate(\WP_REST_Request $request) {
        // Gestisci preflight OPTIONS
        if ($request->get_method() === 'OPTIONS') {
            return true;
        }
        
        $api_key = $request->get_header('X-API-Key');
        
        if (empty($api_key)) {
            return new \WP_Error('missing_api_key', 'API Key richiesta', ['status' => 401]);
        }
        
        // Verifica API Key nella tabella delle chiavi
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}api_keys WHERE api_key = %s AND attivo = 1",
            $api_key
        ));
        
        if (!$key) {
            return new \WP_Error('invalid_api_key', 'API Key non valida', ['status' => 401]);
        }
        
        // Aggiorna last_used
        $wpdb->update(
            "{$prefix}api_keys",
            ['last_used' => current_time('mysql')],
            ['id' => $key->id]
        );
        
        return true;
    }
    
    /**
     * Endpoint CBG Calendar
     * GET /wp-json/cbg/v1/calendar
     * 
     * Parametri:
     * - plesso: nome plesso (opzionale)
     * - from: data inizio ISO (opzionale)
     * - to: data fine ISO (opzionale)
     */
    public function cbg_get_calendar(\WP_REST_Request $request) {
        $plesso_nome = $request->get_param('plesso');
        $from = $request->get_param('from');
        $to = $request->get_param('to');
        
        // Trova plesso_id dal nome se fornito
        $plesso_id = null;
        if (!empty($plesso_nome)) {
            $plessi = Plesso::attivi();
            foreach ($plessi as $p) {
                if (strcasecmp($p->descrizione, $plesso_nome) === 0 || 
                    strcasecmp($p->descrizione_pubblica, $plesso_nome) === 0) {
                    $plesso_id = $p->id;
                    break;
                }
            }
        }
        
        // Costruisci parametri filtro
        $params = [];
        if ($plesso_id) {
            $params['plesso_id'] = $plesso_id;
        }
        if ($from) {
            $params['start'] = date('Y-m-d', strtotime($from));
        }
        if ($to) {
            $params['end'] = date('Y-m-d', strtotime($to));
        }
        
        // Recupera eventi (solo pubblici per API esterna, o tutti se autenticati)
        $eventi = Evento::filter($params, true);
        
        // Formatta risposta CBG
        $result = [];
        foreach ($eventi as $evento) {
            $plesso = $evento->plesso();
            $autore = $evento->autore();
            
            $item = [
                'id' => (int) $evento->id,
                'titolo' => $evento->titolo,
                'data_inizio' => $this->format_datetime_iso($evento->data_inizio),
            ];
            
            // Campi opzionali
            if (!empty($evento->descrizione)) {
                $item['descrizione'] = $evento->descrizione;
            }
            
            if (!empty($evento->data_fine)) {
                $item['data_fine'] = $this->format_datetime_iso($evento->data_fine);
            }
            
            $item['tutto_il_giorno'] = (bool) $evento->tutto_giorno;
            
            if ($plesso) {
                $item['plesso'] = $plesso->descrizione_pubblica ?: $plesso->descrizione;
            }
            
            // Colore: prima controlla se c'è colore personalizzato dal calendario
            $colore_personalizzato = null;
            if ($evento->calendar_id) {
                $calendario = CalendarioEsterno::find($evento->calendar_id);
                if ($calendario && $calendario->colore) {
                    $colore_personalizzato = $calendario->colore;
                }
            }
            
            if ($colore_personalizzato) {
                $item['colore'] = $colore_personalizzato;
            } elseif ($evento->source === 'google') {
                $item['colore'] = '#00b894';
            } elseif ($evento->source === 'ical') {
                $item['colore'] = '#fdcb6e';
            } elseif ($evento->visibilita === 'privato') {
                $item['colore'] = '#6c5ce7';
            } else {
                $item['colore'] = '#2d7ff9';
            }
            
            if ($autore) {
                $item['creato_da'] = $autore->user_email;
            }
            
            $result[] = $item;
        }
        
        return rest_ensure_response(['eventi' => $result]);
    }
    
    /**
     * Formatta datetime in ISO 8601
     */
    private function format_datetime_iso($datetime) {
        if (empty($datetime)) {
            return null;
        }
        $timestamp = strtotime($datetime);
        return date('Y-m-d\TH:i:s', $timestamp);
    }
    
    /**
     * Middleware autenticazione
     */
    public function authenticate_request($result, $server, $request) {
        if (strpos($request->get_route(), '/' . self::NAMESPACE) !== 0) {
            return $result;
        }
        
        $auth_result = Auth::authenticate_api_key($request);
        
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        
        return $result;
    }
    
    // =========================================================================
    // PLESSI
    // =========================================================================
    
    public function get_plessi(\WP_REST_Request $request) {
        $include_classi = $request->get_param('include_classi') === 'true';
        $include_spec = $request->get_param('include_specializzazioni') === 'true';
        $plessi = Plesso::attivi();
        
        return array_map(
            fn($p) => $p->toApiResponse($include_classi, $include_spec),
            $plessi
        );
    }
    
    public function get_plesso(\WP_REST_Request $request) {
        $plesso = Plesso::find($request['id']);
        
        if (!$plesso) {
            return new \WP_Error('not_found', 'Plesso non trovato', ['status' => 404]);
        }
        
        return $plesso->toApiResponse(true, true);
    }
    
    // =========================================================================
    // CLASSI
    // =========================================================================
    
    public function get_classi(\WP_REST_Request $request) {
        $plesso_id = $request->get_param('plesso_id');
        $specializzazione_id = $request->get_param('specializzazione_id');
        
        if ($plesso_id) {
            $classi = Classe::byPlesso($plesso_id, $specializzazione_id);
        } elseif ($specializzazione_id) {
            $classi = Classe::bySpecializzazione($specializzazione_id);
        } else {
            $classi = Classe::all();
        }
        
        return array_map(fn($c) => $c->toApiResponse(), $classi);
    }
    
    /**
     * GET /anni-scolastici - Lista anni scolastici dalla tabella CBG
     */
    public function get_anni_scolastici(\WP_REST_Request $request) {
        $anni = Classe::getAllAnniScolastici('DESC');
        $anno_corrente_id = Classe::getAnnoCorrenteId();
        
        return array_map(function($anno) use ($anno_corrente_id) {
            return [
                'id' => (int) $anno->id,
                'descrizione' => $anno->descrizione,
                'corrente' => ((int) $anno->id === $anno_corrente_id),
            ];
        }, $anni);
    }
    
    /**
     * GET /specializzazioni - Lista specializzazioni dalla tabella CBG
     */
    public function get_specializzazioni(\WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'cbg_specializzazione';
        
        $plesso_id = $request->get_param('plesso_id');
        
        if ($plesso_id) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, p.descrizione as plesso_nome 
                 FROM {$table} s
                 LEFT JOIN {$wpdb->prefix}cbg_plesso p ON s.plesso = p.id
                 WHERE s.plesso = %d
                 ORDER BY s.descrizione",
                $plesso_id
            ));
        } else {
            $rows = $wpdb->get_results(
                "SELECT s.*, p.descrizione as plesso_nome 
                 FROM {$table} s
                 LEFT JOIN {$wpdb->prefix}cbg_plesso p ON s.plesso = p.id
                 ORDER BY p.descrizione, s.descrizione"
            );
        }
        
        return array_map(function($s) {
            return [
                'id' => (int) $s->id,
                'nome' => $s->descrizione,
                'descrizione_pubblica' => $s->descrizione_pubblica ?? null,
                'plesso_id' => $s->plesso ? (int) $s->plesso : null,
                'plesso_nome' => $s->plesso_nome ?? null,
            ];
        }, $rows);
    }
    
    // =========================================================================
    // EVENTI
    // =========================================================================
    
    public function get_eventi(\WP_REST_Request $request) {
        $params = [
            'start' => $request->get_param('start'),
            'end' => $request->get_param('end'),
            'plesso_id' => $request->get_param('plesso_id'),
            'classe_id' => $request->get_param('classe_id'),
            'sub_calendario_id' => $request->get_param('sub_calendario_id'),
            'source' => $request->get_param('source'),
            'limit' => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
        ];
        
        $format = $request->get_param('format') ?: 'default';
        $can_view_private = Auth::can_view_private();
        
        $eventi = Evento::filter($params, $can_view_private);
        
        if ($format === 'fullcalendar') {
            return array_map(fn($e) => $e->toFullCalendarEvent(), $eventi);
        }
        
        return array_map(fn($e) => $e->toApiResponse(), $eventi);
    }
    
    public function get_miei_eventi(\WP_REST_Request $request) {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('sc_manage_settings');
        $include_past = $request->get_param('include_past') ? true : false;
        
        $sql = "SELECT * FROM {$prefix}eventi WHERE source = 'local'";
        
        // Se non è admin, mostra solo i propri eventi
        if (!$is_admin) {
            $sql .= $wpdb->prepare(" AND autore_id = %d", $current_user_id);
        }
        
        // Se non include passati, filtra per data >= oggi
        if (!$include_past) {
            $sql .= " AND data_fine >= CURDATE()";
        }
        
        $sql .= " ORDER BY data_inizio ASC";
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        $eventi = array_map(fn($row) => new Evento($row), $rows ?: []);
        
        return array_map(fn($e) => $e->toApiResponse(), $eventi);
    }
    
    public function get_evento(\WP_REST_Request $request) {
        $evento = Evento::find($request['id']);
        
        if (!$evento) {
            return new \WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }
        
        // Verifica visibilità
        if ($evento->visibilita === 'privato' && !Auth::can_view_private()) {
            return new \WP_Error('forbidden', 'Non autorizzato', ['status' => 403]);
        }
        
        return $evento->toApiResponse();
    }
    
    public function create_evento(\WP_REST_Request $request) {
        $data = $this->validate_evento_data($request->get_json_params());
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        $data['autore_id'] = get_current_user_id();
        $data['source'] = 'local';
        
        $evento = new Evento($data);
        
        if (!$evento->save()) {
            return new \WP_Error('db_error', 'Errore salvataggio evento', ['status' => 500]);
        }
        
        // Sync classi
        if (!empty($data['classe_ids'])) {
            $evento->syncClassi($data['classe_ids']);
        }
        
        // Sync sub-calendari
        if (!empty($data['sub_calendario_ids'])) {
            $evento->syncSubCalendari($data['sub_calendario_ids']);
        }
        
        return rest_ensure_response([
            'success' => true,
            'evento' => $evento->toApiResponse(),
        ]);
    }
    
    public function update_evento(\WP_REST_Request $request) {
        $evento = Evento::find($request['id']);
        
        if (!$evento) {
            return new \WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }
        
        if (!$evento->isLocal()) {
            return new \WP_Error('readonly', 'Eventi esterni non modificabili', ['status' => 400]);
        }
        
        // Verifica permessi: admin può tutto, altri solo i propri eventi
        $current_user_id = get_current_user_id();
        if (!current_user_can('sc_manage_settings') && $evento->autore_id != $current_user_id) {
            return new \WP_Error('forbidden', 'Puoi modificare solo i tuoi eventi', ['status' => 403]);
        }
        
        $data = $this->validate_evento_data($request->get_json_params(), true);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        $evento->fill($data);
        
        if (!$evento->save()) {
            return new \WP_Error('db_error', 'Errore aggiornamento evento', ['status' => 500]);
        }
        
        // Sync classi se fornite
        if (isset($data['classe_ids'])) {
            $evento->syncClassi($data['classe_ids']);
        }
        
        // Sync sub-calendari se forniti
        if (isset($data['sub_calendario_ids'])) {
            $evento->syncSubCalendari($data['sub_calendario_ids']);
        }
        
        return rest_ensure_response([
            'success' => true,
            'evento' => $evento->toApiResponse(),
        ]);
    }
    
    public function delete_evento(\WP_REST_Request $request) {
        $evento = Evento::find($request['id']);
        
        if (!$evento) {
            return new \WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }
        
        if (!$evento->isLocal()) {
            return new \WP_Error('readonly', 'Eventi esterni non eliminabili', ['status' => 400]);
        }
        
        // Verifica permessi: admin può tutto, altri solo i propri eventi
        $current_user_id = get_current_user_id();
        if (!current_user_can('sc_manage_settings') && $evento->autore_id != $current_user_id) {
            return new \WP_Error('forbidden', 'Puoi eliminare solo i tuoi eventi', ['status' => 403]);
        }
        
        if (!$evento->delete()) {
            return new \WP_Error('db_error', 'Errore eliminazione evento', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    private function validate_evento_data($data, $is_update = false) {
        $required = ['titolo', 'data_inizio', 'data_fine'];
        
        if (!$is_update) {
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return new \WP_Error('validation', "Campo '$field' obbligatorio", ['status' => 400]);
                }
            }
        }
        
        $allowed = [
            'titolo', 'descrizione', 'data_inizio', 'data_fine',
            'tutto_giorno', 'visibilita', 'mostra_su_schermo', 'plesso_id', 
            'responsabile', 'luogo_scuola', 'luogo_fisico', 'luogo_lat', 'luogo_lng',
            'classe_ids', 'sub_calendario_ids'
        ];
        
        $filtered = array_intersect_key($data, array_flip($allowed));
        
        // Validazione date
        if (!empty($filtered['data_inizio']) && !strtotime($filtered['data_inizio'])) {
            return new \WP_Error('validation', 'data_inizio non valida', ['status' => 400]);
        }
        
        if (!empty($filtered['data_fine']) && !strtotime($filtered['data_fine'])) {
            return new \WP_Error('validation', 'data_fine non valida', ['status' => 400]);
        }
        
        // Validazione visibilità
        if (!empty($filtered['visibilita']) && !in_array($filtered['visibilita'], ['pubblico', 'privato'])) {
            return new \WP_Error('validation', 'visibilita non valida', ['status' => 400]);
        }
        
        return $filtered;
    }
    
    // =========================================================================
    // SUB-CALENDARI
    // =========================================================================
    
    public function get_sub_calendari(\WP_REST_Request $request) {
        $plesso_id = $request->get_param('plesso_id');
        
        if ($plesso_id) {
            $sub_calendari = SubCalendario::perPlesso($plesso_id);
        } else {
            $sub_calendari = SubCalendario::attivi();
        }
        
        return array_map(fn($sc) => $sc->toApiResponse(), $sub_calendari);
    }
    
    public function get_sub_calendario(\WP_REST_Request $request) {
        $sub_calendario = SubCalendario::find($request['id']);
        
        if (!$sub_calendario) {
            return new \WP_Error('not_found', 'Sub-calendario non trovato', ['status' => 404]);
        }
        
        return $sub_calendario->toApiResponse();
    }
    
    public function create_sub_calendario(\WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        if (empty($data['nome'])) {
            return new \WP_Error('validation', 'Nome obbligatorio', ['status' => 400]);
        }
        
        if (empty($data['plesso_id'])) {
            return new \WP_Error('validation', 'plesso_id obbligatorio', ['status' => 400]);
        }
        
        $sub_calendario = new SubCalendario([
            'plesso_id' => (int) $data['plesso_id'],
            'nome' => sanitize_text_field($data['nome']),
            'colore' => sanitize_hex_color($data['colore'] ?? '#2d7ff9') ?: '#2d7ff9',
            'ordine' => (int) ($data['ordine'] ?? 0),
            'attivo' => 1,
        ]);
        
        if (!$sub_calendario->save()) {
            return new \WP_Error('db_error', 'Errore salvataggio sub-calendario', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'sub_calendario' => $sub_calendario->toApiResponse(),
        ]);
    }
    
    public function update_sub_calendario(\WP_REST_Request $request) {
        $sub_calendario = SubCalendario::find($request['id']);
        
        if (!$sub_calendario) {
            return new \WP_Error('not_found', 'Sub-calendario non trovato', ['status' => 404]);
        }
        
        $data = $request->get_json_params();
        
        $allowed = ['nome', 'colore', 'ordine', 'attivo'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if ($field === 'nome') {
                    $sub_calendario->$field = sanitize_text_field($data[$field]);
                } elseif ($field === 'colore') {
                    $sub_calendario->$field = sanitize_hex_color($data[$field]) ?: '#2d7ff9';
                } else {
                    $sub_calendario->$field = $data[$field];
                }
            }
        }
        
        if (!$sub_calendario->save()) {
            return new \WP_Error('db_error', 'Errore aggiornamento sub-calendario', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'sub_calendario' => $sub_calendario->toApiResponse(),
        ]);
    }
    
    public function delete_sub_calendario(\WP_REST_Request $request) {
        $sub_calendario = SubCalendario::find($request['id']);
        
        if (!$sub_calendario) {
            return new \WP_Error('not_found', 'Sub-calendario non trovato', ['status' => 404]);
        }
        
        // Rimuovi associazioni con eventi
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        $wpdb->delete("{$prefix}eventi_sub_calendari", ['sub_calendario_id' => $sub_calendario->id]);
        
        if (!$sub_calendario->delete()) {
            return new \WP_Error('db_error', 'Errore eliminazione sub-calendario', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    public function can_manage_sub_calendari() {
        return Auth::can('sc_manage_sub_calendari') || Auth::can('sc_manage_settings');
    }
    
    // =========================================================================
    // CALENDARI ESTERNI
    // =========================================================================
    
    public function get_calendari_esterni(\WP_REST_Request $request) {
        $calendari = CalendarioEsterno::attivi();
        return array_map(fn($c) => $c->toApiResponse(), $calendari);
    }
    
    public function get_calendario_esterno(\WP_REST_Request $request) {
        $calendario = CalendarioEsterno::find($request['id']);
        
        if (!$calendario) {
            return new \WP_Error('not_found', 'Calendario non trovato', ['status' => 404]);
        }
        
        return $calendario->toApiResponse();
    }
    
    public function create_calendario_esterno(\WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        $required = ['nome', 'tipo', 'calendar_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new \WP_Error('validation', "Campo '$field' obbligatorio", ['status' => 400]);
            }
        }
        
        $calendario = new CalendarioEsterno([
            'nome' => sanitize_text_field($data['nome']),
            'tipo' => $data['tipo'],
            'calendar_id' => sanitize_text_field($data['calendar_id']),
            'plesso_id' => $data['plesso_id'] ?? null,
            'visibilita_default' => $data['visibilita_default'] ?? 'pubblico',
            'colore' => $data['colore'] ?? null,
            'sync_interval' => $data['sync_interval'] ?? 15,
            'attivo' => 1,
        ]);
        
        if (!empty($data['credentials'])) {
            $calendario->setCredentials($data['credentials']);
        }
        
        if (!$calendario->save()) {
            return new \WP_Error('db_error', 'Errore salvataggio calendario', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'calendario' => $calendario->toApiResponse(),
        ]);
    }
    
    public function update_calendario_esterno(\WP_REST_Request $request) {
        $calendario = CalendarioEsterno::find($request['id']);
        
        if (!$calendario) {
            return new \WP_Error('not_found', 'Calendario non trovato', ['status' => 404]);
        }
        
        $data = $request->get_json_params();
        
        $allowed = ['nome', 'calendar_id', 'plesso_id', 'visibilita_default', 'colore', 'sync_interval', 'attivo'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $calendario->$field = $data[$field];
            }
        }
        
        if (!empty($data['credentials'])) {
            $calendario->setCredentials($data['credentials']);
        }
        
        if (!$calendario->save()) {
            return new \WP_Error('db_error', 'Errore aggiornamento calendario', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'calendario' => $calendario->toApiResponse(),
        ]);
    }
    
    public function delete_calendario_esterno(\WP_REST_Request $request) {
        $calendario = CalendarioEsterno::find($request['id']);
        
        if (!$calendario) {
            return new \WP_Error('not_found', 'Calendario non trovato', ['status' => 404]);
        }
        
        // Elimina anche gli eventi importati
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        $wpdb->delete("{$prefix}eventi", ['calendar_id' => $calendario->id]);
        
        if (!$calendario->delete()) {
            return new \WP_Error('db_error', 'Errore eliminazione calendario', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    public function sync_calendario_esterno(\WP_REST_Request $request) {
        $calendario = CalendarioEsterno::find($request['id']);
        
        if (!$calendario) {
            return new \WP_Error('not_found', 'Calendario non trovato', ['status' => 404]);
        }
        
        $syncManager = new SyncManager();
        $result = $syncManager->syncCalendar($calendario->id);
        
        if ($result['success'] ?? false) {
            return rest_ensure_response([
                'success' => true,
                'stats' => $result,
            ]);
        }
        
        return new \WP_Error(
            'sync_failed', 
            $result['error_message'] ?? 'Sincronizzazione fallita',
            ['status' => 500, 'details' => $result]
        );
    }
    
    // =========================================================================
    // API KEYS
    // =========================================================================
    
    public function get_api_keys(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        
        // Admin vede tutte, altri solo le proprie
        if (current_user_can('administrator')) {
            global $wpdb;
            $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
            
            $keys = $wpdb->get_results(
                "SELECT k.id, k.nome, LEFT(k.api_key, 8) as key_preview, 
                        k.last_used, k.expires_at, k.attivo, k.created_at,
                        u.display_name as user_name
                 FROM {$prefix}api_keys k
                 LEFT JOIN {$wpdb->users} u ON k.user_id = u.ID
                 ORDER BY k.created_at DESC"
            );
        } else {
            $keys = Auth::get_user_api_keys($user_id);
        }
        
        return $keys;
    }
    
    public function create_api_key(\WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        if (empty($data['nome'])) {
            return new \WP_Error('validation', 'Nome obbligatorio', ['status' => 400]);
        }
        
        $user_id = $data['user_id'] ?? get_current_user_id();
        
        // Solo admin può creare key per altri utenti
        if ($user_id != get_current_user_id() && !current_user_can('administrator')) {
            return new \WP_Error('forbidden', 'Non autorizzato', ['status' => 403]);
        }
        
        $api_key = Auth::generate_api_key(
            $user_id,
            sanitize_text_field($data['nome']),
            $data['permissions'] ?? null,
            $data['expires_at'] ?? null
        );
        
        if (!$api_key) {
            return new \WP_Error('db_error', 'Errore creazione API Key', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'api_key' => $api_key, // Mostrata solo alla creazione
            'message' => 'Salva questa API Key, non sarà più visibile',
        ]);
    }
    
    public function revoke_api_key(\WP_REST_Request $request) {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}api_keys WHERE id = %d",
            $request['id']
        ));
        
        if (!$key) {
            return new \WP_Error('not_found', 'API Key non trovata', ['status' => 404]);
        }
        
        // Solo admin o proprietario
        if ($key->user_id != get_current_user_id() && !current_user_can('administrator')) {
            return new \WP_Error('forbidden', 'Non autorizzato', ['status' => 403]);
        }
        
        Auth::revoke_api_key($request['id']);
        
        return rest_ensure_response(['success' => true]);
    }
    
    // =========================================================================
    // PERMISSION CALLBACKS
    // =========================================================================
    
    public function can_create_evento() {
        return Auth::can('sc_create_events');
    }
    
    public function can_edit_evento(\WP_REST_Request $request) {
        $evento = Evento::find($request['id']);
        return $evento && Auth::can_edit_event($evento);
    }
    
    public function can_delete_evento(\WP_REST_Request $request) {
        return Auth::can('sc_delete_events');
    }
    
    public function is_admin() {
        return Auth::can('sc_manage_settings');
    }
    
    public function can_manage_api_keys() {
        return Auth::can('sc_manage_api_keys');
    }
    
    // =========================================================================
    // BOOKING ENDPOINTS
    // =========================================================================
    
    /**
     * Autenticazione API Key per booking
     */
    public function authenticate_api_key_for_booking(\WP_REST_Request $request) {
        $api_key = $request->get_header('X-API-Key');
        
        if (empty($api_key)) {
            return new \WP_Error('missing_api_key', 'API Key richiesta', ['status' => 401]);
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}api_keys WHERE api_key = %s AND attivo = 1",
            $api_key
        ));
        
        if (!$key) {
            return new \WP_Error('invalid_api_key', 'API Key non valida', ['status' => 401]);
        }
        
        // Aggiorna last_used
        $wpdb->update(
            "{$prefix}api_keys",
            ['last_used' => current_time('mysql')],
            ['id' => $key->id]
        );
        
        return true;
    }
    
    /**
     * Crea evento da booking
     * POST /wp-json/school-calendar/v1/eventi/booking
     */
    public function create_booking_event(\WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        // Validazione
        if (empty($data['titolo'])) {
            return new \WP_Error('validation', 'Titolo obbligatorio', ['status' => 400]);
        }
        if (empty($data['data_inizio'])) {
            return new \WP_Error('validation', 'Data inizio obbligatoria', ['status' => 400]);
        }
        if (empty($data['booking_id'])) {
            return new \WP_Error('validation', 'booking_id obbligatorio', ['status' => 400]);
        }
        
        // Verifica che booking_id non esista già
        $existing = Evento::findBy('booking_id', $data['booking_id']);
        if ($existing) {
            return new \WP_Error('duplicate', 'Evento con questo booking_id già esistente', ['status' => 409]);
        }
        
        $evento = new Evento([
            'titolo' => sanitize_text_field($data['titolo']),
            'descrizione' => sanitize_textarea_field($data['descrizione'] ?? ''),
            'data_inizio' => $data['data_inizio'],
            'data_fine' => $data['data_fine'] ?? $data['data_inizio'],
            'tutto_giorno' => !empty($data['tutto_giorno']) ? 1 : 0,
            'visibilita' => $data['visibilita'] ?? 'pubblico',
            'mostra_su_schermo' => !empty($data['mostra_su_schermo']) ? 1 : 0,
            'plesso_id' => $data['plesso_id'] ?? null,
            'risorsa' => sanitize_text_field($data['risorsa'] ?? ''),
            'source' => 'booking',
            'booking_id' => (int) $data['booking_id'],
        ]);
        
        if (!$evento->save()) {
            return new \WP_Error('db_error', 'Errore salvataggio evento', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'evento_id' => $evento->id,
            'evento' => $evento->toApiResponse(),
        ]);
    }
    
    /**
     * Recupera evento da booking_id
     * GET /wp-json/school-calendar/v1/eventi/booking/{booking_id}
     */
    public function get_booking_event(\WP_REST_Request $request) {
        $evento = Evento::findBy('booking_id', $request['booking_id']);
        
        if (!$evento) {
            return new \WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }
        
        return rest_ensure_response($evento->toApiResponse());
    }
    
    /**
     * Aggiorna evento da booking_id
     * PUT /wp-json/school-calendar/v1/eventi/booking/{booking_id}
     */
    public function update_booking_event(\WP_REST_Request $request) {
        $evento = Evento::findBy('booking_id', $request['booking_id']);
        
        if (!$evento) {
            return new \WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }
        
        $data = $request->get_json_params();
        
        $allowed = ['titolo', 'descrizione', 'data_inizio', 'data_fine', 'tutto_giorno', 
                    'visibilita', 'mostra_su_schermo', 'plesso_id', 'risorsa'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if ($field === 'titolo') {
                    $evento->$field = sanitize_text_field($data[$field]);
                } elseif ($field === 'descrizione') {
                    $evento->$field = sanitize_textarea_field($data[$field]);
                } elseif ($field === 'risorsa') {
                    $evento->$field = sanitize_text_field($data[$field]);
                } elseif (in_array($field, ['tutto_giorno', 'mostra_su_schermo'])) {
                    $evento->$field = !empty($data[$field]) ? 1 : 0;
                } else {
                    $evento->$field = $data[$field];
                }
            }
        }
        
        if (!$evento->save()) {
            return new \WP_Error('db_error', 'Errore aggiornamento evento', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'evento' => $evento->toApiResponse(),
        ]);
    }
    
    /**
     * Elimina evento da booking_id
     * DELETE /wp-json/school-calendar/v1/eventi/booking/{booking_id}
     */
    public function delete_booking_event(\WP_REST_Request $request) {
        $evento = Evento::findBy('booking_id', $request['booking_id']);
        
        if (!$evento) {
            return new \WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }
        
        if (!$evento->delete()) {
            return new \WP_Error('db_error', 'Errore eliminazione evento', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    // =========================================================================
    // SCHERMO ENDPOINT
    // =========================================================================
    
    /**
     * Eventi da mostrare su schermo/display
     * GET /wp-json/school-calendar/v1/schermo
     * 
     * Parametri:
     * - plesso_id: filtra per plesso (opzionale)
     * - data: data specifica, default oggi (opzionale)
     * - limit: numero max eventi, default 10 (opzionale)
     */
    public function get_eventi_schermo(\WP_REST_Request $request) {
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $plesso_id = $request->get_param('plesso_id');
        $data = $request->get_param('data') ?: date('Y-m-d');
        $limit = (int) ($request->get_param('limit') ?: 10);
        
        $sql = "SELECT * FROM {$prefix}eventi 
                WHERE mostra_su_schermo = 1 
                AND visibilita = 'pubblico'
                AND DATE(data_inizio) <= %s 
                AND DATE(data_fine) >= %s";
        
        $params = [$data, $data];
        
        if ($plesso_id) {
            $sql .= " AND (plesso_id = %d OR plesso_id IS NULL)";
            $params[] = $plesso_id;
        }
        
        $sql .= " ORDER BY data_inizio ASC LIMIT %d";
        $params[] = $limit;
        
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        $eventi = [];
        foreach ($rows as $row) {
            $evento = new Evento($row);
            $plesso = $evento->plesso();
            
            $eventi[] = [
                'id' => (int) $evento->id,
                'titolo' => $evento->titolo,
                'descrizione' => $evento->descrizione,
                'data_inizio' => $this->format_datetime_iso($evento->data_inizio),
                'data_fine' => $this->format_datetime_iso($evento->data_fine),
                'tutto_il_giorno' => (bool) $evento->tutto_giorno,
                'plesso' => $plesso ? ($plesso->descrizione_pubblica ?: $plesso->descrizione) : null,
                'risorsa' => $evento->risorsa,
                'source' => $evento->source,
            ];
        }
        
        return rest_ensure_response([
            'data' => $data,
            'count' => count($eventi),
            'eventi' => $eventi,
        ]);
    }
}
