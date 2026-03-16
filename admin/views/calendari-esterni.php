<?php
defined('ABSPATH') || exit;

use SchoolCalendar\Models\CalendarioEsterno;
use SchoolCalendar\Models\Plesso;
use SchoolCalendar\SyncManager;

$calendari = CalendarioEsterno::all([], 'nome ASC');
$plessi = Plesso::attivi();
$syncManager = new SyncManager();
$syncStatus = $syncManager->getStatus();
?>
<div class="wrap">
    <h1>Calendari Esterni</h1>
    <p class="description">Configura la sincronizzazione con Google Calendar o feed iCal.</p>
    
    <!-- Status Box -->
    <div class="sc-status-box">
        <div class="sc-status-item">
            <span class="dashicons dashicons-clock"></span>
            <strong>Prossima sync:</strong> 
            <?php echo $syncStatus['next_run'] ? date_i18n('d/m/Y H:i:s', strtotime($syncStatus['next_run'])) : 'Non schedulata'; ?>
        </div>
        <div class="sc-status-item">
            <span class="dashicons dashicons-update"></span>
            <strong>Ultima sync:</strong> 
            <?php echo $syncStatus['last_sync'] ? date_i18n('d/m/Y H:i:s', strtotime($syncStatus['last_sync'])) : 'Mai'; ?>
        </div>
        <div class="sc-status-item">
            <span class="dashicons dashicons-calendar-alt"></span>
            <strong>Calendari attivi:</strong> <?php echo $syncStatus['calendars_count']; ?>
        </div>
        <button class="button button-primary sc-sync-all-btn">
            <span class="dashicons dashicons-update"></span> Sincronizza Tutti
        </button>
    </div>
    
    <div class="sc-calendari-list">
        <?php if (empty($calendari)): ?>
            <div class="sc-card">
                <p>Nessun calendario esterno configurato.</p>
            </div>
        <?php else: ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Plesso</th>
                        <th>Visibilità</th>
                        <th>Ultima Sync</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calendari as $cal): ?>
                        <tr>
                            <td><strong><?php echo esc_html($cal->nome); ?></strong></td>
                            <td>
                                <span class="sc-badge sc-badge-<?php echo $cal->tipo; ?>">
                                    <?php echo strtoupper($cal->tipo); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $plesso = $cal->plesso();
                                echo $plesso ? esc_html($plesso->descrizione) : '<em>Tutti</em>';
                                ?>
                            </td>
                            <td><?php echo ucfirst($cal->visibilita_default); ?></td>
                            <td>
                                <?php 
                                echo $cal->last_sync 
                                    ? date_i18n('d/m/Y H:i', strtotime($cal->last_sync))
                                    : '<em>Mai</em>';
                                ?>
                            </td>
                            <td>
                                <span class="sc-status sc-status-<?php echo $cal->attivo ? 'active' : 'inactive'; ?>">
                                    <?php echo $cal->attivo ? 'Attivo' : 'Disattivo'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small sc-sync-cal" data-id="<?php echo $cal->id; ?>">
                                    Sync Ora
                                </button>
                                <button class="button button-small sc-edit-cal" data-id="<?php echo $cal->id; ?>">
                                    Modifica
                                </button>
                                <button class="button button-small sc-delete-cal" data-id="<?php echo $cal->id; ?>">
                                    Elimina
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="sc-card" style="max-width: 600px; margin-top: 30px;">
        <h2>Aggiungi Calendario Esterno</h2>
        
        <form id="sc-add-calendario-form">
            <table class="form-table">
                <tr>
                    <th><label for="cal-nome">Nome</label></th>
                    <td><input type="text" id="cal-nome" name="nome" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="cal-tipo">Tipo</label></th>
                    <td>
                        <select id="cal-tipo" name="tipo" required>
                            <option value="google">Google Calendar</option>
                            <option value="ical">iCal Feed</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="cal-calendar-id">Calendar ID / URL</label></th>
                    <td>
                        <input type="text" id="cal-calendar-id" name="calendar_id" class="large-text" required>
                        <p class="description">
                            <strong>Google:</strong> ID calendario (es. abc123@group.calendar.google.com)<br>
                            <strong>iCal:</strong> URL feed ICS
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="cal-plesso">Plesso</label></th>
                    <td>
                        <select id="cal-plesso" name="plesso_id">
                            <option value="">Tutti i plessi</option>
                            <?php foreach ($plessi as $plesso): ?>
                                <option value="<?php echo $plesso->id; ?>">
                                    <?php echo esc_html($plesso->descrizione); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="cal-visibilita">Visibilità Default</label></th>
                    <td>
                        <select id="cal-visibilita" name="visibilita_default">
                            <option value="pubblico">Pubblico</option>
                            <option value="privato">Privato (solo abbonati)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="cal-colore">Colore Eventi</label></th>
                    <td>
                        <input type="color" id="cal-colore" name="colore" value="#00b894">
                        <span class="description" style="margin-left: 10px;">Colore per gli eventi di questo calendario (default: <span style="color:#00b894">■</span> Google, <span style="color:#fdcb6e">■</span> iCal)</span>
                    </td>
                </tr>
                <tr>
                    <th><label for="cal-interval">Intervallo Sync</label></th>
                    <td>
                        <select id="cal-interval" name="sync_interval">
                            <option value="5">Ogni 5 minuti</option>
                            <option value="15" selected>Ogni 15 minuti</option>
                            <option value="30">Ogni 30 minuti</option>
                            <option value="60">Ogni ora</option>
                        </select>
                    </td>
                </tr>
                <tr class="sc-google-only">
                    <th><label for="cal-credentials">Credenziali Google</label></th>
                    <td>
                        <textarea id="cal-credentials" name="credentials" rows="5" class="large-text" 
                                  placeholder='Incolla qui il JSON del Service Account...'></textarea>
                        <p class="description">
                            JSON delle credenziali del Service Account Google.<br>
                            <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">
                                Crea Service Account →
                            </a>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Aggiungi Calendario</button>
            </p>
        </form>
    </div>
</div>

<style>
.sc-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    border-radius: 4px;
}
.sc-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}
.sc-badge-google { background: #4285f4; color: #fff; }
.sc-badge-ical { background: #ff9800; color: #fff; }
.sc-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}
.sc-status-active { background: #d4edda; color: #155724; }
.sc-status-inactive { background: #f8d7da; color: #721c24; }
.sc-calendari-list { margin-top: 20px; }
.sc-status-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-left: 4px solid #2271b1;
    padding: 15px 20px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 30px;
    flex-wrap: wrap;
}
.sc-status-item {
    display: flex;
    align-items: center;
    gap: 5px;
}
.sc-status-item .dashicons {
    color: #2271b1;
}
.sc-sync-all-btn {
    margin-left: auto !important;
}
.sc-sync-all-btn .dashicons {
    vertical-align: middle;
    margin-right: 3px;
}
.sc-sync-stats {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
.sc-sync-stats span {
    margin-right: 10px;
}
.sc-sync-stats .created { color: #28a745; }
.sc-sync-stats .updated { color: #007bff; }
.sc-sync-stats .deleted { color: #dc3545; }
</style>

<script>
jQuery(function($) {
    var syncNonce = '<?php echo wp_create_nonce('sc_sync_nonce'); ?>';
    
    // Toggle campi Google
    $('#cal-tipo').on('change', function() {
        $('.sc-google-only').toggle($(this).val() === 'google');
    }).trigger('change');
    
    // Submit form (creazione o modifica)
    $('#sc-add-calendario-form').on('submit', function(e) {
        e.preventDefault();
        
        var editId = $(this).data('edit-id');
        var isEdit = !!editId;
        
        var data = {
            nome: $('#cal-nome').val(),
            tipo: $('#cal-tipo').val(),
            calendar_id: $('#cal-calendar-id').val(),
            plesso_id: $('#cal-plesso').val() || null,
            visibilita_default: $('#cal-visibilita').val(),
            colore: $('#cal-colore').val() || null,
            sync_interval: parseInt($('#cal-interval').val())
        };
        
        // Credenziali solo se fornite (in modifica può essere lasciato vuoto)
        if (data.tipo === 'google' && $('#cal-credentials').val().trim()) {
            try {
                data.credentials = JSON.parse($('#cal-credentials').val());
            } catch(e) {
                alert('JSON credenziali non valido');
                return;
            }
        }
        
        $.ajax({
            url: scAdmin.apiUrl + '/calendari-esterni' + (isEdit ? '/' + editId : ''),
            method: isEdit ? 'PUT' : 'POST',
            headers: { 'X-WP-Nonce': scAdmin.nonce },
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                alert(isEdit ? 'Calendario aggiornato!' : 'Calendario aggiunto!');
                location.reload();
            },
            error: function(xhr) {
                alert('Errore: ' + (xhr.responseJSON?.message || 'Sconosciuto'));
            }
        });
    });
    
    // Sync manuale singolo
    $('.sc-sync-cal').on('click', function() {
        var id = $(this).data('id');
        var $btn = $(this).prop('disabled', true);
        var originalText = $btn.text();
        $btn.text('Sync in corso...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sc_sync_calendar',
                nonce: syncNonce,
                calendario_id: id
            },
            success: function(response) {
                if (response.success) {
                    var stats = response.data;
                    alert('Sincronizzazione completata!\n\n' +
                          'Creati: ' + stats.created + '\n' +
                          'Aggiornati: ' + stats.updated + '\n' +
                          'Eliminati: ' + stats.deleted + '\n' +
                          'Saltati: ' + stats.skipped);
                    location.reload();
                } else {
                    alert('Errore: ' + (response.data?.error_message || response.data?.error || 'Sconosciuto'));
                }
            },
            error: function(xhr) {
                alert('Errore di connessione');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Sync tutti
    $('.sc-sync-all-btn').on('click', function() {
        if (!confirm('Sincronizzare tutti i calendari esterni?')) return;
        
        var $btn = $(this).prop('disabled', true);
        var originalHtml = $btn.html();
        $btn.html('<span class="dashicons dashicons-update spin"></span> Sincronizzazione...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sc_sync_all',
                nonce: syncNonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Sincronizzazione completata per ' + response.data.total_calendars + ' calendari');
                    location.reload();
                } else {
                    alert('Errore durante la sincronizzazione');
                }
            },
            error: function() {
                alert('Errore di connessione');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
    
    // Elimina
    $('.sc-delete-cal').on('click', function() {
        if (!confirm('Eliminare questo calendario e tutti gli eventi importati?')) return;
        
        var id = $(this).data('id');
        
        $.ajax({
            url: scAdmin.apiUrl + '/calendari-esterni/' + id,
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
    
    // Modifica calendario
    $('.sc-edit-cal').on('click', function() {
        var id = $(this).data('id');
        
        $.ajax({
            url: scAdmin.apiUrl + '/calendari-esterni/' + id,
            method: 'GET',
            headers: { 'X-WP-Nonce': scAdmin.nonce },
            success: function(cal) {
                // Popola il form con i dati
                $('#cal-nome').val(cal.nome);
                $('#cal-tipo').val(cal.tipo).trigger('change');
                $('#cal-calendar-id').val(cal.calendar_id);
                $('#cal-plesso').val(cal.plesso_id || '');
                $('#cal-visibilita').val(cal.visibilita_default);
                $('#cal-colore').val(cal.colore || (cal.tipo === 'google' ? '#00b894' : '#fdcb6e'));
                $('#cal-interval').val(cal.sync_interval);
                
                // Se ha credenziali, mostra placeholder
                if (cal.tipo === 'google' && cal.has_credentials) {
                    $('#cal-credentials').attr('placeholder', '(credenziali già configurate - lascia vuoto per mantenere)');
                } else {
                    $('#cal-credentials').attr('placeholder', '');
                }
                $('#cal-credentials').val('');
                
                // Cambia form in modalità modifica
                $('#sc-add-calendario-form').data('edit-id', id);
                $('#sc-add-calendario-form').find('h2').text('Modifica Calendario');
                $('#sc-add-calendario-form button[type="submit"]').text('Aggiorna Calendario');
                
                // Aggiungi pulsante annulla se non esiste
                if (!$('#sc-cancel-edit').length) {
                    $('#sc-add-calendario-form button[type="submit"]').after(
                        ' <button type="button" id="sc-cancel-edit" class="button">Annulla</button>'
                    );
                }
                
                // Scroll al form
                $('html, body').animate({
                    scrollTop: $('#sc-add-calendario-form').offset().top - 50
                }, 300);
            },
            error: function(xhr) {
                alert('Errore nel caricamento: ' + (xhr.responseJSON?.message || 'Sconosciuto'));
            }
        });
    });
    
    // Annulla modifica
    $(document).on('click', '#sc-cancel-edit', function() {
        $('#sc-add-calendario-form')[0].reset();
        $('#sc-add-calendario-form').removeData('edit-id');
        $('#sc-add-calendario-form').find('h2').text('Aggiungi Calendario Esterno');
        $('#sc-add-calendario-form button[type="submit"]').text('Aggiungi Calendario');
        $('#cal-credentials').attr('placeholder', '');
        $(this).remove();
        $('#cal-tipo').trigger('change');
    });
});
</script>
