<?php
/**
 * School Calendar API Client - Advanced Version
 * 
 * Features:
 * - Caching (file-based, Redis, Memcached, custom)
 * - Retry logic con exponential backoff
 * - Request/Response logging
 * - Rate limiting awareness
 * - Batch operations
 * - Event helpers e utilities
 * 
 * @version 2.0.0
 */

namespace SchoolCalendar;

use Exception;
use InvalidArgumentException;

class Client {
    
    private string $baseUrl;
    private string $apiKey;
    private int $timeout = 30;
    private int $maxRetries = 3;
    private array $lastError = [];
    private array $lastResponse = [];
    private ?CacheInterface $cache = null;
    private ?LoggerInterface $logger = null;
    private array $defaultCacheTtl = [
        'plessi' => 3600,      // 1 ora
        'classi' => 3600,      // 1 ora
        'eventi' => 300,       // 5 minuti
        'evento' => 300,       // 5 minuti
    ];
    
    /**
     * @param string $baseUrl URL base API (es. https://sito.it/wp-json/school-calendar/v1)
     * @param string $apiKey  API Key
     * @param array  $options Opzioni: cache, logger, timeout, maxRetries
     */
    public function __construct(string $baseUrl, string $apiKey, array $options = []) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        
        if (isset($options['timeout'])) {
            $this->timeout = (int) $options['timeout'];
        }
        
        if (isset($options['maxRetries'])) {
            $this->maxRetries = (int) $options['maxRetries'];
        }
        
        if (isset($options['cache']) && $options['cache'] instanceof CacheInterface) {
            $this->cache = $options['cache'];
        }
        
        if (isset($options['logger']) && $options['logger'] instanceof LoggerInterface) {
            $this->logger = $options['logger'];
        }
        
        if (isset($options['cacheTtl']) && is_array($options['cacheTtl'])) {
            $this->defaultCacheTtl = array_merge($this->defaultCacheTtl, $options['cacheTtl']);
        }
    }
    
    // =========================================================================
    // CONFIGURATION
    // =========================================================================
    
    public function setCache(CacheInterface $cache): self {
        $this->cache = $cache;
        return $this;
    }
    
    public function setLogger(LoggerInterface $logger): self {
        $this->logger = $logger;
        return $this;
    }
    
    public function setTimeout(int $seconds): self {
        $this->timeout = $seconds;
        return $this;
    }
    
    public function setMaxRetries(int $retries): self {
        $this->maxRetries = $retries;
        return $this;
    }
    
    public function getLastError(): array {
        return $this->lastError;
    }
    
    public function getLastResponse(): array {
        return $this->lastResponse;
    }
    
    // =========================================================================
    // EVENTI
    // =========================================================================
    
    /**
     * Lista eventi con filtri
     */
    public function getEventi(array $params = []): ?array {
        $cacheKey = 'eventi_' . md5(serialize($params));
        
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        $result = $this->get('/eventi', $params);
        
        if ($result !== null) {
            $this->setCache($cacheKey, $result, $this->defaultCacheTtl['eventi']);
        }
        
        return $result;
    }
    
    /**
     * Singolo evento
     */
    public function getEvento(int $id): ?array {
        $cacheKey = "evento_{$id}";
        
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        $result = $this->get("/eventi/{$id}");
        
        if ($result !== null) {
            $this->setCache($cacheKey, $result, $this->defaultCacheTtl['evento']);
        }
        
        return $result;
    }
    
    /**
     * Crea evento
     */
    public function createEvento(array $data): ?array {
        $this->validateEventoData($data);
        $result = $this->post('/eventi', $data);
        
        if ($result !== null) {
            $this->invalidateEventiCache();
        }
        
        return $result;
    }
    
    /**
     * Aggiorna evento
     */
    public function updateEvento(int $id, array $data): ?array {
        $result = $this->put("/eventi/{$id}", $data);
        
        if ($result !== null) {
            $this->invalidateEventiCache();
            $this->deleteFromCache("evento_{$id}");
        }
        
        return $result;
    }
    
    /**
     * Elimina evento
     */
    public function deleteEvento(int $id): bool {
        $result = $this->delete("/eventi/{$id}");
        
        if ($result !== null && ($result['success'] ?? false)) {
            $this->invalidateEventiCache();
            $this->deleteFromCache("evento_{$id}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Creazione batch di eventi
     */
    public function createEventiBatch(array $eventi): array {
        $results = [
            'success' => [],
            'failed' => [],
        ];
        
        foreach ($eventi as $index => $evento) {
            try {
                $this->validateEventoData($evento);
                $result = $this->post('/eventi', $evento);
                
                if ($result !== null) {
                    $results['success'][] = $result;
                } else {
                    $results['failed'][] = [
                        'index' => $index,
                        'data' => $evento,
                        'error' => $this->lastError,
                    ];
                }
            } catch (Exception $e) {
                $results['failed'][] = [
                    'index' => $index,
                    'data' => $evento,
                    'error' => ['message' => $e->getMessage()],
                ];
            }
        }
        
        if (!empty($results['success'])) {
            $this->invalidateEventiCache();
        }
        
        return $results;
    }
    
    // =========================================================================
    // PLESSI
    // =========================================================================
    
    /**
     * Lista plessi
     */
    public function getPlessi(bool $includeClassi = false): ?array {
        $cacheKey = 'plessi_' . ($includeClassi ? 'full' : 'base');
        
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        $params = $includeClassi ? ['include_classi' => 'true'] : [];
        $result = $this->get('/plessi', $params);
        
        if ($result !== null) {
            $this->setCache($cacheKey, $result, $this->defaultCacheTtl['plessi']);
        }
        
        return $result;
    }
    
    /**
     * Singolo plesso
     */
    public function getPlesso(int $id): ?array {
        $cacheKey = "plesso_{$id}";
        
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        $result = $this->get("/plessi/{$id}");
        
        if ($result !== null) {
            $this->setCache($cacheKey, $result, $this->defaultCacheTtl['plessi']);
        }
        
        return $result;
    }
    
    /**
     * Mappa plessi id => nome
     */
    public function getPlessiMap(): array {
        $plessi = $this->getPlessi();
        
        if ($plessi === null) {
            return [];
        }
        
        $map = [];
        foreach ($plessi as $plesso) {
            $map[$plesso['id']] = $plesso['nome'];
        }
        
        return $map;
    }
    
    // =========================================================================
    // CLASSI
    // =========================================================================
    
    /**
     * Lista classi
     */
    public function getClassi(?int $plessoId = null, ?string $annoScolastico = null): ?array {
        $cacheKey = 'classi_' . ($plessoId ?? 'all') . '_' . ($annoScolastico ?? 'current');
        
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        $params = [];
        if ($plessoId) $params['plesso_id'] = $plessoId;
        if ($annoScolastico) $params['anno_scolastico'] = $annoScolastico;
        
        $result = $this->get('/classi', $params);
        
        if ($result !== null) {
            $this->setCache($cacheKey, $result, $this->defaultCacheTtl['classi']);
        }
        
        return $result;
    }
    
    /**
     * Mappa classi id => nome (opzionalmente per plesso)
     */
    public function getClassiMap(?int $plessoId = null): array {
        $classi = $this->getClassi($plessoId);
        
        if ($classi === null) {
            return [];
        }
        
        $map = [];
        foreach ($classi as $classe) {
            $map[$classe['id']] = $classe['nome'];
        }
        
        return $map;
    }
    
    // =========================================================================
    // CALENDARI ESTERNI
    // =========================================================================
    
    public function getCalendariEsterni(): ?array {
        return $this->get('/calendari-esterni');
    }
    
    public function syncCalendarioEsterno(int $id): ?array {
        return $this->post("/calendari-esterni/{$id}/sync");
    }
    
    public function syncAllCalendari(): array {
        $calendari = $this->getCalendariEsterni();
        
        if ($calendari === null) {
            return ['error' => $this->lastError];
        }
        
        $results = [];
        foreach ($calendari as $cal) {
            $results[$cal['id']] = $this->syncCalendarioEsterno($cal['id']);
        }
        
        return $results;
    }
    
    // =========================================================================
    // HELPER METHODS - DATE RANGES
    // =========================================================================
    
    /**
     * Eventi oggi
     */
    public function getEventiOggi(?int $plessoId = null, ?int $classeId = null): ?array {
        $oggi = date('Y-m-d');
        return $this->getEventi([
            'start' => $oggi . ' 00:00:00',
            'end' => $oggi . ' 23:59:59',
            'plesso_id' => $plessoId,
            'classe_id' => $classeId,
        ]);
    }
    
    /**
     * Eventi domani
     */
    public function getEventiDomani(?int $plessoId = null, ?int $classeId = null): ?array {
        $domani = date('Y-m-d', strtotime('+1 day'));
        return $this->getEventi([
            'start' => $domani . ' 00:00:00',
            'end' => $domani . ' 23:59:59',
            'plesso_id' => $plessoId,
            'classe_id' => $classeId,
        ]);
    }
    
    /**
     * Eventi settimana corrente
     */
    public function getEventiSettimana(?int $plessoId = null, ?int $classeId = null): ?array {
        return $this->getEventi([
            'start' => date('Y-m-d', strtotime('monday this week')),
            'end' => date('Y-m-d', strtotime('sunday this week')),
            'plesso_id' => $plessoId,
            'classe_id' => $classeId,
        ]);
    }
    
    /**
     * Eventi mese corrente
     */
    public function getEventiMese(?int $plessoId = null, ?int $classeId = null): ?array {
        return $this->getEventi([
            'start' => date('Y-m-01'),
            'end' => date('Y-m-t'),
            'plesso_id' => $plessoId,
            'classe_id' => $classeId,
        ]);
    }
    
    /**
     * Eventi per mese specifico
     */
    public function getEventiByMese(int $anno, int $mese, ?int $plessoId = null): ?array {
        $start = sprintf('%04d-%02d-01', $anno, $mese);
        $end = date('Y-m-t', strtotime($start));
        
        return $this->getEventi([
            'start' => $start,
            'end' => $end,
            'plesso_id' => $plessoId,
        ]);
    }
    
    /**
     * Prossimi N eventi
     */
    public function getProssimiEventi(int $limit = 10, ?int $plessoId = null, ?int $classeId = null): ?array {
        return $this->getEventi([
            'start' => date('Y-m-d H:i:s'),
            'limit' => $limit,
            'plesso_id' => $plessoId,
            'classe_id' => $classeId,
        ]);
    }
    
    /**
     * Eventi in un range di date
     */
    public function getEventiRange(string $start, string $end, ?int $plessoId = null): ?array {
        return $this->getEventi([
            'start' => $start,
            'end' => $end,
            'plesso_id' => $plessoId,
        ]);
    }
    
    // =========================================================================
    // HELPER METHODS - AGGREGAZIONI
    // =========================================================================
    
    /**
     * Conta eventi per periodo
     */
    public function countEventi(array $params = []): int {
        $eventi = $this->getEventi($params);
        return $eventi !== null ? count($eventi) : 0;
    }
    
    /**
     * Eventi raggruppati per giorno
     */
    public function getEventiGroupedByDay(string $start, string $end, ?int $plessoId = null): array {
        $eventi = $this->getEventiRange($start, $end, $plessoId);
        
        if ($eventi === null) {
            return [];
        }
        
        $grouped = [];
        foreach ($eventi as $evento) {
            $day = substr($evento['data_inizio'], 0, 10);
            $grouped[$day][] = $evento;
        }
        
        ksort($grouped);
        return $grouped;
    }
    
    /**
     * Eventi raggruppati per plesso
     */
    public function getEventiGroupedByPlesso(string $start, string $end): array {
        $eventi = $this->getEventiRange($start, $end);
        
        if ($eventi === null) {
            return [];
        }
        
        $grouped = [
            'tutti' => [], // Eventi senza plesso specifico
        ];
        
        foreach ($eventi as $evento) {
            $plessoId = $evento['plesso_id'] ?? null;
            
            if ($plessoId === null) {
                $grouped['tutti'][] = $evento;
            } else {
                $grouped[$plessoId][] = $evento;
            }
        }
        
        return $grouped;
    }
    
    /**
     * Calendario mensile con eventi
     */
    public function getCalendarioMensile(int $anno, int $mese, ?int $plessoId = null): array {
        $eventi = $this->getEventiByMese($anno, $mese, $plessoId) ?? [];
        
        $firstDay = mktime(0, 0, 0, $mese, 1, $anno);
        $daysInMonth = (int) date('t', $firstDay);
        $startWeekday = (int) date('N', $firstDay); // 1=Lun, 7=Dom
        
        // Raggruppa eventi per giorno
        $eventiByDay = [];
        foreach ($eventi as $evento) {
            $day = (int) substr($evento['data_inizio'], 8, 2);
            $eventiByDay[$day][] = $evento;
        }
        
        // Costruisci griglia calendario
        $calendar = [
            'anno' => $anno,
            'mese' => $mese,
            'nome_mese' => $this->getNomeMese($mese),
            'giorni' => [],
            'settimane' => [],
        ];
        
        $week = [];
        
        // Padding inizio mese
        for ($i = 1; $i < $startWeekday; $i++) {
            $week[] = null;
        }
        
        // Giorni del mese
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $anno, $mese, $day);
            
            $dayData = [
                'giorno' => $day,
                'data' => $date,
                'eventi' => $eventiByDay[$day] ?? [],
                'weekend' => in_array(date('N', strtotime($date)), [6, 7]),
                'oggi' => $date === date('Y-m-d'),
            ];
            
            $calendar['giorni'][$day] = $dayData;
            $week[] = $dayData;
            
            if (count($week) === 7) {
                $calendar['settimane'][] = $week;
                $week = [];
            }
        }
        
        // Padding fine mese
        if (!empty($week)) {
            while (count($week) < 7) {
                $week[] = null;
            }
            $calendar['settimane'][] = $week;
        }
        
        return $calendar;
    }
    
    private function getNomeMese(int $mese): string {
        $nomi = [
            1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
            5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
            9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
        ];
        return $nomi[$mese] ?? '';
    }
    
    // =========================================================================
    // VALIDATION
    // =========================================================================
    
    private function validateEventoData(array $data, bool $isUpdate = false): void {
        if (!$isUpdate) {
            $required = ['titolo', 'data_inizio', 'data_fine'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("Campo '{$field}' obbligatorio");
                }
            }
        }
        
        if (!empty($data['data_inizio']) && !$this->isValidDateTime($data['data_inizio'])) {
            throw new InvalidArgumentException("data_inizio non valida");
        }
        
        if (!empty($data['data_fine']) && !$this->isValidDateTime($data['data_fine'])) {
            throw new InvalidArgumentException("data_fine non valida");
        }
        
        if (!empty($data['data_inizio']) && !empty($data['data_fine'])) {
            if (strtotime($data['data_fine']) < strtotime($data['data_inizio'])) {
                throw new InvalidArgumentException("data_fine deve essere >= data_inizio");
            }
        }
        
        if (!empty($data['visibilita']) && !in_array($data['visibilita'], ['pubblico', 'privato'])) {
            throw new InvalidArgumentException("visibilita deve essere 'pubblico' o 'privato'");
        }
    }
    
    private function isValidDateTime(string $datetime): bool {
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
        foreach ($formats as $format) {
            $d = \DateTime::createFromFormat($format, $datetime);
            if ($d && $d->format($format) === $datetime) {
                return true;
            }
        }
        return false;
    }
    
    // =========================================================================
    // CACHE
    // =========================================================================
    
    private function getFromCache(string $key) {
        if ($this->cache === null) {
            return null;
        }
        
        try {
            return $this->cache->get($key);
        } catch (Exception $e) {
            $this->log('warning', "Cache get error: " . $e->getMessage());
            return null;
        }
    }
    
    private function setCache(string $key, $value, int $ttl): void {
        if ($this->cache === null) {
            return;
        }
        
        try {
            $this->cache->set($key, $value, $ttl);
        } catch (Exception $e) {
            $this->log('warning', "Cache set error: " . $e->getMessage());
        }
    }
    
    private function deleteFromCache(string $key): void {
        if ($this->cache === null) {
            return;
        }
        
        try {
            $this->cache->delete($key);
        } catch (Exception $e) {
            $this->log('warning', "Cache delete error: " . $e->getMessage());
        }
    }
    
    private function invalidateEventiCache(): void {
        if ($this->cache === null) {
            return;
        }
        
        try {
            $this->cache->deletePattern('eventi_*');
        } catch (Exception $e) {
            $this->log('warning', "Cache invalidate error: " . $e->getMessage());
        }
    }
    
    public function clearCache(): void {
        if ($this->cache === null) {
            return;
        }
        
        try {
            $this->cache->clear();
        } catch (Exception $e) {
            $this->log('warning', "Cache clear error: " . $e->getMessage());
        }
    }
    
    // =========================================================================
    // LOGGING
    // =========================================================================
    
    private function log(string $level, string $message, array $context = []): void {
        if ($this->logger === null) {
            return;
        }
        
        $this->logger->log($level, $message, $context);
    }
    
    // =========================================================================
    // HTTP METHODS
    // =========================================================================
    
    private function get(string $endpoint, array $params = []): ?array {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query(array_filter($params, fn($v) => $v !== null));
        }
        
        return $this->request('GET', $url);
    }
    
    private function post(string $endpoint, array $data = []): ?array {
        return $this->request('POST', $this->baseUrl . $endpoint, $data);
    }
    
    private function put(string $endpoint, array $data = []): ?array {
        return $this->request('PUT', $this->baseUrl . $endpoint, $data);
    }
    
    private function delete(string $endpoint): ?array {
        return $this->request('DELETE', $this->baseUrl . $endpoint);
    }
    
    private function request(string $method, string $url, ?array $data = null): ?array {
        $this->lastError = [];
        $this->lastResponse = [];
        
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $this->maxRetries) {
            $attempt++;
            
            try {
                $result = $this->doRequest($method, $url, $data);
                
                $this->log('info', "API Request", [
                    'method' => $method,
                    'url' => $url,
                    'attempt' => $attempt,
                    'success' => true,
                ]);
                
                return $result;
                
            } catch (ApiException $e) {
                $lastException = $e;
                
                // Non ritentare per errori client (4xx)
                if ($e->getCode() >= 400 && $e->getCode() < 500) {
                    break;
                }
                
                $this->log('warning', "API Request failed, retrying", [
                    'method' => $method,
                    'url' => $url,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
                
                // Exponential backoff
                if ($attempt < $this->maxRetries) {
                    usleep((2 ** $attempt) * 100000); // 200ms, 400ms, 800ms...
                }
            }
        }
        
        // Tutti i tentativi falliti
        $this->lastError = [
            'code' => $lastException ? $lastException->getCode() : 0,
            'message' => $lastException ? $lastException->getMessage() : 'Unknown error',
        ];
        
        $this->log('error', "API Request failed permanently", [
            'method' => $method,
            'url' => $url,
            'attempts' => $attempt,
            'error' => $this->lastError,
        ]);
        
        return null;
    }
    
    private function doRequest(string $method, string $url, ?array $data): array {
        $ch = curl_init();
        
        $headers = [
            'X-SC-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
        }
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        
        curl_close($ch);
        
        if ($errno) {
            throw new ApiException("cURL error: {$error}", $errno);
        }
        
        $decoded = json_decode($response, true);
        
        $this->lastResponse = [
            'status' => $statusCode,
            'body' => $decoded,
        ];
        
        if ($statusCode >= 400) {
            $message = $decoded['message'] ?? "HTTP {$statusCode}";
            throw new ApiException($message, $statusCode);
        }
        
        return $decoded;
    }
}

// =========================================================================
// EXCEPTIONS
// =========================================================================

class ApiException extends Exception {}

// =========================================================================
// INTERFACES
// =========================================================================

interface CacheInterface {
    public function get(string $key);
    public function set(string $key, $value, int $ttl): bool;
    public function delete(string $key): bool;
    public function deletePattern(string $pattern): bool;
    public function clear(): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message, array $context = []): void;
}

// =========================================================================
// CACHE IMPLEMENTATIONS
// =========================================================================

/**
 * File-based cache
 */
class FileCache implements CacheInterface {
    
    private string $directory;
    
    public function __construct(string $directory) {
        $this->directory = rtrim($directory, '/');
        
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }
    
    public function get(string $key) {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        $data = unserialize($content);
        
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    public function set(string $key, $value, int $ttl): bool {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];
        
        return file_put_contents($file, serialize($data)) !== false;
    }
    
    public function delete(string $key): bool {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    public function deletePattern(string $pattern): bool {
        $pattern = str_replace('*', '.*', $pattern);
        $files = glob($this->directory . '/sc_cache_*');
        
        foreach ($files as $file) {
            $key = str_replace([$this->directory . '/sc_cache_', '.cache'], '', $file);
            if (preg_match('/^' . $pattern . '$/', $key)) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    public function clear(): bool {
        $files = glob($this->directory . '/sc_cache_*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    private function getFilePath(string $key): string {
        return $this->directory . '/sc_cache_' . md5($key) . '.cache';
    }
}

/**
 * Redis cache
 */
class RedisCache implements CacheInterface {
    
    private \Redis $redis;
    private string $prefix = 'sc:';
    
    public function __construct(\Redis $redis, string $prefix = 'sc:') {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }
    
    public function get(string $key) {
        $value = $this->redis->get($this->prefix . $key);
        
        if ($value === false) {
            return null;
        }
        
        return unserialize($value);
    }
    
    public function set(string $key, $value, int $ttl): bool {
        return $this->redis->setex($this->prefix . $key, $ttl, serialize($value));
    }
    
    public function delete(string $key): bool {
        return $this->redis->del($this->prefix . $key) > 0;
    }
    
    public function deletePattern(string $pattern): bool {
        $keys = $this->redis->keys($this->prefix . $pattern);
        
        if (!empty($keys)) {
            $this->redis->del(...$keys);
        }
        
        return true;
    }
    
    public function clear(): bool {
        $keys = $this->redis->keys($this->prefix . '*');
        
        if (!empty($keys)) {
            $this->redis->del(...$keys);
        }
        
        return true;
    }
}

/**
 * APCu cache
 */
class ApcuCache implements CacheInterface {
    
    private string $prefix = 'sc:';
    
    public function __construct(string $prefix = 'sc:') {
        if (!function_exists('apcu_fetch')) {
            throw new \RuntimeException('APCu extension not available');
        }
        $this->prefix = $prefix;
    }
    
    public function get(string $key) {
        $success = false;
        $value = apcu_fetch($this->prefix . $key, $success);
        
        return $success ? $value : null;
    }
    
    public function set(string $key, $value, int $ttl): bool {
        return apcu_store($this->prefix . $key, $value, $ttl);
    }
    
    public function delete(string $key): bool {
        return apcu_delete($this->prefix . $key);
    }
    
    public function deletePattern(string $pattern): bool {
        $pattern = str_replace('*', '.*', $pattern);
        $iterator = new \APCUIterator('/^' . preg_quote($this->prefix, '/') . $pattern . '$/');
        
        foreach ($iterator as $entry) {
            apcu_delete($entry['key']);
        }
        
        return true;
    }
    
    public function clear(): bool {
        $iterator = new \APCUIterator('/^' . preg_quote($this->prefix, '/') . '/');
        
        foreach ($iterator as $entry) {
            apcu_delete($entry['key']);
        }
        
        return true;
    }
}

// =========================================================================
// LOGGER IMPLEMENTATIONS
// =========================================================================

/**
 * File logger
 */
class FileLogger implements LoggerInterface {
    
    private string $file;
    
    public function __construct(string $file) {
        $this->file = $file;
        
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    public function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
        
        file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Callable logger (per integrazione con sistemi esistenti)
 */
class CallableLogger implements LoggerInterface {
    
    private $callable;
    
    public function __construct(callable $callable) {
        $this->callable = $callable;
    }
    
    public function log(string $level, string $message, array $context = []): void {
        ($this->callable)($level, $message, $context);
    }
}
