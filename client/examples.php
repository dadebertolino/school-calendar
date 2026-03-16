<?php
/**
 * School Calendar Client - Esempi di utilizzo
 */

require_once __DIR__ . '/SchoolCalendarClientAdvanced.php';

use SchoolCalendar\Client;
use SchoolCalendar\FileCache;
use SchoolCalendar\RedisCache;
use SchoolCalendar\FileLogger;

// =========================================================================
// CONFIGURAZIONE BASE
// =========================================================================

$client = new Client(
    'https://scuola.example.it/wp-json/school-calendar/v1',
    'your_api_key_here'
);

// =========================================================================
// CONFIGURAZIONE CON CACHE E LOGGING
// =========================================================================

// Cache su file
$cache = new FileCache('/tmp/school-calendar-cache');

// Oppure cache Redis
// $redis = new Redis();
// $redis->connect('127.0.0.1', 6379);
// $cache = new RedisCache($redis);

// Logger su file
$logger = new FileLogger('/var/log/school-calendar.log');

$client = new Client(
    'https://scuola.example.it/wp-json/school-calendar/v1',
    'your_api_key_here',
    [
        'cache' => $cache,
        'logger' => $logger,
        'timeout' => 30,
        'maxRetries' => 3,
        'cacheTtl' => [
            'eventi' => 180,  // 3 minuti per eventi (più frequente)
            'plessi' => 7200, // 2 ore per plessi (cambiano raramente)
        ],
    ]
);

// =========================================================================
// ESEMPI: RECUPERO EVENTI
// =========================================================================

// Eventi di oggi
$eventiOggi = $client->getEventiOggi();
echo "Eventi oggi: " . count($eventiOggi ?? []) . "\n";

// Eventi di domani per un plesso specifico
$eventiDomani = $client->getEventiDomani(plessoId: 1);

// Eventi della settimana per una classe specifica
$eventiSettimana = $client->getEventiSettimana(plessoId: 1, classeId: 5);

// Prossimi 10 eventi
$prossimiEventi = $client->getProssimiEventi(10);

// Eventi di un mese specifico
$eventiFebbraio = $client->getEventiByMese(2025, 2);

// Eventi con filtri multipli
$eventi = $client->getEventi([
    'start' => '2025-01-01',
    'end' => '2025-06-30',
    'plesso_id' => 1,
    'source' => 'local,google', // Solo eventi locali e da Google
]);

// =========================================================================
// ESEMPI: CALENDARIO MENSILE
// =========================================================================

// Genera struttura calendario per febbraio 2025
$calendario = $client->getCalendarioMensile(2025, 2, plessoId: 1);

echo "Calendario {$calendario['nome_mese']} {$calendario['anno']}\n\n";

echo "Lu  Ma  Me  Gi  Ve  Sa  Do\n";
foreach ($calendario['settimane'] as $settimana) {
    foreach ($settimana as $giorno) {
        if ($giorno === null) {
            echo "    ";
        } else {
            $badge = count($giorno['eventi']) > 0 ? '*' : ' ';
            printf("%2d%s ", $giorno['giorno'], $badge);
        }
    }
    echo "\n";
}

// Dettaglio giorni con eventi
foreach ($calendario['giorni'] as $giorno) {
    if (!empty($giorno['eventi'])) {
        echo "\n{$giorno['data']}:\n";
        foreach ($giorno['eventi'] as $evento) {
            $ora = substr($evento['data_inizio'], 11, 5);
            echo "  - [{$ora}] {$evento['titolo']}\n";
        }
    }
}

// =========================================================================
// ESEMPI: RAGGRUPPAMENTI
// =========================================================================

// Eventi raggruppati per giorno
$eventiPerGiorno = $client->getEventiGroupedByDay('2025-02-01', '2025-02-28');

foreach ($eventiPerGiorno as $data => $eventi) {
    echo "{$data}: " . count($eventi) . " eventi\n";
}

// Eventi raggruppati per plesso
$eventiPerPlesso = $client->getEventiGroupedByPlesso('2025-02-01', '2025-02-28');
$plessiMap = $client->getPlessiMap();

foreach ($eventiPerPlesso as $plessoId => $eventi) {
    $nomePlesso = $plessoId === 'tutti' ? 'Tutti i plessi' : ($plessiMap[$plessoId] ?? "Plesso {$plessoId}");
    echo "{$nomePlesso}: " . count($eventi) . " eventi\n";
}

// =========================================================================
// ESEMPI: CREAZIONE EVENTI
// =========================================================================

// Crea singolo evento
$nuovoEvento = $client->createEvento([
    'titolo' => 'Riunione genitori 3A',
    'descrizione' => 'Incontro con i genitori per discutere andamento quadrimestre',
    'data_inizio' => '2025-02-20 17:00:00',
    'data_fine' => '2025-02-20 18:30:00',
    'visibilita' => 'privato',
    'plesso_id' => 1,
    'classe_ids' => [3], // Classe 3A
]);

if ($nuovoEvento) {
    echo "Evento creato con ID: {$nuovoEvento['evento']['id']}\n";
} else {
    echo "Errore: " . $client->getLastError()['message'] . "\n";
}

// Crea eventi in batch
$eventiDaCreare = [
    [
        'titolo' => 'Consiglio di classe 1A',
        'data_inizio' => '2025-02-21 14:00:00',
        'data_fine' => '2025-02-21 15:00:00',
        'visibilita' => 'privato',
        'plesso_id' => 1,
        'classe_ids' => [1],
    ],
    [
        'titolo' => 'Consiglio di classe 1B',
        'data_inizio' => '2025-02-21 15:00:00',
        'data_fine' => '2025-02-21 16:00:00',
        'visibilita' => 'privato',
        'plesso_id' => 1,
        'classe_ids' => [2],
    ],
    [
        'titolo' => 'Consiglio di classe 2A',
        'data_inizio' => '2025-02-21 16:00:00',
        'data_fine' => '2025-02-21 17:00:00',
        'visibilita' => 'privato',
        'plesso_id' => 1,
        'classe_ids' => [4],
    ],
];

$results = $client->createEventiBatch($eventiDaCreare);

echo "Creati: " . count($results['success']) . "\n";
echo "Falliti: " . count($results['failed']) . "\n";

foreach ($results['failed'] as $failed) {
    echo "  Errore indice {$failed['index']}: {$failed['error']['message']}\n";
}

// =========================================================================
// ESEMPI: AGGIORNAMENTO E ELIMINAZIONE
// =========================================================================

// Aggiorna evento
$updated = $client->updateEvento(123, [
    'titolo' => 'Riunione genitori 3A - RINVIATA',
    'data_inizio' => '2025-02-27 17:00:00',
    'data_fine' => '2025-02-27 18:30:00',
]);

// Elimina evento
$deleted = $client->deleteEvento(123);
echo $deleted ? "Eliminato\n" : "Errore eliminazione\n";

// =========================================================================
// ESEMPI: PLESSI E CLASSI
// =========================================================================

// Lista plessi con classi
$plessi = $client->getPlessi(includeClassi: true);

foreach ($plessi as $plesso) {
    echo "\n{$plesso['nome']} ({$plesso['codice']})\n";
    
    if (!empty($plesso['classi'])) {
        foreach ($plesso['classi'] as $classe) {
            echo "  - {$classe['nome']}\n";
        }
    }
}

// Mappa rapida per lookup
$plessiMap = $client->getPlessiMap();  // [1 => 'Plesso Centro', 2 => 'Plesso Nord', ...]
$classiMap = $client->getClassiMap(1); // [1 => '1A', 2 => '1B', ...] per plesso 1

// =========================================================================
// ESEMPI: GESTIONE ERRORI
// =========================================================================

$evento = $client->getEvento(99999);

if ($evento === null) {
    $error = $client->getLastError();
    
    switch ($error['code']) {
        case 404:
            echo "Evento non trovato\n";
            break;
        case 401:
            echo "Non autorizzato - verifica API Key\n";
            break;
        case 403:
            echo "Accesso negato\n";
            break;
        default:
            echo "Errore: {$error['message']} (code: {$error['code']})\n";
    }
}

// =========================================================================
// ESEMPI: CACHE
// =========================================================================

// Svuota tutta la cache
$client->clearCache();

// La cache viene automaticamente invalidata dopo operazioni di scrittura
// (createEvento, updateEvento, deleteEvento)

// =========================================================================
// ESEMPI: SINCRONIZZAZIONE CALENDARI GOOGLE
// =========================================================================

// Forza sync di un calendario specifico
$syncResult = $client->syncCalendarioEsterno(1);

// Sincronizza tutti i calendari esterni
$allSyncResults = $client->syncAllCalendari();

foreach ($allSyncResults as $calId => $result) {
    $status = $result ? 'OK' : 'ERRORE';
    echo "Calendario {$calId}: {$status}\n";
}

// =========================================================================
// ESEMPIO: INTEGRAZIONE IN UNA PAGINA WEB
// =========================================================================

function renderCalendarioWidget(Client $client, ?int $plessoId = null): string {
    $eventi = $client->getProssimiEventi(5, $plessoId);
    
    if (empty($eventi)) {
        return '<div class="calendar-widget"><p>Nessun evento in programma</p></div>';
    }
    
    $html = '<div class="calendar-widget">';
    $html .= '<h3>Prossimi Eventi</h3>';
    $html .= '<ul>';
    
    foreach ($eventi as $evento) {
        $data = date('d/m', strtotime($evento['data_inizio']));
        $ora = date('H:i', strtotime($evento['data_inizio']));
        
        $html .= "<li>";
        $html .= "<span class='date'>{$data}</span> ";
        $html .= "<span class='time'>{$ora}</span> ";
        $html .= "<span class='title'>" . htmlspecialchars($evento['titolo']) . "</span>";
        $html .= "</li>";
    }
    
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}

// Uso:
// echo renderCalendarioWidget($client, plessoId: 1);

// =========================================================================
// ESEMPIO: EXPORT ICAL
// =========================================================================

function exportToIcal(Client $client, string $start, string $end, ?int $plessoId = null): string {
    $eventi = $client->getEventiRange($start, $end, $plessoId);
    
    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//School Calendar//IT\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "METHOD:PUBLISH\r\n";
    
    foreach ($eventi ?? [] as $evento) {
        $dtstart = date('Ymd\THis', strtotime($evento['data_inizio']));
        $dtend = date('Ymd\THis', strtotime($evento['data_fine']));
        $uid = "sc-{$evento['id']}@school-calendar";
        
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:{$uid}\r\n";
        $ical .= "DTSTART:{$dtstart}\r\n";
        $ical .= "DTEND:{$dtend}\r\n";
        $ical .= "SUMMARY:" . $this->escapeIcal($evento['titolo']) . "\r\n";
        
        if (!empty($evento['descrizione'])) {
            $ical .= "DESCRIPTION:" . $this->escapeIcal($evento['descrizione']) . "\r\n";
        }
        
        $ical .= "END:VEVENT\r\n";
    }
    
    $ical .= "END:VCALENDAR\r\n";
    
    return $ical;
}

function escapeIcal(string $text): string {
    return str_replace(
        ["\r\n", "\n", "\r", ",", ";", "\\"],
        ["\\n", "\\n", "\\n", "\\,", "\\;", "\\\\"],
        $text
    );
}

// Uso:
// header('Content-Type: text/calendar; charset=utf-8');
// header('Content-Disposition: attachment; filename="calendario.ics"');
// echo exportToIcal($client, '2025-01-01', '2025-12-31', 1);
