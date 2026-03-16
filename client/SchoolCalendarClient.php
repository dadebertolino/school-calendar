<?php
/**
 * School Calendar API Client
 * 
 * Classe standalone per integrare il calendario in progetti PHP esterni.
 * 
 * Utilizzo:
 *   $client = new SchoolCalendarClient('https://sito.it/wp-json/school-calendar/v1', 'api_key');
 *   $eventi = $client->getEventi(['start' => '2025-01-01', 'end' => '2025-01-31']);
 */

class SchoolCalendarClient {
    
    private string $baseUrl;
    private string $apiKey;
    private int $timeout = 30;
    private array $lastError = [];
    
    /**
     * @param string $baseUrl URL base dell'API (es. https://sito.it/wp-json/school-calendar/v1)
     * @param string $apiKey  API Key per autenticazione
     */
    public function __construct(string $baseUrl, string $apiKey) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }
    
    /**
     * Imposta timeout richieste
     */
    public function setTimeout(int $seconds): self {
        $this->timeout = $seconds;
        return $this;
    }
    
    /**
     * Recupera ultimo errore
     */
    public function getLastError(): array {
        return $this->lastError;
    }
    
    // =========================================================================
    // EVENTI
    // =========================================================================
    
    /**
     * Lista eventi con filtri
     * 
     * @param array $params Filtri disponibili:
     *   - start: Data inizio (YYYY-MM-DD o YYYY-MM-DD HH:ii:ss)
     *   - end: Data fine
     *   - plesso_id: Filtra per plesso
     *   - classe_id: Filtra per classe
     *   - source: Filtra per origine (local, google, ical)
     *   - format: 'default' o 'fullcalendar'
     *   - limit: Numero max risultati
     *   - offset: Offset per paginazione
     */
    public function getEventi(array $params = []): ?array {
        return $this->get('/eventi', $params);
    }
    
    /**
     * Recupera singolo evento
     */
    public function getEvento(int $id): ?array {
        return $this->get("/eventi/{$id}");
    }
    
    /**
     * Crea nuovo evento
     * 
     * @param array $data Dati evento:
     *   - titolo: (obbligatorio)
     *   - descrizione: 
     *   - data_inizio: (obbligatorio) YYYY-MM-DD HH:ii:ss
     *   - data_fine: (obbligatorio) YYYY-MM-DD HH:ii:ss
     *   - tutto_giorno: bool
     *   - visibilita: 'pubblico' o 'privato'
     *   - plesso_id: int o null per tutti
     *   - classe_ids: array di ID classi
     */
    public function createEvento(array $data): ?array {
        return $this->post('/eventi', $data);
    }
    
    /**
     * Aggiorna evento esistente
     */
    public function updateEvento(int $id, array $data): ?array {
        return $this->put("/eventi/{$id}", $data);
    }
    
    /**
     * Elimina evento
     */
    public function deleteEvento(int $id): bool {
        $result = $this->delete("/eventi/{$id}");
        return $result !== null && ($result['success'] ?? false);
    }
    
    // =========================================================================
    // PLESSI
    // =========================================================================
    
    /**
     * Lista plessi
     * 
     * @param bool $includeClassi Include classi nel risultato
     */
    public function getPlessi(bool $includeClassi = false): ?array {
        $params = $includeClassi ? ['include_classi' => 'true'] : [];
        return $this->get('/plessi', $params);
    }
    
    /**
     * Dettaglio plesso
     */
    public function getPlesso(int $id): ?array {
        return $this->get("/plessi/{$id}");
    }
    
    // =========================================================================
    // CLASSI
    // =========================================================================
    
    /**
     * Lista classi
     * 
     * @param int|null $plessoId Filtra per plesso
     * @param string|null $annoScolastico Filtra per anno (es. "2024-2025")
     */
    public function getClassi(?int $plessoId = null, ?string $annoScolastico = null): ?array {
        $params = [];
        if ($plessoId) $params['plesso_id'] = $plessoId;
        if ($annoScolastico) $params['anno_scolastico'] = $annoScolastico;
        
        return $this->get('/classi', $params);
    }
    
    // =========================================================================
    // CALENDARI ESTERNI (richiede permessi admin)
    // =========================================================================
    
    /**
     * Lista calendari esterni configurati
     */
    public function getCalendariEsterni(): ?array {
        return $this->get('/calendari-esterni');
    }
    
    /**
     * Forza sincronizzazione calendario esterno
     */
    public function syncCalendarioEsterno(int $id): ?array {
        return $this->post("/calendari-esterni/{$id}/sync");
    }
    
    // =========================================================================
    // HELPER METHODS
    // =========================================================================
    
    /**
     * Recupera eventi per il mese corrente
     */
    public function getEventiMeseCorrente(?int $plessoId = null): ?array {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        
        $params = ['start' => $start, 'end' => $end];
        if ($plessoId) $params['plesso_id'] = $plessoId;
        
        return $this->getEventi($params);
    }
    
    /**
     * Recupera eventi per settimana corrente
     */
    public function getEventiSettimanaCorrente(?int $plessoId = null): ?array {
        $start = date('Y-m-d', strtotime('monday this week'));
        $end = date('Y-m-d', strtotime('sunday this week'));
        
        $params = ['start' => $start, 'end' => $end];
        if ($plessoId) $params['plesso_id'] = $plessoId;
        
        return $this->getEventi($params);
    }
    
    /**
     * Recupera prossimi N eventi
     */
    public function getProssimiEventi(int $limit = 10, ?int $plessoId = null): ?array {
        $params = [
            'start' => date('Y-m-d'),
            'limit' => $limit,
        ];
        if ($plessoId) $params['plesso_id'] = $plessoId;
        
        return $this->getEventi($params);
    }
    
    // =========================================================================
    // HTTP METHODS
    // =========================================================================
    
    private function get(string $endpoint, array $params = []): ?array {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
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
        
        $headers = [
            'X-SC-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ];
        
        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $this->lastError = [
                'code' => 0,
                'message' => 'Errore di connessione',
            ];
            return null;
        }
        
        // Estrai status code
        $statusCode = 200;
        if (!empty($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                    $statusCode = (int) $matches[1];
                }
            }
        }
        
        $decoded = json_decode($response, true);
        
        if ($statusCode >= 400) {
            $this->lastError = [
                'code' => $statusCode,
                'message' => $decoded['message'] ?? 'Errore sconosciuto',
                'data' => $decoded,
            ];
            return null;
        }
        
        return $decoded;
    }
}

// =========================================================================
// ESEMPIO DI UTILIZZO CON CURL (alternativa)
// =========================================================================

class SchoolCalendarClientCurl extends SchoolCalendarClient {
    
    private function request(string $method, string $url, ?array $data = null): ?array {
        $this->lastError = [];
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'X-SC-API-Key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
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
        
        curl_close($ch);
        
        if ($error) {
            $this->lastError = [
                'code' => 0,
                'message' => 'cURL error: ' . $error,
            ];
            return null;
        }
        
        $decoded = json_decode($response, true);
        
        if ($statusCode >= 400) {
            $this->lastError = [
                'code' => $statusCode,
                'message' => $decoded['message'] ?? 'Errore sconosciuto',
                'data' => $decoded,
            ];
            return null;
        }
        
        return $decoded;
    }
}
