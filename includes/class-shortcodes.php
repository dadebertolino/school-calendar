<?php
namespace SchoolCalendar;

use SchoolCalendar\Models\Plesso;
use SchoolCalendar\Models\Classe;
use SchoolCalendar\Models\SubCalendario;
use SchoolCalendar\Models\Evento;

defined('ABSPATH') || exit;

class Shortcodes {
    
    private static int $instance_counter = 0;
    
    public function __construct() {
        add_shortcode('school_calendar', [$this, 'render_calendar']);
        add_shortcode('school_calendar_list', [$this, 'render_list']);
        add_shortcode('school_calendar_widget', [$this, 'render_widget']);
        add_shortcode('school_calendar_form', [$this, 'render_event_form']);
        add_shortcode('school_calendar_my_events', [$this, 'render_my_events']);
        
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }
    
    /**
     * Registra assets (caricati solo quando necessario)
     */
    public function register_assets(): void {
        // FullCalendar CSS
        wp_register_style(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css',
            [],
            '6.1.15'
        );
        
        // Plugin CSS
        wp_register_style(
            'school-calendar',
            SC_PLUGIN_URL . 'public/css/calendar.css',
            ['fullcalendar'],
            SC_VERSION
        );
        
        // FullCalendar JS (bundle completo)
        wp_register_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
            [],
            '6.1.15',
            true
        );
        
        // Plugin JS
        wp_register_script(
            'school-calendar',
            SC_PLUGIN_URL . 'public/js/calendar.js',
            ['fullcalendar'],
            SC_VERSION,
            true
        );
    }
    
    /**
     * Localizza script (chiamato quando lo script viene effettivamente usato)
     */
    private function localize_script(): void {
        wp_localize_script('school-calendar', 'schoolCalendarConfig', [
            'apiUrl' => esc_url_raw(rest_url('school-calendar/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale' => substr(get_locale(), 0, 2),
            'isLoggedIn' => is_user_logged_in(),
            'canCreate' => current_user_can('sc_create_events'),
            'canEdit' => current_user_can('sc_edit_own_events'),
            'isAdmin' => current_user_can('sc_manage_settings'),
            'currentUserId' => get_current_user_id(),
        ]);
    }
    
    /**
     * Shortcode principale: [school_calendar]
     * 
     * Attributi:
     * - view: dayGridMonth, timeGridWeek, timeGridDay, listWeek, listMonth (default: dayGridMonth)
     * - plesso: ID plesso o "all" (default: all)
     * - classe: ID classe (default: none)
     * - show_filters: true/false (default: true)
     * - show_legend: true/false (default: true)
     * - height: auto, 600px, etc (default: auto)
     * - editable: true/false - permette drag&drop se utente ha permessi (default: false)
     * - slot_min_time: ora inizio visualizzazione giornaliera/settimanale (default: 07:00)
     * - slot_max_time: ora fine visualizzazione giornaliera/settimanale (default: 20:00)
     * - require_login: true/false - richiede login per visualizzare (default: false)
     * - guest_message: messaggio da mostrare ai non loggati (default: vuoto)
     * - guest_view: vista per non loggati (default: stessa di view)
     * - subscriber_view: vista per sottoscrittori loggati (default: stessa di view)
     * - guest_show_filters: true/false - mostra filtri ai non loggati (default: come show_filters)
     * - subscriber_show_filters: true/false - mostra filtri ai sottoscrittori (default: come show_filters)
     * - hide_weekends: true/false - nasconde sabato e domenica (default: false)
     */
    public function render_calendar(array $atts = []): string {
        $atts = shortcode_atts([
            'view' => 'dayGridMonth',
            'plesso' => 'all',
            'classe' => '',
            'show_filters' => 'true',
            'show_legend' => 'true',
            'height' => 'auto',
            'editable' => 'false',
            'slot_min_time' => '07:00',
            'slot_max_time' => '20:00',
            'hide_weekends' => 'false',
            'require_login' => 'false',
            'guest_message' => '',
            'guest_view' => '',
            'subscriber_view' => '',
            'guest_show_filters' => '',
            'subscriber_show_filters' => '',
        ], $atts, 'school_calendar');
        
        // Verifica accesso
        $is_logged_in = is_user_logged_in();
        $is_subscriber = $is_logged_in && current_user_can('read') && !current_user_can('edit_posts');
        
        // Se richiede login e utente non loggato, mostra messaggio
        if ($atts['require_login'] === 'true' && !$is_logged_in) {
            $message = $atts['guest_message'] ?: __('Effettua il login per visualizzare il calendario.', 'school-calendar');
            return '<div class="sc-login-required"><p>' . esc_html($message) . '</p><a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button">' . __('Accedi', 'school-calendar') . '</a></div>';
        }
        
        // Determina vista e filtri in base al ruolo
        if (!$is_logged_in) {
            // Visitatore non loggato
            $effective_view = $atts['guest_view'] ?: $atts['view'];
            $effective_show_filters = $atts['guest_show_filters'] !== '' ? $atts['guest_show_filters'] : $atts['show_filters'];
        } elseif ($is_subscriber) {
            // Sottoscrittore loggato
            $effective_view = $atts['subscriber_view'] ?: $atts['view'];
            $effective_show_filters = $atts['subscriber_show_filters'] !== '' ? $atts['subscriber_show_filters'] : $atts['show_filters'];
        } else {
            // Altri utenti loggati (editor, admin, ecc.)
            $effective_view = $atts['view'];
            $effective_show_filters = $atts['show_filters'];
        }
        
        // Enqueue assets
        wp_enqueue_style('school-calendar');
        wp_enqueue_script('school-calendar');
        $this->localize_script();
        
        self::$instance_counter++;
        $instance_id = 'sc-calendar-' . self::$instance_counter;
        
        // Prepara dati per JS
        $config = [
            'instanceId' => $instance_id,
            'initialView' => $effective_view,
            'plesso' => $atts['plesso'] === 'all' ? null : (int) $atts['plesso'],
            'classe' => $atts['classe'] ? (int) $atts['classe'] : null,
            'height' => $atts['height'],
            'editable' => $atts['editable'] === 'true' && current_user_can('sc_edit_own_events'),
            'showFilters' => $effective_show_filters === 'true',
            'slotMinTime' => $atts['slot_min_time'] . ':00',
            'slotMaxTime' => $atts['slot_max_time'] . ':00',
            'hideWeekends' => $atts['hide_weekends'] === 'true',
        ];
        
        ob_start();
        ?>
        <div class="sc-calendar-wrapper" id="<?php echo esc_attr($instance_id); ?>-wrapper">
            
            <?php if ($effective_show_filters === 'true'): ?>
                <?php echo $this->render_filters($instance_id, $atts); ?>
            <?php endif; ?>
            
            <div class="sc-calendar-container">
                <div id="<?php echo esc_attr($instance_id); ?>" class="sc-calendar"></div>
            </div>
            
            <?php if ($atts['show_legend'] === 'true'): ?>
                <?php 
                $legend_plesso = ($atts['plesso'] && $atts['plesso'] !== 'all') ? $atts['plesso'] : null;
                echo $this->render_legend($instance_id, $legend_plesso); 
                ?>
            <?php endif; ?>
            
            <!-- Modal dettaglio evento -->
            <div id="<?php echo esc_attr($instance_id); ?>-modal" class="sc-modal" style="display:none;">
                <div class="sc-modal-overlay"></div>
                <div class="sc-modal-content">
                    <button class="sc-modal-close">&times;</button>
                    <div class="sc-modal-body"></div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SchoolCalendar !== 'undefined') {
                SchoolCalendar.init(<?php echo json_encode($config); ?>);
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render filtri
     */
    private function render_filters(string $instance_id, array $atts): string {
        $plessi = Plesso::attivi();
        $selected_plesso = $atts['plesso'] === 'all' ? '' : $atts['plesso'];
        
        // Carica sub-calendari
        $sub_calendari = \SchoolCalendar\Models\SubCalendario::attivi();
        
        ob_start();
        ?>
        <div class="sc-filters" data-instance="<?php echo esc_attr($instance_id); ?>">
            <div class="sc-filter-group">
                <label for="<?php echo esc_attr($instance_id); ?>-plesso">Plesso:</label>
                <select id="<?php echo esc_attr($instance_id); ?>-plesso" class="sc-filter-plesso">
                    <option value="">Tutti i plessi</option>
                    <?php foreach ($plessi as $plesso): ?>
                        <option value="<?php echo $plesso->id; ?>" <?php selected($selected_plesso, $plesso->id); ?>>
                            <?php echo esc_html($plesso->descrizione); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (!empty($sub_calendari)): ?>
            <div class="sc-filter-group">
                <label for="<?php echo esc_attr($instance_id); ?>-subcal">Categoria:</label>
                <select id="<?php echo esc_attr($instance_id); ?>-subcal" class="sc-filter-subcal">
                    <option value="">Tutte</option>
                    <?php foreach ($sub_calendari as $sc): ?>
                        <option value="<?php echo $sc->id; ?>" data-plesso="<?php echo $sc->plesso_id; ?>" data-color="<?php echo esc_attr($sc->colore); ?>">
                            <?php echo esc_html($sc->nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="sc-filter-group">
                <label for="<?php echo esc_attr($instance_id); ?>-classe">Classe:</label>
                <select id="<?php echo esc_attr($instance_id); ?>-classe" class="sc-filter-classe" disabled>
                    <option value="">Tutte le classi</option>
                </select>
            </div>
            
            <div class="sc-filter-group sc-filter-views">
                <button type="button" class="sc-view-btn" data-view="dayGridMonth" title="Mese">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </button>
                <button type="button" class="sc-view-btn" data-view="timeGridWeek" title="Settimana">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="9" y1="4" x2="9" y2="22"></line><line x1="15" y1="4" x2="15" y2="22"></line></svg>
                </button>
                <button type="button" class="sc-view-btn" data-view="timeGridDay" title="Giorno">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="12" y1="4" x2="12" y2="22"></line></svg>
                </button>
                <button type="button" class="sc-view-btn" data-view="listMonth" title="Lista">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render legenda
     */
    private function render_legend(string $instance_id = '', $plesso_id = null): string {
        ob_start();
        
        // Carica sub-calendari (sempre tutti se non specificato plesso)
        if ($plesso_id) {
            $sub_calendari = \SchoolCalendar\Models\SubCalendario::perPlesso($plesso_id);
        } else {
            $sub_calendari = \SchoolCalendar\Models\SubCalendario::attivi();
        }
        ?>
        <div class="sc-legend" data-instance="<?php echo esc_attr($instance_id); ?>">
            <span class="sc-legend-item">
                <span class="sc-legend-dot sc-legend-pubblico"></span> Pubblico
            </span>
            <span class="sc-legend-item">
                <span class="sc-legend-dot sc-legend-privato"></span> Riservato
            </span>
            <span class="sc-legend-item">
                <span class="sc-legend-dot sc-legend-google"></span> Google
            </span>
            <?php foreach ($sub_calendari as $sc): ?>
            <label class="sc-legend-item sc-legend-subcal" data-subcal-id="<?php echo $sc->id; ?>">
                <span class="sc-legend-dot" style="background-color: <?php echo esc_attr($sc->colore); ?>;"></span>
                <?php echo esc_html($sc->nome); ?>
            </label>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode lista eventi: [school_calendar_list]
     * 
     * Attributi:
     * - limit: numero eventi (default: 10)
     * - plesso: ID plesso (default: all)
     * - classe: ID classe (default: none)
     * - days: giorni futuri da mostrare (default: 30)
     * - show_date: true/false (default: true)
     * - show_time: true/false (default: true)
     * - show_plesso: true/false (default: true)
     */
    public function render_list(array $atts = []): string {
        $atts = shortcode_atts([
            'limit' => 10,
            'plesso' => '',
            'classe' => '',
            'days' => 30,
            'show_date' => 'true',
            'show_time' => 'true',
            'show_plesso' => 'true',
        ], $atts, 'school_calendar_list');
        
        wp_enqueue_style('school-calendar');
        
        // Query eventi
        $params = [
            'start' => date('Y-m-d'),
            'end' => date('Y-m-d', strtotime("+{$atts['days']} days")),
            'limit' => (int) $atts['limit'],
        ];
        
        if ($atts['plesso']) {
            $params['plesso_id'] = (int) $atts['plesso'];
        }
        
        if ($atts['classe']) {
            $params['classe_id'] = (int) $atts['classe'];
        }
        
        $can_view_private = is_user_logged_in() && current_user_can('sc_view_private_events');
        $eventi = \SchoolCalendar\Models\Evento::filter($params, $can_view_private);
        
        if (empty($eventi)) {
            return '<div class="sc-list-empty">Nessun evento in programma</div>';
        }
        
        // Raggruppa per data
        $grouped = [];
        foreach ($eventi as $evento) {
            $date = substr($evento->data_inizio, 0, 10);
            $grouped[$date][] = $evento;
        }
        
        ob_start();
        ?>
        <div class="sc-event-list">
            <?php foreach ($grouped as $date => $day_events): ?>
                <?php if ($atts['show_date'] === 'true'): ?>
                    <div class="sc-list-date">
                        <?php echo date_i18n('l j F', strtotime($date)); ?>
                    </div>
                <?php endif; ?>
                
                <?php foreach ($day_events as $evento): ?>
                    <div class="sc-list-event sc-event-<?php echo $evento->visibilita; ?> sc-event-source-<?php echo $evento->source; ?>">
                        <?php if ($atts['show_time'] === 'true' && !$evento->tutto_giorno): ?>
                            <span class="sc-list-time">
                                <?php echo date('H:i', strtotime($evento->data_inizio)); ?>
                            </span>
                        <?php endif; ?>
                        
                        <span class="sc-list-title"><?php echo esc_html($evento->titolo); ?></span>
                        
                        <?php if ($atts['show_plesso'] === 'true' && $evento->plesso_id): ?>
                            <?php $plesso = $evento->plesso(); ?>
                            <?php if ($plesso): ?>
                                <span class="sc-list-plesso"><?php echo esc_html($plesso->nome); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($evento->visibilita === 'privato'): ?>
                            <span class="sc-list-badge sc-badge-privato">Riservato</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode widget compatto: [school_calendar_widget]
     * 
     * Attributi:
     * - title: titolo widget (default: "Prossimi Eventi")
     * - limit: numero eventi (default: 5)
     * - plesso: ID plesso (default: all)
     */
    public function render_widget(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Prossimi Eventi',
            'limit' => 5,
            'plesso' => '',
        ], $atts, 'school_calendar_widget');
        
        wp_enqueue_style('school-calendar');
        
        $params = [
            'start' => date('Y-m-d H:i:s'),
            'limit' => (int) $atts['limit'],
        ];
        
        if ($atts['plesso']) {
            $params['plesso_id'] = (int) $atts['plesso'];
        }
        
        $can_view_private = is_user_logged_in() && current_user_can('sc_view_private_events');
        $eventi = \SchoolCalendar\Models\Evento::filter($params, $can_view_private);
        
        ob_start();
        ?>
        <div class="sc-widget">
            <?php if ($atts['title']): ?>
                <h3 class="sc-widget-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            
            <?php if (empty($eventi)): ?>
                <p class="sc-widget-empty">Nessun evento in programma</p>
            <?php else: ?>
                <ul class="sc-widget-list">
                    <?php foreach ($eventi as $evento): ?>
                        <li class="sc-widget-event">
                            <span class="sc-widget-date">
                                <?php 
                                $date = strtotime($evento->data_inizio);
                                echo date_i18n('d M', $date);
                                if (!$evento->tutto_giorno) {
                                    echo ' · ' . date('H:i', $date);
                                }
                                ?>
                            </span>
                            <span class="sc-widget-title"><?php echo esc_html($evento->titolo); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode form caricamento eventi: [school_calendar_form]
     * 
     * Attributi:
     * - plesso: ID plesso preselezionato (default: none)
     * - redirect: URL redirect dopo salvataggio (default: current page)
     */
    public function render_event_form(array $atts = []): string {
        $atts = shortcode_atts([
            'plesso' => '',
            'redirect' => '',
        ], $atts, 'school_calendar_form');
        
        // Verifica login
        if (!is_user_logged_in()) {
            return '<div class="sc-form-message sc-form-error">Devi effettuare il login per caricare eventi.</div>';
        }
        
        // Verifica permessi
        if (!current_user_can('sc_create_events')) {
            return '<div class="sc-form-message sc-form-error">Non hai i permessi per caricare eventi.</div>';
        }
        
        wp_enqueue_style('school-calendar');
        wp_enqueue_script('school-calendar');
        $this->localize_script();
        
        $plessi = Plesso::attivi();
        $sub_calendari = SubCalendario::attivi();
        $classi = Classe::all();
        
        // Raggruppa classi per plesso
        $classi_per_plesso = [];
        foreach ($classi as $classe) {
            $classi_per_plesso[$classe->plesso][] = $classe;
        }
        
        $instance_id = 'sc-form-' . (++self::$instance_counter);
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>" class="sc-event-form-wrapper">
            <form id="<?php echo esc_attr($instance_id); ?>-form" class="sc-event-form">
                <input type="hidden" id="<?php echo esc_attr($instance_id); ?>-redirect" value="<?php echo esc_url($atts['redirect'] ?: get_permalink()); ?>">
                
                <div class="sc-form-row">
                    <label for="<?php echo esc_attr($instance_id); ?>-titolo">Titolo evento *</label>
                    <input type="text" id="<?php echo esc_attr($instance_id); ?>-titolo" required>
                </div>
                
                <div class="sc-form-row">
                    <label for="<?php echo esc_attr($instance_id); ?>-descrizione">Descrizione</label>
                    <textarea id="<?php echo esc_attr($instance_id); ?>-descrizione" rows="3"></textarea>
                </div>
                
                <div class="sc-form-row sc-form-row-inline">
                    <div>
                        <label for="<?php echo esc_attr($instance_id); ?>-data-inizio">Data inizio *</label>
                        <input type="date" id="<?php echo esc_attr($instance_id); ?>-data-inizio" required>
                    </div>
                    <div>
                        <label for="<?php echo esc_attr($instance_id); ?>-ora-inizio">Ora inizio</label>
                        <input type="time" id="<?php echo esc_attr($instance_id); ?>-ora-inizio" value="08:00">
                    </div>
                </div>
                
                <div class="sc-form-row sc-form-row-inline">
                    <div>
                        <label for="<?php echo esc_attr($instance_id); ?>-data-fine">Data fine *</label>
                        <input type="date" id="<?php echo esc_attr($instance_id); ?>-data-fine" required>
                    </div>
                    <div>
                        <label for="<?php echo esc_attr($instance_id); ?>-ora-fine">Ora fine</label>
                        <input type="time" id="<?php echo esc_attr($instance_id); ?>-ora-fine" value="09:00">
                    </div>
                </div>
                
                <div class="sc-form-row">
                    <label>
                        <input type="checkbox" id="<?php echo esc_attr($instance_id); ?>-tutto-giorno">
                        Tutto il giorno
                    </label>
                </div>
                
                <div class="sc-form-row">
                    <label for="<?php echo esc_attr($instance_id); ?>-plesso">Plesso</label>
                    <select id="<?php echo esc_attr($instance_id); ?>-plesso">
                        <option value="">Tutti i plessi</option>
                        <?php foreach ($plessi as $plesso): ?>
                            <option value="<?php echo $plesso->id; ?>" <?php selected($atts['plesso'], $plesso->id); ?>>
                                <?php echo esc_html($plesso->descrizione); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (!empty($sub_calendari)): ?>
                <div class="sc-form-row">
                    <label for="<?php echo esc_attr($instance_id); ?>-subcal">Categoria</label>
                    <select id="<?php echo esc_attr($instance_id); ?>-subcal" multiple style="height: 100px;">
                        <?php foreach ($sub_calendari as $sc): ?>
                            <option value="<?php echo $sc->id; ?>" data-plesso="<?php echo $sc->plesso_id; ?>">
                                <?php echo esc_html($sc->nome); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Tieni premuto Ctrl per selezionare più categorie</small>
                </div>
                <?php endif; ?>
                
                <div class="sc-form-row">
                    <label for="<?php echo esc_attr($instance_id); ?>-responsabile">Responsabile</label>
                    <input type="text" id="<?php echo esc_attr($instance_id); ?>-responsabile" value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>">
                </div>
                
                <div class="sc-form-row">
                    <label for="<?php echo esc_attr($instance_id); ?>-luogo">Luogo (interno scuola)</label>
                    <input type="text" id="<?php echo esc_attr($instance_id); ?>-luogo" placeholder="es. Aula Magna, Lab Info 3...">
                </div>
                
                <div class="sc-form-row">
                    <label for="<?php echo esc_attr($instance_id); ?>-visibilita">Visibilità</label>
                    <select id="<?php echo esc_attr($instance_id); ?>-visibilita">
                        <option value="pubblico">Pubblico</option>
                        <option value="privato">Riservato (solo utenti registrati)</option>
                    </select>
                </div>
                
                <div class="sc-form-row">
                    <button type="submit" class="sc-form-submit">Salva Evento</button>
                </div>
                
                <div id="<?php echo esc_attr($instance_id); ?>-message" class="sc-form-message" style="display:none;"></div>
            </form>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('<?php echo esc_js($instance_id); ?>-form');
            var messageEl = document.getElementById('<?php echo esc_js($instance_id); ?>-message');
            var redirectUrl = document.getElementById('<?php echo esc_js($instance_id); ?>-redirect').value;
            
            // Sync date fine con date inizio
            var dataInizio = document.getElementById('<?php echo esc_js($instance_id); ?>-data-inizio');
            var dataFine = document.getElementById('<?php echo esc_js($instance_id); ?>-data-fine');
            dataInizio.addEventListener('change', function() {
                if (!dataFine.value || dataFine.value < this.value) {
                    dataFine.value = this.value;
                }
            });
            
            // Toggle tutto il giorno
            var tuttoGiorno = document.getElementById('<?php echo esc_js($instance_id); ?>-tutto-giorno');
            var oraInizio = document.getElementById('<?php echo esc_js($instance_id); ?>-ora-inizio');
            var oraFine = document.getElementById('<?php echo esc_js($instance_id); ?>-ora-fine');
            tuttoGiorno.addEventListener('change', function() {
                oraInizio.disabled = this.checked;
                oraFine.disabled = this.checked;
            });
            
            // Submit
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var dataInizioVal = dataInizio.value;
                var dataFineVal = dataFine.value;
                
                if (!tuttoGiorno.checked) {
                    dataInizioVal += ' ' + oraInizio.value + ':00';
                    dataFineVal += ' ' + oraFine.value + ':00';
                } else {
                    dataInizioVal += ' 00:00:00';
                    dataFineVal += ' 23:59:59';
                }
                
                var subcalSelect = document.getElementById('<?php echo esc_js($instance_id); ?>-subcal');
                var subcalIds = subcalSelect ? Array.from(subcalSelect.selectedOptions).map(function(o) { return parseInt(o.value); }) : [];
                
                var data = {
                    titolo: document.getElementById('<?php echo esc_js($instance_id); ?>-titolo').value,
                    descrizione: document.getElementById('<?php echo esc_js($instance_id); ?>-descrizione').value,
                    data_inizio: dataInizioVal,
                    data_fine: dataFineVal,
                    tutto_giorno: tuttoGiorno.checked ? 1 : 0,
                    plesso_id: document.getElementById('<?php echo esc_js($instance_id); ?>-plesso').value || null,
                    sub_calendario_ids: subcalIds,
                    responsabile: document.getElementById('<?php echo esc_js($instance_id); ?>-responsabile').value,
                    luogo_scuola: document.getElementById('<?php echo esc_js($instance_id); ?>-luogo').value,
                    visibilita: document.getElementById('<?php echo esc_js($instance_id); ?>-visibilita').value
                };
                
                messageEl.style.display = 'none';
                
                fetch(schoolCalendarConfig.apiUrl + '/eventi', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': schoolCalendarConfig.nonce
                    },
                    body: JSON.stringify(data)
                })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        messageEl.className = 'sc-form-message sc-form-success';
                        messageEl.textContent = 'Evento creato con successo!';
                        messageEl.style.display = 'block';
                        form.reset();
                        if (redirectUrl) {
                            setTimeout(function() {
                                window.location.href = redirectUrl;
                            }, 1500);
                        }
                    } else {
                        throw new Error(result.message || 'Errore sconosciuto');
                    }
                })
                .catch(function(error) {
                    messageEl.className = 'sc-form-message sc-form-error';
                    messageEl.textContent = 'Errore: ' + error.message;
                    messageEl.style.display = 'block';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode I miei eventi: [school_calendar_my_events]
     * 
     * Mostra lista eventi dell'utente corrente con possibilità di modifica/elimina
     */
    public function render_my_events(array $atts = []): string {
        $atts = shortcode_atts([
            'limit' => 50,
            'show_past' => 'false',
        ], $atts, 'school_calendar_my_events');
        
        // Verifica login
        if (!is_user_logged_in()) {
            return '<div class="sc-form-message sc-form-error">Devi effettuare il login per vedere i tuoi eventi.</div>';
        }
        
        // Verifica permessi
        if (!current_user_can('sc_create_events')) {
            return '<div class="sc-form-message sc-form-error">Non hai i permessi per gestire eventi.</div>';
        }
        
        wp_enqueue_style('school-calendar');
        wp_enqueue_script('school-calendar');
        $this->localize_script();
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('sc_manage_settings');
        
        // Carica dati per il form di modifica
        $plessi = Plesso::attivi();
        $sub_calendari = SubCalendario::attivi();
        
        $instance_id = 'sc-my-events-' . (++self::$instance_counter);
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>" class="sc-my-events-wrapper">
            <div class="sc-my-events-header">
                <h3>I Miei Eventi</h3>
                <?php if ($is_admin): ?>
                    <span class="sc-admin-badge">Modalità Admin - Vedi tutti gli eventi</span>
                <?php endif; ?>
            </div>
            
            <div class="sc-my-events-filters">
                <label>
                    <input type="checkbox" id="<?php echo esc_attr($instance_id); ?>-show-past" <?php checked($atts['show_past'], 'true'); ?>>
                    Mostra eventi passati
                </label>
            </div>
            
            <div id="<?php echo esc_attr($instance_id); ?>-list" class="sc-my-events-list">
                <p class="sc-loading">Caricamento eventi...</p>
            </div>
            
            <!-- Modal modifica evento -->
            <div id="<?php echo esc_attr($instance_id); ?>-edit-modal" class="sc-edit-modal" style="display:none;">
                <div class="sc-edit-modal-overlay"></div>
                <div class="sc-edit-modal-content">
                    <button class="sc-edit-modal-close">&times;</button>
                    <h3>Modifica Evento</h3>
                    <form id="<?php echo esc_attr($instance_id); ?>-edit-form" class="sc-event-form">
                        <input type="hidden" id="<?php echo esc_attr($instance_id); ?>-edit-id">
                        
                        <div class="sc-form-row">
                            <label>Titolo *</label>
                            <input type="text" id="<?php echo esc_attr($instance_id); ?>-edit-titolo" required>
                        </div>
                        
                        <div class="sc-form-row">
                            <label>Descrizione</label>
                            <textarea id="<?php echo esc_attr($instance_id); ?>-edit-descrizione" rows="3"></textarea>
                        </div>
                        
                        <div class="sc-form-row sc-form-row-inline">
                            <div>
                                <label>Data inizio *</label>
                                <input type="date" id="<?php echo esc_attr($instance_id); ?>-edit-data-inizio" required>
                            </div>
                            <div>
                                <label>Ora inizio</label>
                                <input type="time" id="<?php echo esc_attr($instance_id); ?>-edit-ora-inizio">
                            </div>
                        </div>
                        
                        <div class="sc-form-row sc-form-row-inline">
                            <div>
                                <label>Data fine *</label>
                                <input type="date" id="<?php echo esc_attr($instance_id); ?>-edit-data-fine" required>
                            </div>
                            <div>
                                <label>Ora fine</label>
                                <input type="time" id="<?php echo esc_attr($instance_id); ?>-edit-ora-fine">
                            </div>
                        </div>
                        
                        <div class="sc-form-row">
                            <label>
                                <input type="checkbox" id="<?php echo esc_attr($instance_id); ?>-edit-tutto-giorno">
                                Tutto il giorno
                            </label>
                        </div>
                        
                        <div class="sc-form-row">
                            <label>Plesso</label>
                            <select id="<?php echo esc_attr($instance_id); ?>-edit-plesso">
                                <option value="">Tutti i plessi</option>
                                <?php foreach ($plessi as $plesso): ?>
                                    <option value="<?php echo $plesso->id; ?>"><?php echo esc_html($plesso->descrizione); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (!empty($sub_calendari)): ?>
                        <div class="sc-form-row">
                            <label>Categoria</label>
                            <select id="<?php echo esc_attr($instance_id); ?>-edit-subcal" multiple style="height: 80px;">
                                <?php foreach ($sub_calendari as $sc): ?>
                                    <option value="<?php echo $sc->id; ?>" data-plesso="<?php echo $sc->plesso_id; ?>"><?php echo esc_html($sc->nome); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="sc-form-row">
                            <label>Responsabile</label>
                            <input type="text" id="<?php echo esc_attr($instance_id); ?>-edit-responsabile">
                        </div>
                        
                        <div class="sc-form-row">
                            <label>Luogo (interno)</label>
                            <input type="text" id="<?php echo esc_attr($instance_id); ?>-edit-luogo">
                        </div>
                        
                        <div class="sc-form-row">
                            <label>Visibilità</label>
                            <select id="<?php echo esc_attr($instance_id); ?>-edit-visibilita">
                                <option value="pubblico">Pubblico</option>
                                <option value="privato">Riservato</option>
                            </select>
                        </div>
                        
                        <div class="sc-form-row sc-form-buttons">
                            <button type="submit" class="sc-form-submit">Salva Modifiche</button>
                            <button type="button" class="sc-form-cancel">Annulla</button>
                        </div>
                        
                        <div id="<?php echo esc_attr($instance_id); ?>-edit-message" class="sc-form-message" style="display:none;"></div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var instanceId = '<?php echo esc_js($instance_id); ?>';
            var listEl = document.getElementById(instanceId + '-list');
            var showPastCheckbox = document.getElementById(instanceId + '-show-past');
            var modal = document.getElementById(instanceId + '-edit-modal');
            var isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
            var currentUserId = <?php echo $current_user_id; ?>;
            
            // Carica eventi
            function loadEvents() {
                listEl.innerHTML = '<p class="sc-loading">Caricamento eventi...</p>';
                
                var showPast = showPastCheckbox.checked;
                var url = schoolCalendarConfig.apiUrl + '/eventi/miei';
                if (showPast) {
                    url += '?include_past=1';
                }
                
                fetch(url, {
                    headers: { 'X-WP-Nonce': schoolCalendarConfig.nonce }
                })
                .then(function(r) { return r.json(); })
                .then(function(eventi) {
                    if (!eventi || eventi.length === 0) {
                        listEl.innerHTML = '<p class="sc-no-events">Nessun evento trovato.</p>';
                        return;
                    }
                    
                    var html = '<table class="sc-events-table"><thead><tr>';
                    html += '<th>Data</th><th>Titolo</th><th>Luogo</th><th>Azioni</th>';
                    html += '</tr></thead><tbody>';
                    
                    eventi.forEach(function(e) {
                        var dataInizio = new Date(e.data_inizio);
                        var dateStr = dataInizio.toLocaleDateString('it-IT', {day: '2-digit', month: 'short', year: 'numeric'});
                        if (!e.tutto_giorno) {
                            dateStr += ' ' + dataInizio.toLocaleTimeString('it-IT', {hour: '2-digit', minute: '2-digit'});
                        }
                        
                        var isPast = dataInizio < new Date();
                        var rowClass = isPast ? 'sc-event-past' : '';
                        
                        html += '<tr class="' + rowClass + '" data-id="' + e.id + '">';
                        html += '<td>' + dateStr + '</td>';
                        html += '<td><strong>' + escapeHtml(e.titolo) + '</strong></td>';
                        html += '<td>' + escapeHtml(e.luogo_scuola || e.luogo_fisico || '-') + '</td>';
                        html += '<td class="sc-actions">';
                        html += '<button class="sc-btn-edit" data-id="' + e.id + '">✏️ Modifica</button>';
                        html += '<button class="sc-btn-delete" data-id="' + e.id + '">🗑️ Elimina</button>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    listEl.innerHTML = html;
                    
                    // Bind edit/delete buttons
                    listEl.querySelectorAll('.sc-btn-edit').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            editEvent(this.dataset.id);
                        });
                    });
                    
                    listEl.querySelectorAll('.sc-btn-delete').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            deleteEvent(this.dataset.id);
                        });
                    });
                })
                .catch(function(err) {
                    listEl.innerHTML = '<p class="sc-form-error">Errore caricamento: ' + err.message + '</p>';
                });
            }
            
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Edit event
            function editEvent(id) {
                fetch(schoolCalendarConfig.apiUrl + '/eventi/' + id, {
                    headers: { 'X-WP-Nonce': schoolCalendarConfig.nonce }
                })
                .then(function(r) { return r.json(); })
                .then(function(e) {
                    document.getElementById(instanceId + '-edit-id').value = e.id;
                    document.getElementById(instanceId + '-edit-titolo').value = e.titolo || '';
                    document.getElementById(instanceId + '-edit-descrizione').value = e.descrizione || '';
                    
                    var dataInizio = e.data_inizio.split(' ');
                    var dataFine = e.data_fine.split(' ');
                    document.getElementById(instanceId + '-edit-data-inizio').value = dataInizio[0];
                    document.getElementById(instanceId + '-edit-data-fine').value = dataFine[0];
                    document.getElementById(instanceId + '-edit-ora-inizio').value = dataInizio[1] ? dataInizio[1].substring(0,5) : '08:00';
                    document.getElementById(instanceId + '-edit-ora-fine').value = dataFine[1] ? dataFine[1].substring(0,5) : '09:00';
                    
                    var tuttoGiorno = document.getElementById(instanceId + '-edit-tutto-giorno');
                    tuttoGiorno.checked = e.tutto_giorno == 1;
                    document.getElementById(instanceId + '-edit-ora-inizio').disabled = tuttoGiorno.checked;
                    document.getElementById(instanceId + '-edit-ora-fine').disabled = tuttoGiorno.checked;
                    
                    document.getElementById(instanceId + '-edit-plesso').value = e.plesso_id || '';
                    document.getElementById(instanceId + '-edit-responsabile').value = e.responsabile || '';
                    document.getElementById(instanceId + '-edit-luogo').value = e.luogo_scuola || '';
                    document.getElementById(instanceId + '-edit-visibilita').value = e.visibilita || 'pubblico';
                    
                    // Sub-calendari
                    var subcalSelect = document.getElementById(instanceId + '-edit-subcal');
                    if (subcalSelect && e.sub_calendario_ids) {
                        Array.from(subcalSelect.options).forEach(function(opt) {
                            opt.selected = e.sub_calendario_ids.indexOf(parseInt(opt.value)) !== -1;
                        });
                    }
                    
                    modal.style.display = 'flex';
                    document.getElementById(instanceId + '-edit-message').style.display = 'none';
                });
            }
            
            // Delete event
            function deleteEvent(id) {
                if (!confirm('Sei sicuro di voler eliminare questo evento?')) return;
                
                fetch(schoolCalendarConfig.apiUrl + '/eventi/' + id, {
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': schoolCalendarConfig.nonce }
                })
                .then(function(r) { return r.json(); })
                .then(function(result) {
                    if (result.success) {
                        loadEvents();
                    } else {
                        alert('Errore: ' + (result.message || 'Impossibile eliminare'));
                    }
                })
                .catch(function(err) {
                    alert('Errore: ' + err.message);
                });
            }
            
            // Toggle tutto il giorno
            document.getElementById(instanceId + '-edit-tutto-giorno').addEventListener('change', function() {
                document.getElementById(instanceId + '-edit-ora-inizio').disabled = this.checked;
                document.getElementById(instanceId + '-edit-ora-fine').disabled = this.checked;
            });
            
            // Close modal
            modal.querySelector('.sc-edit-modal-close').addEventListener('click', function() {
                modal.style.display = 'none';
            });
            modal.querySelector('.sc-edit-modal-overlay').addEventListener('click', function() {
                modal.style.display = 'none';
            });
            modal.querySelector('.sc-form-cancel').addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Submit edit form
            document.getElementById(instanceId + '-edit-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                var id = document.getElementById(instanceId + '-edit-id').value;
                var tuttoGiorno = document.getElementById(instanceId + '-edit-tutto-giorno').checked;
                
                var dataInizio = document.getElementById(instanceId + '-edit-data-inizio').value;
                var dataFine = document.getElementById(instanceId + '-edit-data-fine').value;
                
                if (!tuttoGiorno) {
                    dataInizio += ' ' + document.getElementById(instanceId + '-edit-ora-inizio').value + ':00';
                    dataFine += ' ' + document.getElementById(instanceId + '-edit-ora-fine').value + ':00';
                } else {
                    dataInizio += ' 00:00:00';
                    dataFine += ' 23:59:59';
                }
                
                var subcalSelect = document.getElementById(instanceId + '-edit-subcal');
                var subcalIds = subcalSelect ? Array.from(subcalSelect.selectedOptions).map(function(o) { return parseInt(o.value); }) : [];
                
                var data = {
                    titolo: document.getElementById(instanceId + '-edit-titolo').value,
                    descrizione: document.getElementById(instanceId + '-edit-descrizione').value,
                    data_inizio: dataInizio,
                    data_fine: dataFine,
                    tutto_giorno: tuttoGiorno ? 1 : 0,
                    plesso_id: document.getElementById(instanceId + '-edit-plesso').value || null,
                    sub_calendario_ids: subcalIds,
                    responsabile: document.getElementById(instanceId + '-edit-responsabile').value,
                    luogo_scuola: document.getElementById(instanceId + '-edit-luogo').value,
                    visibilita: document.getElementById(instanceId + '-edit-visibilita').value
                };
                
                var messageEl = document.getElementById(instanceId + '-edit-message');
                
                fetch(schoolCalendarConfig.apiUrl + '/eventi/' + id, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': schoolCalendarConfig.nonce
                    },
                    body: JSON.stringify(data)
                })
                .then(function(r) { return r.json(); })
                .then(function(result) {
                    if (result.success) {
                        modal.style.display = 'none';
                        loadEvents();
                    } else {
                        messageEl.className = 'sc-form-message sc-form-error';
                        messageEl.textContent = 'Errore: ' + (result.message || 'Impossibile salvare');
                        messageEl.style.display = 'block';
                    }
                })
                .catch(function(err) {
                    messageEl.className = 'sc-form-message sc-form-error';
                    messageEl.textContent = 'Errore: ' + err.message;
                    messageEl.style.display = 'block';
                });
            });
            
            // Show past toggle
            showPastCheckbox.addEventListener('change', loadEvents);
            
            // Initial load
            loadEvents();
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
