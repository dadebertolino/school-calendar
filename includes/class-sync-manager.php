<?php
namespace SchoolCalendar;

use SchoolCalendar\Models\CalendarioEsterno;

defined('ABSPATH') || exit;

class SyncManager {
    
    private const CRON_HOOK = 'school_calendar_sync';
    private const CRON_INTERVAL = 'sc_sync_interval';
    
    private GoogleSync $googleSync;
    private IcalSync $icalSync;
    
    public function __construct() {
        $this->googleSync = new GoogleSync();
        $this->icalSync = new IcalSync();
    }
    
    /**
     * Inizializza hooks e cron
     */
    public function init(): void {
        // Registra intervallo cron personalizzato
        add_filter('cron_schedules', [$this, 'addCronSchedule']);
        
        // Hook per esecuzione cron
        add_action(self::CRON_HOOK, [$this, 'runScheduledSync']);
        
        // Attiva cron se non già schedulato
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), self::CRON_INTERVAL, self::CRON_HOOK);
        }
        
        // Hook per sync manuale via admin
        add_action('wp_ajax_sc_sync_calendar', [$this, 'ajaxSyncCalendar']);
        add_action('wp_ajax_sc_sync_all', [$this, 'ajaxSyncAll']);
    }
    
    /**
     * Aggiunge intervallo cron personalizzato
     */
    public function addCronSchedule(array $schedules): array {
        $schedules[self::CRON_INTERVAL] = [
            'interval' => 5 * MINUTE_IN_SECONDS, // Ogni 5 minuti
            'display' => __('Every 5 Minutes', 'school-calendar'),
        ];
        
        return $schedules;
    }
    
    /**
     * Esegue sync schedulata
     */
    public function runScheduledSync(): void {
        $this->log('info', 'Starting scheduled sync');
        
        $results = $this->syncAll();
        
        $this->log('info', 'Scheduled sync completed', [
            'calendars_processed' => count($results),
        ]);
    }
    
    /**
     * Sincronizza tutti i calendari esterni
     */
    public function syncAll(): array {
        $results = [
            'google' => [],
            'ical' => [],
            'started_at' => current_time('mysql'),
        ];
        
        // Sync Google Calendars
        $results['google'] = $this->googleSync->syncAll();
        
        // Sync iCal feeds
        $results['ical'] = $this->icalSync->syncAll();
        
        $results['finished_at'] = current_time('mysql');
        $results['total_calendars'] = count($results['google']) + count($results['ical']);
        
        // Salva risultato ultima sync
        update_option('sc_last_sync_results', $results);
        
        return $results;
    }
    
    /**
     * Sincronizza un singolo calendario
     */
    public function syncCalendar(int $calendarioId): array {
        $calendario = CalendarioEsterno::find($calendarioId);
        
        if (!$calendario) {
            return [
                'success' => false,
                'error' => 'Calendario non trovato',
            ];
        }
        
        if (!$calendario->attivo) {
            return [
                'success' => false,
                'error' => 'Calendario non attivo',
            ];
        }
        
        switch ($calendario->tipo) {
            case 'google':
                return $this->googleSync->sync($calendario);
                
            case 'ical':
                return $this->icalSync->sync($calendario);
                
            default:
                return [
                    'success' => false,
                    'error' => 'Tipo calendario non supportato',
                ];
        }
    }
    
    /**
     * AJAX: Sync singolo calendario
     */
    public function ajaxSyncCalendar(): void {
        check_ajax_referer('sc_sync_nonce', 'nonce');
        
        if (!current_user_can('sc_manage_settings')) {
            wp_send_json_error(['message' => 'Non autorizzato'], 403);
        }
        
        $calendarioId = (int) ($_POST['calendario_id'] ?? 0);
        
        if (!$calendarioId) {
            wp_send_json_error(['message' => 'ID calendario mancante'], 400);
        }
        
        $result = $this->syncCalendar($calendarioId);
        
        if ($result['success'] ?? false) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Sync tutti i calendari
     */
    public function ajaxSyncAll(): void {
        check_ajax_referer('sc_sync_nonce', 'nonce');
        
        if (!current_user_can('sc_manage_settings')) {
            wp_send_json_error(['message' => 'Non autorizzato'], 403);
        }
        
        $results = $this->syncAll();
        
        wp_send_json_success($results);
    }
    
    /**
     * Disattiva cron
     */
    public static function deactivate(): void {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
    
    /**
     * Restituisce stato sync
     */
    public function getStatus(): array {
        $nextRun = wp_next_scheduled(self::CRON_HOOK);
        $lastResults = get_option('sc_last_sync_results', []);
        
        $calendari = CalendarioEsterno::attivi();
        
        return [
            'cron_active' => (bool) $nextRun,
            'next_run' => $nextRun ? date('Y-m-d H:i:s', $nextRun) : null,
            'last_sync' => $lastResults['finished_at'] ?? null,
            'calendars_count' => count($calendari),
            'last_results' => $lastResults,
        ];
    }
    
    /**
     * Logging helper
     */
    private function log(string $level, string $message, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SchoolCalendar][SyncManager][{$level}] {$message}");
        }
        
        do_action('school_calendar_sync_log', $level, $message, $context);
    }
}
