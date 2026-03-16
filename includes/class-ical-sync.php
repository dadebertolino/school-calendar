<?php
namespace SchoolCalendar;

use SchoolCalendar\Models\CalendarioEsterno;
use SchoolCalendar\Models\Evento;
use Exception;

defined('ABSPATH') || exit;

class IcalSync {
    
    private array $errors = [];
    private array $stats = [];
    
    /**
     * Sincronizza tutti i calendari iCal attivi
     */
    public function syncAll(): array {
        $calendari = CalendarioEsterno::daSincronizzare();
        $results = [];
        
        foreach ($calendari as $calendario) {
            if ($calendario->tipo !== 'ical') {
                continue;
            }
            
            $results[$calendario->id] = $this->sync($calendario);
        }
        
        return $results;
    }
    
    /**
     * Sincronizza un singolo calendario iCal
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
            // Scarica feed iCal
            $icalContent = $this->fetchIcalFeed($calendario->calendar_id);
            
            // Parse eventi
            $events = $this->parseIcal($icalContent);
            
            // Sincronizza con database
            $this->syncEvents($calendario, $events);
            
            // Cleanup eventi rimossi
            $this->cleanupDeletedEvents($calendario, $events);
            
            // Aggiorna timestamp
            $calendario->updateLastSync();
            
            $this->stats['success'] = true;
            $this->stats['finished_at'] = current_time('mysql');
            
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            $this->stats['success'] = false;
            $this->stats['error_message'] = $e->getMessage();
        }
        
        $this->stats['errors'] = count($this->errors);
        
        return $this->stats;
    }
    
    /**
     * Scarica feed iCal
     */
    private function fetchIcalFeed(string $url): string {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'text/calendar',
            ],
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore download feed: ' . $response->get_error_message());
        }
        
        $statusCode = wp_remote_retrieve_response_code($response);
        
        if ($statusCode !== 200) {
            throw new Exception("Errore HTTP {$statusCode} scaricando feed");
        }
        
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body) || strpos($body, 'BEGIN:VCALENDAR') === false) {
            throw new Exception('Feed iCal non valido');
        }
        
        return $body;
    }
    
    /**
     * Parse contenuto iCal
     */
    private function parseIcal(string $content): array {
        $events = [];
        
        // Normalizza line endings
        $content = str_replace(["\r\n ", "\r\n\t"], '', $content);
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        
        // Estrai eventi
        preg_match_all('/BEGIN:VEVENT(.+?)END:VEVENT/s', $content, $matches);
        
        foreach ($matches[1] as $eventContent) {
            $event = $this->parseEvent($eventContent);
            
            if ($event && !empty($event['uid'])) {
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    /**
     * Parse singolo evento
     */
    private function parseEvent(string $content): ?array {
        $event = [
            'uid' => null,
            'summary' => null,
            'description' => null,
            'dtstart' => null,
            'dtend' => null,
            'all_day' => false,
            'last_modified' => null,
        ];
        
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            // Parse property
            if (preg_match('/^([A-Z\-]+)(;[^:]+)?:(.*)$/s', $line, $matches)) {
                $property = $matches[1];
                $params = $matches[2] ?? '';
                $value = $matches[3];
                
                switch ($property) {
                    case 'UID':
                        $event['uid'] = $value;
                        break;
                        
                    case 'SUMMARY':
                        $event['summary'] = $this->unescapeIcal($value);
                        break;
                        
                    case 'DESCRIPTION':
                        $event['description'] = $this->unescapeIcal($value);
                        break;
                        
                    case 'DTSTART':
                        $parsed = $this->parseIcalDate($value, $params);
                        $event['dtstart'] = $parsed['datetime'];
                        $event['all_day'] = $parsed['all_day'];
                        break;
                        
                    case 'DTEND':
                        $parsed = $this->parseIcalDate($value, $params);
                        $event['dtend'] = $parsed['datetime'];
                        break;
                        
                    case 'LAST-MODIFIED':
                    case 'DTSTAMP':
                        if (!$event['last_modified']) {
                            $parsed = $this->parseIcalDate($value, $params);
                            $event['last_modified'] = $parsed['datetime'];
                        }
                        break;
                }
            }
        }
        
        // Validazione minima
        if (empty($event['uid']) || empty($event['dtstart'])) {
            return null;
        }
        
        // Default dtend = dtstart
        if (empty($event['dtend'])) {
            $event['dtend'] = $event['dtstart'];
        }
        
        return $event;
    }
    
    /**
     * Parse data iCal
     */
    private function parseIcalDate(string $value, string $params = ''): array {
        $allDay = false;
        
        // Verifica se è solo data (VALUE=DATE)
        if (strpos($params, 'VALUE=DATE') !== false || strlen($value) === 8) {
            $allDay = true;
            
            // YYYYMMDD
            $year = substr($value, 0, 4);
            $month = substr($value, 4, 2);
            $day = substr($value, 6, 2);
            
            return [
                'datetime' => "{$year}-{$month}-{$day} 00:00:00",
                'all_day' => true,
            ];
        }
        
        // YYYYMMDDTHHMMSS o YYYYMMDDTHHMMSSZ
        $value = str_replace('Z', '', $value);
        
        if (strlen($value) >= 15) {
            $year = substr($value, 0, 4);
            $month = substr($value, 4, 2);
            $day = substr($value, 6, 2);
            $hour = substr($value, 9, 2);
            $minute = substr($value, 11, 2);
            $second = substr($value, 13, 2) ?: '00';
            
            return [
                'datetime' => "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}",
                'all_day' => false,
            ];
        }
        
        // Fallback: prova con strtotime
        $timestamp = strtotime($value);
        
        if ($timestamp) {
            return [
                'datetime' => date('Y-m-d H:i:s', $timestamp),
                'all_day' => false,
            ];
        }
        
        return [
            'datetime' => null,
            'all_day' => false,
        ];
    }
    
    /**
     * Unescape valori iCal
     */
    private function unescapeIcal(string $value): string {
        return str_replace(
            ['\\n', '\\,', '\\;', '\\\\'],
            ["\n", ',', ';', '\\'],
            $value
        );
    }
    
    /**
     * Sincronizza eventi con database
     */
    private function syncEvents(CalendarioEsterno $calendario, array $events): void {
        foreach ($events as $icalEvent) {
            try {
                $this->syncSingleEvent($calendario, $icalEvent);
            } catch (Exception $e) {
                $this->errors[] = "Event {$icalEvent['uid']}: " . $e->getMessage();
                $this->stats['errors']++;
            }
        }
    }
    
    /**
     * Sincronizza singolo evento
     */
    private function syncSingleEvent(CalendarioEsterno $calendario, array $icalEvent): void {
        $externalId = $icalEvent['uid'];
        $externalUpdated = $icalEvent['last_modified'];
        
        // Cerca evento esistente
        $evento = Evento::query()
            ->where('source', 'ical')
            ->where('external_id', $externalId)
            ->first();
        
        $eventData = [
            'titolo' => $icalEvent['summary'] ?? '(Senza titolo)',
            'descrizione' => $icalEvent['description'],
            'data_inizio' => $icalEvent['dtstart'],
            'data_fine' => $icalEvent['dtend'],
            'tutto_giorno' => $icalEvent['all_day'] ? 1 : 0,
            'visibilita' => $calendario->visibilita_default,
            'plesso_id' => $calendario->plesso_id,
            'source' => 'ical',
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
            
            $evento->fill($eventData);
            $evento->save();
            $this->stats['updated']++;
            
        } else {
            $evento = new Evento($eventData);
            $evento->save();
            $this->stats['created']++;
        }
    }
    
    /**
     * Rimuovi eventi non più presenti nel feed
     */
    private function cleanupDeletedEvents(CalendarioEsterno $calendario, array $events): void {
        $uids = array_column($events, 'uid');
        
        global $wpdb;
        $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
        
        if (empty($uids)) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$prefix}eventi WHERE source = 'ical' AND calendar_id = %d",
                $calendario->id
            ));
        } else {
            $placeholders = implode(',', array_fill(0, count($uids), '%s'));
            
            $query = $wpdb->prepare(
                "DELETE FROM {$prefix}eventi 
                 WHERE source = 'ical' 
                 AND calendar_id = %d 
                 AND external_id NOT IN ({$placeholders})",
                array_merge([$calendario->id], $uids)
            );
            
            $deleted = $wpdb->query($query);
        }
        
        $this->stats['deleted'] = (int) $deleted;
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
    
    public function getStats(): array {
        return $this->stats;
    }
}
