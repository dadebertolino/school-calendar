<?php
namespace SchoolCalendar;

use SchoolCalendar\Models\CalendarioEsterno;
use SchoolCalendar\Models\Evento;
use Exception;

defined('ABSPATH') || exit;

class GoogleSync {
    
    private const GOOGLE_CALENDAR_API = 'https://www.googleapis.com/calendar/v3';
    private const SCOPES = ['https://www.googleapis.com/auth/calendar.readonly'];
    
    private ?string $accessToken = null;
    private array $errors = [];
    private array $stats = [];
    
    /**
     * Sincronizza tutti i calendari Google attivi
     */
    public function syncAll(): array {
        $calendari = CalendarioEsterno::daSincronizzare();
        $results = [];
        
        foreach ($calendari as $calendario) {
            if ($calendario->tipo !== 'google') {
                continue;
            }
            
            $results[$calendario->id] = $this->sync($calendario);
        }
        
        return $results;
    }
    
    /**
     * Sincronizza un singolo calendario
     */
    public function sync(CalendarioEsterno $calendario): array {
        $this->errors = [];
        $this->stats = [
            'calendario_id' => $calendario->id,
            'calendario_nome' => $calendario->nome,
            'started_at' => current_time('mysql'),
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        
        try {
            // Ottieni access token
            $this->accessToken = $this->getAccessToken($calendario);
            
            if (!$this->accessToken) {
                throw new Exception('Impossibile ottenere access token');
            }
            
            // Recupera eventi da Google
            $googleEvents = $this->fetchGoogleEvents($calendario);
            
            // Sincronizza con database locale
            $this->syncEvents($calendario, $googleEvents);
            
            // Rimuovi eventi non più presenti in Google
            $this->cleanupDeletedEvents($calendario, $googleEvents);
            
            // Aggiorna timestamp ultima sync
            $calendario->updateLastSync();
            
            $this->stats['success'] = true;
            $this->stats['finished_at'] = current_time('mysql');
            
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            $this->stats['success'] = false;
            $this->stats['error_message'] = $e->getMessage();
            
            $this->log('error', "Sync failed for calendar {$calendario->id}: " . $e->getMessage());
        }
        
        $this->stats['errors'] = count($this->errors);
        
        return $this->stats;
    }
    
    /**
     * Ottiene access token tramite Service Account
     */
    private function getAccessToken(CalendarioEsterno $calendario): ?string {
        $credentials = $calendario->getCredentials();
        
        if (!$credentials) {
            throw new Exception('Credenziali non configurate');
        }
        
        // Verifica campi necessari
        $required = ['client_email', 'private_key', 'token_uri'];
        foreach ($required as $field) {
            if (empty($credentials[$field])) {
                throw new Exception("Campo mancante nelle credenziali: {$field}");
            }
        }
        
        // Crea JWT
        $jwt = $this->createJwt($credentials);
        
        // Scambia JWT per access token
        $response = wp_remote_post($credentials['token_uri'], [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore richiesta token: ' . $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['access_token'])) {
            $error = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
            throw new Exception("Errore ottenimento token: {$error}");
        }
        
        return $body['access_token'];
    }
    
    /**
     * Crea JWT per autenticazione Service Account
     */
    private function createJwt(array $credentials): string {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];
        
        $now = time();
        $payload = [
            'iss' => $credentials['client_email'],
            'scope' => implode(' ', self::SCOPES),
            'aud' => $credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ];
        
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signatureInput = "{$headerEncoded}.{$payloadEncoded}";
        
        // Firma con chiave privata
        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        
        if (!$privateKey) {
            throw new Exception('Chiave privata non valida');
        }
        
        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception('Errore firma JWT');
        }
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return "{$signatureInput}.{$signatureEncoded}";
    }
    
    /**
     * Recupera eventi da Google Calendar
     */
    private function fetchGoogleEvents(CalendarioEsterno $calendario): array {
        $events = [];
        $pageToken = null;
        
        // Range: da 1 mese fa a 1 anno avanti
        $timeMin = date('c', strtotime('-1 month'));
        $timeMax = date('c', strtotime('+1 year'));
        
        do {
            $url = self::GOOGLE_CALENDAR_API . '/calendars/' . urlencode($calendario->calendar_id) . '/events';
            
            $params = [
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'maxResults' => 250,
            ];
            
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }
            
            $url .= '?' . http_build_query($params);
            
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
                'timeout' => 30,
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception('Errore fetch eventi: ' . $response->get_error_message());
            }
            
            $statusCode = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($statusCode !== 200) {
                $error = $body['error']['message'] ?? "HTTP {$statusCode}";
                throw new Exception("Errore API Google: {$error}");
            }
            
            if (!empty($body['items'])) {
                $events = array_merge($events, $body['items']);
            }
            
            $pageToken = $body['nextPageToken'] ?? null;
            
        } while ($pageToken);
        
        $this->log('info', "Fetched " . count($events) . " events from Google Calendar {$calendario->calendar_id}");
        
        return $events;
    }
    
    /**
     * Sincronizza eventi Google con database locale
     */
    private function syncEvents(CalendarioEsterno $calendario, array $googleEvents): void {
        foreach ($googleEvents as $gEvent) {
            try {
                $this->syncSingleEvent($calendario, $gEvent);
            } catch (Exception $e) {
                $this->errors[] = "Event {$gEvent['id']}: " . $e->getMessage();
                $this->stats['errors']++;
            }
        }
    }
    
    /**
     * Sincronizza singolo evento
     */
    private function syncSingleEvent(CalendarioEsterno $calendario, array $gEvent): void {
        // Salta eventi cancellati
        if (($gEvent['status'] ?? '') === 'cancelled') {
            return;
        }
        
        $externalId = $gEvent['id'];
        $externalUpdated = $gEvent['updated'] ?? null;
        
        // Cerca evento esistente
        $evento = Evento::query()
            ->where('source', 'google')
            ->where('external_id', $externalId)
            ->first();
        
        // Parse date
        $dates = $this->parseGoogleDates($gEvent);
        
        if (!$dates) {
            $this->stats['skipped']++;
            return;
        }
        
        $eventData = [
            'titolo' => $gEvent['summary'] ?? '(Senza titolo)',
            'descrizione' => $gEvent['description'] ?? null,
            'data_inizio' => $dates['start'],
            'data_fine' => $dates['end'],
            'tutto_giorno' => $dates['all_day'] ? 1 : 0,
            'visibilita' => $calendario->visibilita_default,
            'plesso_id' => $calendario->plesso_id,
            'source' => 'google',
            'external_id' => $externalId,
            'external_updated' => $externalUpdated,
            'calendar_id' => $calendario->id,
        ];
        
        if ($evento) {
            // Verifica se aggiornato
            if ($evento->external_updated === $externalUpdated) {
                $this->stats['skipped']++;
                return;
            }
            
            // Aggiorna
            $evento->fill($eventData);
            $evento->save();
            $this->stats['updated']++;
            
        } else {
            // Crea nuovo
            $evento = new Evento($eventData);
            $evento->save();
            $this->stats['created']++;
        }
    }
    
    /**
     * Parse date da formato Google
     */
    private function parseGoogleDates(array $gEvent): ?array {
        $start = $gEvent['start'] ?? null;
        $end = $gEvent['end'] ?? null;
        
        if (!$start) {
            return null;
        }
        
        // Evento tutto il giorno
        if (isset($start['date'])) {
            return [
                'start' => $start['date'] . ' 00:00:00',
                'end' => ($end['date'] ?? $start['date']) . ' 23:59:59',
                'all_day' => true,
            ];
        }
        
        // Evento con orario
        if (isset($start['dateTime'])) {
            $startDt = new \DateTime($start['dateTime']);
            $endDt = isset($end['dateTime']) ? new \DateTime($end['dateTime']) : clone $startDt;
            
            return [
                'start' => $startDt->format('Y-m-d H:i:s'),
                'end' => $endDt->format('Y-m-d H:i:s'),
                'all_day' => false,
            ];
        }
        
        return null;
    }
    
    /**
     * Rimuove eventi locali non più presenti in Google
     */
    private function cleanupDeletedEvents(CalendarioEsterno $calendario, array $googleEvents): void {
        // Raccogli tutti gli ID esterni presenti in Google
        $googleIds = array_column($googleEvents, 'id');
        
        // Trova eventi locali che non sono più in Google
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        $placeholders = implode(',', array_fill(0, count($googleIds), '%s'));
        
        if (empty($googleIds)) {
            // Se non ci sono eventi in Google, elimina tutti gli eventi di questo calendario
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$prefix}eventi WHERE source = 'google' AND calendar_id = %d",
                $calendario->id
            ));
        } else {
            // Elimina eventi non più presenti
            $query = $wpdb->prepare(
                "DELETE FROM {$prefix}eventi 
                 WHERE source = 'google' 
                 AND calendar_id = %d 
                 AND external_id NOT IN ({$placeholders})",
                array_merge([$calendario->id], $googleIds)
            );
            
            $deleted = $wpdb->query($query);
        }
        
        $this->stats['deleted'] = (int) $deleted;
    }
    
    /**
     * Helper: Base64 URL encode
     */
    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Logging
     */
    private function log(string $level, string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SchoolCalendar][{$level}] {$message}");
        }
        
        do_action('school_calendar_log', $level, $message, $this->stats);
    }
    
    /**
     * Restituisce errori dell'ultima sincronizzazione
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Restituisce statistiche dell'ultima sincronizzazione
     */
    public function getStats(): array {
        return $this->stats;
    }
}
