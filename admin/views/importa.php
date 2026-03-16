<?php
defined('ABSPATH') || exit;

use SchoolCalendar\Models\Plesso;
use SchoolCalendar\Models\SubCalendario;
use SchoolCalendar\Models\Evento;

$plessi = Plesso::all();
$sub_calendari = SubCalendario::attivi();

$import_result = null;
$imported_count = 0;
$deleted_count = 0;
$errors = [];

// Gestisci upload e importazione
if (isset($_POST['sc_import_ics']) && wp_verify_nonce($_POST['sc_import_nonce'], 'sc_import_ics')) {
    
    if (!empty($_FILES['ics_file']['tmp_name'])) {
        $plesso_id = !empty($_POST['plesso_id']) ? intval($_POST['plesso_id']) : null;
        $sub_calendario_ids = !empty($_POST['sub_calendario_ids']) ? array_map('intval', $_POST['sub_calendario_ids']) : [];
        $visibilita = $_POST['visibilita'] ?? 'pubblico';
        $pulisci_prima = !empty($_POST['pulisci_prima']);
        
        // Se richiesto, elimina eventi esistenti dei sub-calendari selezionati
        if ($pulisci_prima && !empty($sub_calendario_ids)) {
            global $wpdb;
            $prefix = $wpdb->prefix . SC_TABLE_PREFIX;
            
            // Trova eventi associati ai sub-calendari selezionati
            $placeholders = implode(',', array_fill(0, count($sub_calendario_ids), '%d'));
            $sql = $wpdb->prepare(
                "SELECT DISTINCT evento_id FROM {$prefix}eventi_sub_calendari WHERE sub_calendario_id IN ($placeholders)",
                $sub_calendario_ids
            );
            $evento_ids = $wpdb->get_col($sql);
            
            if (!empty($evento_ids)) {
                foreach ($evento_ids as $evento_id) {
                    $evento = Evento::find($evento_id);
                    if ($evento && $evento->source === 'local') {
                        $evento->delete();
                        $deleted_count++;
                    }
                }
            }
        }
        
        $ics_content = file_get_contents($_FILES['ics_file']['tmp_name']);
        
        // Parse ICS
        $events = parse_ics_content($ics_content);
        
        foreach ($events as $event_data) {
            try {
                $evento = new Evento([
                    'titolo' => $event_data['summary'] ?? 'Evento importato',
                    'descrizione' => $event_data['description'] ?? '',
                    'data_inizio' => $event_data['dtstart'],
                    'data_fine' => $event_data['dtend'],
                    'tutto_giorno' => $event_data['all_day'] ? 1 : 0,
                    'plesso_id' => $plesso_id,
                    'luogo_scuola' => $event_data['location'] ?? '',
                    'responsabile' => $event_data['attendee'] ?? '',
                    'visibilita' => $visibilita,
                    'source' => 'local',
                    'autore_id' => get_current_user_id(),
                ]);
                
                if ($evento->save()) {
                    // Associa sub-calendari
                    if (!empty($sub_calendario_ids)) {
                        $evento->syncSubCalendari($sub_calendario_ids);
                    }
                    $imported_count++;
                } else {
                    $errors[] = "Errore salvataggio: " . ($event_data['summary'] ?? 'evento');
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        
        $import_result = true;
    } else {
        $errors[] = "Nessun file selezionato";
    }
}

/**
 * Parse contenuto ICS e restituisce array di eventi
 */
function parse_ics_content($content) {
    $events = [];
    
    // Normalizza line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // Unfold lines (le righe che iniziano con spazio sono continuazione)
    $content = preg_replace("/\n[ \t]/", "", $content);
    
    // Trova tutti i VEVENT
    preg_match_all('/BEGIN:VEVENT(.+?)END:VEVENT/s', $content, $matches);
    
    foreach ($matches[1] as $vevent) {
        $event = [];
        
        // SUMMARY (titolo)
        if (preg_match('/SUMMARY[^:]*:(.+)/i', $vevent, $m)) {
            $event['summary'] = decode_ics_value(trim($m[1]));
        }
        
        // DESCRIPTION
        if (preg_match('/DESCRIPTION[^:]*:(.+)/i', $vevent, $m)) {
            $event['description'] = decode_ics_value(trim($m[1]));
        }
        
        // LOCATION
        if (preg_match('/LOCATION[^:]*:(.+)/i', $vevent, $m)) {
            $event['location'] = decode_ics_value(trim($m[1]));
        }
        
        // ATTENDEE (responsabile)
        if (preg_match('/ATTENDEE[^:]*:(.+)/i', $vevent, $m)) {
            $attendee = trim($m[1]);
            // Rimuovi mailto: se presente
            $attendee = preg_replace('/^mailto:/i', '', $attendee);
            $event['attendee'] = decode_ics_value($attendee);
        }
        
        // DTSTART
        if (preg_match('/DTSTART[^:]*:(\d{8})(T(\d{6})Z?)?/i', $vevent, $m)) {
            $date = $m[1];
            $time = isset($m[3]) ? $m[3] : null;
            $event['dtstart'] = format_ics_datetime($date, $time);
            $event['all_day'] = empty($time);
        }
        
        // DTEND
        if (preg_match('/DTEND[^:]*:(\d{8})(T(\d{6})Z?)?/i', $vevent, $m)) {
            $date = $m[1];
            $time = isset($m[3]) ? $m[3] : null;
            $event['dtend'] = format_ics_datetime($date, $time);
        }
        
        // Se manca DTEND, usa DTSTART + 1 ora
        if (empty($event['dtend']) && !empty($event['dtstart'])) {
            $dt = new DateTime($event['dtstart']);
            $dt->modify('+1 hour');
            $event['dtend'] = $dt->format('Y-m-d H:i:s');
        }
        
        if (!empty($event['dtstart'])) {
            $events[] = $event;
        }
    }
    
    return $events;
}

/**
 * Decodifica valori ICS (escaped chars)
 */
function decode_ics_value($value) {
    $value = str_replace(['\\n', '\\N'], "\n", $value);
    $value = str_replace(['\\,', '\\;', '\\\\'], [',', ';', '\\'], $value);
    return $value;
}

/**
 * Formatta datetime ICS in MySQL format
 */
function format_ics_datetime($date, $time = null) {
    $year = substr($date, 0, 4);
    $month = substr($date, 4, 2);
    $day = substr($date, 6, 2);
    
    if ($time) {
        $hour = substr($time, 0, 2);
        $min = substr($time, 2, 2);
        $sec = substr($time, 4, 2);
        
        // Converti da UTC a timezone locale
        $dt = new DateTime("{$year}-{$month}-{$day} {$hour}:{$min}:{$sec}", new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(wp_timezone_string()));
        
        return $dt->format('Y-m-d H:i:s');
    }
    
    return "{$year}-{$month}-{$day} 00:00:00";
}
?>

<div class="wrap">
    <h1>Importa Eventi da ICS</h1>
    <p class="description">Carica un file .ics per importare eventi nel calendario scolastico.</p>
    
    <?php if ($import_result): ?>
        <div class="notice notice-success">
            <p><strong>
                <?php if ($deleted_count > 0): ?>
                    <?php echo $deleted_count; ?> eventi eliminati, 
                <?php endif; ?>
                <?php echo $imported_count; ?> eventi importati con successo!
            </strong></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="notice notice-error">
            <p><strong>Errori durante l'importazione:</strong></p>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data" style="max-width: 600px; margin-top: 20px;">
        <?php wp_nonce_field('sc_import_ics', 'sc_import_nonce'); ?>
        
        <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            
            <table class="form-table">
                <tr>
                    <th><label for="ics_file">File ICS *</label></th>
                    <td>
                        <input type="file" name="ics_file" id="ics_file" accept=".ics" required>
                        <p class="description">Seleziona un file .ics da importare</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="plesso_id">Plesso</label></th>
                    <td>
                        <select name="plesso_id" id="plesso_id">
                            <option value="">-- Nessun plesso --</option>
                            <?php foreach ($plessi as $plesso): ?>
                                <option value="<?php echo $plesso->id; ?>">
                                    <?php echo esc_html($plesso->descrizione); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th><label>Sub-calendari</label></th>
                    <td>
                        <?php if (empty($sub_calendari)): ?>
                            <p class="description">Nessun sub-calendario disponibile</p>
                        <?php else: ?>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                <?php foreach ($sub_calendari as $sc): ?>
                                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                        <input type="checkbox" name="sub_calendario_ids[]" value="<?php echo $sc->id; ?>" data-plesso="<?php echo $sc->plesso_id; ?>">
                                        <span style="width: 16px; height: 16px; border-radius: 3px; background: <?php echo esc_attr($sc->colore); ?>;"></span>
                                        <?php echo esc_html($sc->nome); ?>
                                        <small style="color: #888;">(<?php 
                                            $p = Plesso::find($sc->plesso_id);
                                            echo $p ? esc_html($p->descrizione) : '';
                                        ?>)</small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">Seleziona le categorie da assegnare agli eventi importati</p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="visibilita">Visibilità</label></th>
                    <td>
                        <select name="visibilita" id="visibilita">
                            <option value="pubblico">Pubblico</option>
                            <option value="privato">Riservato (solo utenti registrati)</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th>Opzioni</th>
                    <td>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="pulisci_prima" value="1">
                            <strong style="color: #d63638;">🗑️ Pulisci e reimporta</strong>
                        </label>
                        <p class="description" style="color: #d63638;">
                            ⚠️ Se attivo, <strong>elimina tutti gli eventi esistenti</strong> delle categorie selezionate prima di importare i nuovi.
                            Utile per reimportare un calendario aggiornato senza creare duplicati.
                        </p>
                    </td>
                </tr>
            </table>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                <h4 style="margin-top: 0;">Campi importati dal file ICS:</h4>
                <ul style="margin-left: 20px; color: #666;">
                    <li><strong>SUMMARY</strong> → Titolo evento</li>
                    <li><strong>DESCRIPTION</strong> → Descrizione</li>
                    <li><strong>LOCATION</strong> → Luogo (scuola)</li>
                    <li><strong>ATTENDEE</strong> → Responsabile</li>
                    <li><strong>DTSTART/DTEND</strong> → Date e orari</li>
                </ul>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="sc_import_ics" class="button button-primary button-large" value="Importa Eventi">
        </p>
    </form>
</div>

<script>
// Filtra sub-calendari quando cambia plesso
document.getElementById('plesso_id').addEventListener('change', function() {
    var plessoId = this.value;
    var checkboxes = document.querySelectorAll('input[name="sub_calendario_ids[]"]');
    
    checkboxes.forEach(function(cb) {
        var label = cb.closest('label');
        if (!plessoId || cb.dataset.plesso === plessoId) {
            label.style.display = '';
        } else {
            label.style.display = 'none';
            cb.checked = false;
        }
    });
});
</script>
