<?php
defined('ABSPATH') || exit;

use SchoolCalendar\Models\Evento;
use SchoolCalendar\Models\Plesso;
use SchoolCalendar\Models\Classe;
use SchoolCalendar\Models\SubCalendario;

// Filtri
$filter_plesso = isset($_GET['plesso_id']) ? (int)$_GET['plesso_id'] : '';
$filter_visibilita = isset($_GET['visibilita']) ? sanitize_text_field($_GET['visibilita']) : '';
$filter_mese = isset($_GET['mese']) ? sanitize_text_field($_GET['mese']) : date('Y-m');

// Query eventi
$params = [];
if ($filter_mese && $filter_mese !== 'all') {
    $params['start'] = $filter_mese . '-01';
    $params['end'] = date('Y-m-t', strtotime($params['start']));
}
if ($filter_plesso) {
    $params['plesso_id'] = $filter_plesso;
}

$eventi = Evento::filter($params, true);

// Filtra per visibilità (post-query)
if ($filter_visibilita) {
    $eventi = array_filter($eventi, fn($e) => $e->visibilita === $filter_visibilita);
}

$plessi = Plesso::attivi();
$classi = Classe::all();
$sub_calendari = SubCalendario::attivi();

// Raggruppa classi per plesso
$classi_per_plesso = [];
foreach ($classi as $classe) {
    $classi_per_plesso[$classe->plesso][] = $classe;
}

// Raggruppa sub-calendari per plesso
$sub_calendari_per_plesso = [];
foreach ($sub_calendari as $sc) {
    $sub_calendari_per_plesso[$sc->plesso_id][] = $sc;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline">Gestione Eventi</h1>
    <a href="#" class="page-title-action" id="sc-add-evento-btn">Aggiungi Nuovo</a>
    <hr class="wp-header-end">
    
    <!-- Filtri -->
    <div class="sc-filters-bar">
        <form method="get" action="">
            <input type="hidden" name="page" value="school-calendar-eventi">
            
            <select name="mese">
                <option value="all" <?php selected($filter_mese, 'all'); ?>>Tutti gli eventi</option>
                <?php 
                // Usa il primo giorno del mese corrente come base
                $base_date = date('Y-m-01');
                for ($i = -3; $i <= 6; $i++):
                    $m = date('Y-m', strtotime("$base_date $i months"));
                    $label = date_i18n('F Y', strtotime($m . '-01'));
                ?>
                    <option value="<?php echo $m; ?>" <?php selected($filter_mese, $m); ?>><?php echo $label; ?></option>
                <?php endfor; ?>
            </select>
            
            <select name="plesso_id">
                <option value="">Tutti i plessi</option>
                <?php foreach ($plessi as $plesso): ?>
                    <option value="<?php echo $plesso->id; ?>" <?php selected($filter_plesso, $plesso->id); ?>>
                        <?php echo esc_html($plesso->descrizione); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="visibilita">
                <option value="">Tutte le visibilità</option>
                <option value="pubblico" <?php selected($filter_visibilita, 'pubblico'); ?>>Pubblico</option>
                <option value="privato" <?php selected($filter_visibilita, 'privato'); ?>>Privato</option>
            </select>
            
            <button type="submit" class="button">Filtra</button>
        </form>
    </div>
    
    <!-- Tabella Eventi -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th>Titolo</th>
                <th style="width:150px;">Data/Ora</th>
                <th style="width:120px;">Plesso</th>
                <th style="width:100px;">Visibilità</th>
                <th style="width:80px;">Origine</th>
                <th style="width:120px;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($eventi)): ?>
                <tr>
                    <td colspan="7">Nessun evento trovato per i filtri selezionati.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($eventi as $evento): ?>
                    <?php $plesso = $evento->plesso(); ?>
                    <tr data-id="<?php echo $evento->id; ?>">
                        <td><?php echo $evento->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($evento->titolo); ?></strong>
                            <?php if ($evento->descrizione): ?>
                                <br><small class="description"><?php echo esc_html(wp_trim_words($evento->descrizione, 10)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            echo date_i18n('d/m/Y', strtotime($evento->data_inizio));
                            if (!$evento->tutto_giorno) {
                                echo '<br><small>' . date('H:i', strtotime($evento->data_inizio));
                                echo ' - ' . date('H:i', strtotime($evento->data_fine)) . '</small>';
                            } else {
                                echo '<br><small>Tutto il giorno</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo $plesso ? esc_html($plesso->descrizione) : '<em>Tutti</em>'; ?>
                        </td>
                        <td>
                            <span class="sc-badge sc-badge-<?php echo $evento->visibilita; ?>">
                                <?php echo $evento->visibilita === 'pubblico' ? 'Pubblico' : 'Privato'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="sc-badge sc-badge-<?php echo $evento->source; ?>">
                                <?php echo ucfirst($evento->source); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($evento->source === 'local'): ?>
                                <button class="button button-small sc-edit-evento" data-id="<?php echo $evento->id; ?>">Modifica</button>
                                <button class="button button-small sc-delete-evento" data-id="<?php echo $evento->id; ?>">Elimina</button>
                            <?php else: ?>
                                <em>Non modificabile</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Modal Form Evento -->
    <div id="sc-evento-modal" class="sc-modal" style="display:none;">
        <div class="sc-modal-overlay"></div>
        <div class="sc-modal-content">
            <button class="sc-modal-close">&times;</button>
            <h2 id="sc-modal-title">Nuovo Evento</h2>
            
            <form id="sc-evento-form">
                <input type="hidden" id="evento-id" value="">
                
                <table class="form-table">
                    <tr>
                        <th><label for="evento-titolo">Titolo *</label></th>
                        <td><input type="text" id="evento-titolo" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="evento-descrizione">Descrizione</label></th>
                        <td><textarea id="evento-descrizione" rows="3" class="large-text"></textarea></td>
                    </tr>
                    <tr>
                        <th><label>Data/Ora Inizio *</label></th>
                        <td>
                            <input type="date" id="evento-data-inizio" required>
                            <input type="time" id="evento-ora-inizio" value="08:00">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Data/Ora Fine *</label></th>
                        <td>
                            <input type="date" id="evento-data-fine" required>
                            <input type="time" id="evento-ora-fine" value="09:00">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="evento-tutto-giorno">Tutto il giorno</label></th>
                        <td>
                            <input type="checkbox" id="evento-tutto-giorno">
                            <label for="evento-tutto-giorno">Sì, è un evento che dura tutto il giorno</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="evento-plesso">Plesso</label></th>
                        <td>
                            <select id="evento-plesso">
                                <option value="">Tutti i plessi</option>
                                <?php foreach ($plessi as $plesso): ?>
                                    <option value="<?php echo $plesso->id; ?>"><?php echo esc_html($plesso->descrizione); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="evento-classi">Classi</label></th>
                        <td>
                            <select id="evento-classi" multiple style="height:120px;width:100%;">
                                <?php foreach ($plessi as $plesso): ?>
                                    <optgroup label="<?php echo esc_attr($plesso->descrizione); ?>" data-plesso="<?php echo $plesso->id; ?>">
                                        <?php if (isset($classi_per_plesso[$plesso->id])): ?>
                                            <?php foreach ($classi_per_plesso[$plesso->id] as $classe): ?>
                                                <option value="<?php echo $classe->id; ?>"><?php echo esc_html($classe->descrizione); ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Tieni premuto Ctrl per selezionare più classi. Lascia vuoto per tutte.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="evento-sub-calendari">Sub-calendari</label></th>
                        <td>
                            <select id="evento-sub-calendari" multiple style="height:100px;width:100%;">
                                <?php foreach ($plessi as $plesso): ?>
                                    <optgroup label="<?php echo esc_attr($plesso->descrizione); ?>" data-plesso="<?php echo $plesso->id; ?>">
                                        <?php if (isset($sub_calendari_per_plesso[$plesso->id])): ?>
                                            <?php foreach ($sub_calendari_per_plesso[$plesso->id] as $sc): ?>
                                                <option value="<?php echo $sc->id; ?>" data-colore="<?php echo esc_attr($sc->colore); ?>">
                                                    <?php echo esc_html($sc->nome); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Categorie a cui appartiene l'evento. Il colore sarà quello del primo selezionato.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="evento-responsabile">Responsabile</label></th>
                        <td>
                            <input type="text" id="evento-responsabile" style="width:100%;" placeholder="es. Prof. Rossi">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="evento-luogo-scuola">Luogo (scuola)</label></th>
                        <td>
                            <input type="text" id="evento-luogo-scuola" style="width:100%;" placeholder="es. Aula Magna, Lab Info 3...">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="evento-luogo-fisico">Luogo (esterno)</label></th>
                        <td>
                            <input type="text" id="evento-luogo-fisico" style="width:100%;" placeholder="es. Via Roma 1, Fossano">
                            <p class="description">Indirizzo esterno alla scuola (sarà linkato a Google Maps)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="evento-visibilita">Visibilità *</label></th>
                        <td>
                            <select id="evento-visibilita" required>
                                <option value="pubblico">Pubblico (visibile a tutti)</option>
                                <option value="privato" selected>Privato (solo utenti registrati)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="evento-schermo">Display</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="evento-schermo" value="1">
                                Mostra su schermi/display informativi
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Salva Evento</button>
                    <button type="button" class="button sc-modal-cancel">Annulla</button>
                </p>
            </form>
        </div>
    </div>
</div>

<style>
.sc-filters-bar {
    background: #fff;
    padding: 15px;
    margin: 15px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}
.sc-filters-bar select {
    margin-right: 10px;
}
.sc-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}
.sc-badge-pubblico { background: #d4edda; color: #155724; }
.sc-badge-privato { background: #e2d5f1; color: #5f4b8b; }
.sc-badge-local { background: #cce5ff; color: #004085; }
.sc-badge-google { background: #d4edda; color: #155724; }
.sc-badge-ical { background: #fff3cd; color: #856404; }

.sc-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sc-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
}
.sc-modal-content {
    position: relative;
    background: #fff;
    padding: 20px 25px;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 5px 30px rgba(0,0,0,0.3);
}
.sc-modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}
.sc-modal-close:hover {
    color: #000;
}
.sc-modal h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
</style>

<script>
jQuery(function($) {
    var modal = $('#sc-evento-modal');
    var form = $('#sc-evento-form');
    
    // Apri modal nuovo evento
    $('#sc-add-evento-btn').on('click', function(e) {
        e.preventDefault();
        resetForm();
        $('#sc-modal-title').text('Nuovo Evento');
        modal.show();
    });
    
    // Apri modal modifica
    $('.sc-edit-evento').on('click', function() {
        var id = $(this).data('id');
        resetForm();
        $('#sc-modal-title').text('Modifica Evento');
        loadEvento(id);
        modal.show();
    });
    
    // Chiudi modal
    $('.sc-modal-overlay, .sc-modal-close, .sc-modal-cancel').on('click', function() {
        modal.hide();
    });
    
    // Toggle orari con tutto il giorno
    $('#evento-tutto-giorno').on('change', function() {
        var disabled = $(this).is(':checked');
        $('#evento-ora-inizio, #evento-ora-fine').prop('disabled', disabled);
    });
    
    // Filtra classi per plesso selezionato
    $('#evento-plesso').on('change', function() {
        var plessoId = $(this).val();
        $('#evento-classi optgroup').each(function() {
            var show = !plessoId || $(this).data('plesso') == plessoId;
            $(this).toggle(show);
        });
        $('#evento-classi').val([]);
    });
    
    // Submit form
    form.on('submit', function(e) {
        e.preventDefault();
        
        var id = $('#evento-id').val();
        var tuttoGiorno = $('#evento-tutto-giorno').is(':checked');
        
        var dataInizio = $('#evento-data-inizio').val();
        var dataFine = $('#evento-data-fine').val();
        
        if (!tuttoGiorno) {
            dataInizio += ' ' + $('#evento-ora-inizio').val() + ':00';
            dataFine += ' ' + $('#evento-ora-fine').val() + ':00';
        } else {
            dataInizio += ' 00:00:00';
            dataFine += ' 23:59:59';
        }
        
        var data = {
            titolo: $('#evento-titolo').val(),
            descrizione: $('#evento-descrizione').val(),
            data_inizio: dataInizio,
            data_fine: dataFine,
            tutto_giorno: tuttoGiorno ? 1 : 0,
            plesso_id: $('#evento-plesso').val() || null,
            classe_ids: $('#evento-classi').val() || [],
            sub_calendario_ids: $('#evento-sub-calendari').val() || [],
            responsabile: $('#evento-responsabile').val() || null,
            luogo_scuola: $('#evento-luogo-scuola').val() || null,
            luogo_fisico: $('#evento-luogo-fisico').val() || null,
            visibilita: $('#evento-visibilita').val(),
            mostra_su_schermo: $('#evento-schermo').is(':checked') ? 1 : 0
        };
        
        var method = id ? 'PUT' : 'POST';
        var url = scAdmin.apiUrl + '/eventi' + (id ? '/' + id : '');
        
        $.ajax({
            url: url,
            method: method,
            headers: { 'X-WP-Nonce': scAdmin.nonce },
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                alert(id ? 'Evento aggiornato!' : 'Evento creato!');
                location.reload();
            },
            error: function(xhr) {
                alert('Errore: ' + (xhr.responseJSON?.message || 'Sconosciuto'));
            }
        });
    });
    
    // Elimina evento
    $('.sc-delete-evento').on('click', function() {
        if (!confirm('Eliminare questo evento?')) return;
        
        var id = $(this).data('id');
        
        $.ajax({
            url: scAdmin.apiUrl + '/eventi/' + id,
            method: 'DELETE',
            headers: { 'X-WP-Nonce': scAdmin.nonce },
            success: function() {
                location.reload();
            },
            error: function(xhr) {
                alert('Errore: ' + (xhr.responseJSON?.message || 'Sconosciuto'));
            }
        });
    });
    
    // Carica dati evento per modifica
    function loadEvento(id) {
        $.ajax({
            url: scAdmin.apiUrl + '/eventi/' + id,
            headers: { 'X-WP-Nonce': scAdmin.nonce },
            success: function(evento) {
                $('#evento-id').val(evento.id);
                $('#evento-titolo').val(evento.titolo);
                $('#evento-descrizione').val(evento.descrizione || '');
                
                var inizio = evento.data_inizio.split(' ');
                var fine = evento.data_fine.split(' ');
                
                $('#evento-data-inizio').val(inizio[0]);
                $('#evento-data-fine').val(fine[0]);
                
                if (evento.tutto_giorno) {
                    $('#evento-tutto-giorno').prop('checked', true).trigger('change');
                } else {
                    $('#evento-ora-inizio').val(inizio[1] ? inizio[1].substring(0,5) : '08:00');
                    $('#evento-ora-fine').val(fine[1] ? fine[1].substring(0,5) : '09:00');
                }
                
                $('#evento-plesso').val(evento.plesso_id || '').trigger('change');
                $('#evento-visibilita').val(evento.visibilita);
                $('#evento-schermo').prop('checked', evento.mostra_su_schermo == 1);
                $('#evento-responsabile').val(evento.responsabile || '');
                $('#evento-luogo-scuola').val(evento.luogo_scuola || '');
                $('#evento-luogo-fisico').val(evento.luogo_fisico || '');
                
                // Classi
                if (evento.classe_ids && evento.classe_ids.length) {
                    $('#evento-classi').val(evento.classe_ids);
                }
                
                // Sub-calendari
                if (evento.sub_calendario_ids && evento.sub_calendario_ids.length) {
                    $('#evento-sub-calendari').val(evento.sub_calendario_ids);
                }
            },
            error: function() {
                alert('Errore nel caricamento evento');
                modal.hide();
            }
        });
    }
    
    // Reset form
    function resetForm() {
        form[0].reset();
        $('#evento-id').val('');
        $('#evento-tutto-giorno').prop('checked', false).trigger('change');
        $('#evento-plesso').val('').trigger('change');
        $('#evento-visibilita').val('privato'); // Default: privato
        $('#evento-schermo').prop('checked', false);
        $('#evento-responsabile').val('');
        $('#evento-luogo-scuola').val('');
        $('#evento-luogo-fisico').val('');
        $('#evento-sub-calendari').val([]);
        
        // Default: domani
        var tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        var tomorrowStr = tomorrow.toISOString().split('T')[0];
        $('#evento-data-inizio').val(tomorrowStr);
        $('#evento-data-fine').val(tomorrowStr);
    }
});
</script>
