<?php
defined('ABSPATH') || exit;

use SchoolCalendar\Models\Plesso;
use SchoolCalendar\Models\SubCalendario;

$plessi = Plesso::all();
?>

<div class="wrap">
    <h1>Sub-calendari</h1>
    <p class="description">Crea categorie di eventi per ogni plesso, ognuna con il proprio colore.</p>
    
    <div class="sc-sub-calendari-container" style="display: flex; gap: 30px; margin-top: 20px;">
        
        <!-- Lista sub-calendari per plesso -->
        <div class="sc-sub-calendari-list" style="flex: 2;">
            <?php foreach ($plessi as $plesso): ?>
                <?php $subCalendari = SubCalendario::perPlesso($plesso->id); ?>
                <div class="sc-plesso-section" style="margin-bottom: 30px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                        <?php echo esc_html($plesso->descrizione_pubblica ?: $plesso->descrizione); ?>
                    </h2>
                    
                    <?php if (empty($subCalendari)): ?>
                        <p style="color: #666; font-style: italic;">Nessun sub-calendario per questo plesso.</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat striped" style="margin-top: 10px;">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Colore</th>
                                    <th>Nome</th>
                                    <th style="width: 80px;">Ordine</th>
                                    <th style="width: 80px;">Stato</th>
                                    <th style="width: 150px;">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subCalendari as $sc): ?>
                                    <tr data-id="<?php echo $sc->id; ?>">
                                        <td>
                                            <span class="sc-color-preview" style="display: inline-block; width: 24px; height: 24px; border-radius: 4px; background-color: <?php echo esc_attr($sc->colore); ?>;"></span>
                                        </td>
                                        <td><strong><?php echo esc_html($sc->nome); ?></strong></td>
                                        <td><?php echo (int) $sc->ordine; ?></td>
                                        <td>
                                            <?php if ($sc->attivo): ?>
                                                <span style="color: #00a32a;">● Attivo</span>
                                            <?php else: ?>
                                                <span style="color: #999;">○ Disattivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small sc-edit-sub-cal" 
                                                    data-id="<?php echo $sc->id; ?>"
                                                    data-nome="<?php echo esc_attr($sc->nome); ?>"
                                                    data-colore="<?php echo esc_attr($sc->colore); ?>"
                                                    data-ordine="<?php echo (int) $sc->ordine; ?>"
                                                    data-attivo="<?php echo $sc->attivo ? '1' : '0'; ?>"
                                                    data-plesso-id="<?php echo $sc->plesso_id; ?>">
                                                Modifica
                                            </button>
                                            <button type="button" class="button button-small button-link-delete sc-delete-sub-cal" 
                                                    data-id="<?php echo $sc->id; ?>"
                                                    data-nome="<?php echo esc_attr($sc->nome); ?>">
                                                Elimina
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Form aggiungi/modifica -->
        <div class="sc-sub-calendari-form" style="flex: 1; position: sticky; top: 32px; align-self: flex-start;">
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 id="sc-form-title" style="margin-top: 0;">Nuovo Sub-calendario</h2>
                
                <form id="sc-sub-calendario-form">
                    <input type="hidden" id="sc-edit-id" value="">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="sc-plesso">Plesso *</label></th>
                            <td>
                                <select id="sc-plesso" required style="width: 100%;">
                                    <option value="">Seleziona plesso...</option>
                                    <?php foreach ($plessi as $plesso): ?>
                                        <option value="<?php echo $plesso->id; ?>">
                                            <?php echo esc_html($plesso->descrizione_pubblica ?: $plesso->descrizione); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sc-nome">Nome *</label></th>
                            <td>
                                <input type="text" id="sc-nome" required style="width: 100%;" 
                                       placeholder="es. Aula Magna, Lab Informatica...">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sc-colore">Colore</label></th>
                            <td>
                                <input type="color" id="sc-colore" value="#2d7ff9" style="width: 60px; height: 40px; padding: 0; border: none; cursor: pointer;">
                                <span id="sc-colore-hex" style="margin-left: 10px; color: #666;">#2d7ff9</span>
                                <div style="margin-top: 10px;">
                                    <button type="button" class="sc-color-preset" data-color="#2563eb" style="background:#2563eb;">●</button>
                                    <button type="button" class="sc-color-preset" data-color="#16a34a" style="background:#16a34a;">●</button>
                                    <button type="button" class="sc-color-preset" data-color="#dc2626" style="background:#dc2626;">●</button>
                                    <button type="button" class="sc-color-preset" data-color="#ca8a04" style="background:#ca8a04;">●</button>
                                    <button type="button" class="sc-color-preset" data-color="#9333ea" style="background:#9333ea;">●</button>
                                    <button type="button" class="sc-color-preset" data-color="#0891b2" style="background:#0891b2;">●</button>
                                    <button type="button" class="sc-color-preset" data-color="#be185d" style="background:#be185d;">●</button>
                                    <button type="button" class="sc-color-preset" data-color="#ea580c" style="background:#ea580c;">●</button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sc-ordine">Ordine</label></th>
                            <td>
                                <input type="number" id="sc-ordine" value="0" min="0" style="width: 80px;">
                                <p class="description">Ordine di visualizzazione (0 = primo)</p>
                            </td>
                        </tr>
                        <tr id="sc-attivo-row" style="display: none;">
                            <th><label for="sc-attivo">Stato</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="sc-attivo" value="1" checked>
                                    Attivo
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="sc-submit-btn">Crea Sub-calendario</button>
                        <button type="button" class="button" id="sc-cancel-btn" style="display: none;">Annulla</button>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.sc-color-preset {
    width: 28px;
    height: 28px;
    border: 2px solid #fff;
    border-radius: 50%;
    cursor: pointer;
    margin-right: 5px;
    color: transparent;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    transition: transform 0.1s;
}
.sc-color-preset:hover {
    transform: scale(1.15);
}
</style>

<script>
jQuery(function($) {
    var form = $('#sc-sub-calendario-form');
    
    // Aggiorna hex quando cambia colore
    $('#sc-colore').on('input', function() {
        $('#sc-colore-hex').text($(this).val());
    });
    
    // Preset colori
    $('.sc-color-preset').on('click', function() {
        var color = $(this).data('color');
        $('#sc-colore').val(color);
        $('#sc-colore-hex').text(color);
    });
    
    // Submit form
    form.on('submit', function(e) {
        e.preventDefault();
        
        var editId = $('#sc-edit-id').val();
        var isEdit = !!editId;
        
        var data = {
            plesso_id: parseInt($('#sc-plesso').val()),
            nome: $('#sc-nome').val(),
            colore: $('#sc-colore').val(),
            ordine: parseInt($('#sc-ordine').val()) || 0
        };
        
        if (isEdit) {
            data.attivo = $('#sc-attivo').is(':checked') ? 1 : 0;
        }
        
        $.ajax({
            url: scAdmin.apiUrl + '/sub-calendari' + (isEdit ? '/' + editId : ''),
            method: isEdit ? 'PUT' : 'POST',
            headers: { 'X-WP-Nonce': scAdmin.nonce },
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                alert(isEdit ? 'Sub-calendario aggiornato!' : 'Sub-calendario creato!');
                location.reload();
            },
            error: function(xhr) {
                alert('Errore: ' + (xhr.responseJSON?.message || 'Sconosciuto'));
            }
        });
    });
    
    // Modifica
    $('.sc-edit-sub-cal').on('click', function() {
        var btn = $(this);
        
        $('#sc-edit-id').val(btn.data('id'));
        $('#sc-plesso').val(btn.data('plesso-id'));
        $('#sc-nome').val(btn.data('nome'));
        $('#sc-colore').val(btn.data('colore'));
        $('#sc-colore-hex').text(btn.data('colore'));
        $('#sc-ordine').val(btn.data('ordine'));
        $('#sc-attivo').prop('checked', btn.data('attivo') == '1');
        
        $('#sc-form-title').text('Modifica Sub-calendario');
        $('#sc-submit-btn').text('Aggiorna');
        $('#sc-cancel-btn').show();
        $('#sc-attivo-row').show();
        
        // Scroll al form su mobile
        $('html, body').animate({
            scrollTop: $('#sc-sub-calendario-form').offset().top - 50
        }, 300);
    });
    
    // Annulla modifica
    $('#sc-cancel-btn').on('click', function() {
        resetForm();
    });
    
    // Elimina
    $('.sc-delete-sub-cal').on('click', function() {
        var id = $(this).data('id');
        var nome = $(this).data('nome');
        
        if (!confirm('Eliminare il sub-calendario "' + nome + '"?\n\nGli eventi associati non verranno eliminati.')) {
            return;
        }
        
        $.ajax({
            url: scAdmin.apiUrl + '/sub-calendari/' + id,
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
    
    function resetForm() {
        form[0].reset();
        $('#sc-edit-id').val('');
        $('#sc-colore').val('#2d7ff9');
        $('#sc-colore-hex').text('#2d7ff9');
        $('#sc-form-title').text('Nuovo Sub-calendario');
        $('#sc-submit-btn').text('Crea Sub-calendario');
        $('#sc-cancel-btn').hide();
        $('#sc-attivo-row').hide();
    }
});
</script>
