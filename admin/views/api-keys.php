<?php
defined('ABSPATH') || exit;

use SchoolCalendar\Auth;

$api_keys = Auth::get_user_api_keys(get_current_user_id());
$is_admin = current_user_can('administrator');
?>
<div class="wrap">
    <h1>API Keys</h1>
    <p class="description">
        Crea API Keys per autenticare richieste da applicazioni esterne.
    </p>
    
    <div class="sc-api-keys-list">
        <?php if (empty($api_keys)): ?>
            <div class="sc-card">
                <p>Nessuna API Key configurata.</p>
            </div>
        <?php else: ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Key (preview)</th>
                        <th>Ultimo utilizzo</th>
                        <th>Scadenza</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($api_keys as $key): ?>
                        <tr>
                            <td><strong><?php echo esc_html($key->nome); ?></strong></td>
                            <td><code><?php echo esc_html($key->key_preview); ?>...</code></td>
                            <td>
                                <?php 
                                echo $key->last_used 
                                    ? date_i18n('d/m/Y H:i', strtotime($key->last_used))
                                    : '<em>Mai</em>';
                                ?>
                            </td>
                            <td>
                                <?php 
                                echo $key->expires_at 
                                    ? date_i18n('d/m/Y', strtotime($key->expires_at))
                                    : '<em>Nessuna</em>';
                                ?>
                            </td>
                            <td>
                                <span class="sc-status sc-status-<?php echo $key->attivo ? 'active' : 'inactive'; ?>">
                                    <?php echo $key->attivo ? 'Attiva' : 'Revocata'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($key->attivo): ?>
                                    <button class="button button-small sc-revoke-key" data-id="<?php echo $key->id; ?>">
                                        Revoca
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="sc-card" style="max-width: 500px; margin-top: 30px;">
        <h2>Crea Nuova API Key</h2>
        
        <form id="sc-add-apikey-form">
            <table class="form-table">
                <tr>
                    <th><label for="key-nome">Nome</label></th>
                    <td>
                        <input type="text" id="key-nome" name="nome" class="regular-text" 
                               placeholder="es. App Mobile, Integrazione ERP" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="key-expires">Scadenza</label></th>
                    <td>
                        <input type="date" id="key-expires" name="expires_at">
                        <p class="description">Lascia vuoto per nessuna scadenza</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Genera API Key</button>
            </p>
        </form>
        
        <div id="sc-new-key-result" style="display:none;" class="notice notice-success">
            <p><strong>API Key generata!</strong></p>
            <p>Copia questa chiave, non sarà più visibile:</p>
            <p><code id="sc-new-key-value" style="word-break: break-all;"></code></p>
            <p>
                <button class="button sc-copy-key">Copia</button>
            </p>
        </div>
    </div>
    
    <div class="sc-card" style="max-width: 500px; margin-top: 20px;">
        <h2>Utilizzo</h2>
        
        <h4>Header HTTP</h4>
        <pre><code>X-SC-API-Key: {your_api_key}</code></pre>
        
        <h4>Esempio cURL</h4>
        <pre><code>curl -H "X-SC-API-Key: abc123..." \
     <?php echo rest_url('school-calendar/v1/eventi'); ?></code></pre>
        
        <h4>Esempio PHP</h4>
        <pre><code>$response = file_get_contents(
    '<?php echo rest_url('school-calendar/v1/eventi'); ?>',
    false,
    stream_context_create([
        'http' => [
            'header' => 'X-SC-API-Key: abc123...'
        ]
    ])
);</code></pre>
    </div>
</div>

<style>
.sc-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    border-radius: 4px;
}
.sc-card h2 {
    margin-top: 0;
}
.sc-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}
.sc-status-active { background: #d4edda; color: #155724; }
.sc-status-inactive { background: #f8d7da; color: #721c24; }
.sc-api-keys-list { margin-top: 20px; }
pre {
    background: #f0f0f1;
    padding: 10px;
    overflow-x: auto;
}
</style>

<script>
jQuery(function($) {
    // Crea API Key
    $('#sc-add-apikey-form').on('submit', function(e) {
        e.preventDefault();
        
        var data = {
            nome: $('#key-nome').val(),
            expires_at: $('#key-expires').val() || null
        };
        
        $.ajax({
            url: scAdmin.apiUrl + '/api-keys',
            method: 'POST',
            headers: { 'X-WP-Nonce': scAdmin.nonce },
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                $('#sc-new-key-value').text(response.api_key);
                $('#sc-new-key-result').show();
                $('#sc-add-apikey-form')[0].reset();
            },
            error: function(xhr) {
                alert('Errore: ' + (xhr.responseJSON?.message || 'Sconosciuto'));
            }
        });
    });
    
    // Copia key
    $('.sc-copy-key').on('click', function() {
        navigator.clipboard.writeText($('#sc-new-key-value').text());
        alert('Copiato!');
    });
    
    // Revoca key
    $('.sc-revoke-key').on('click', function() {
        if (!confirm('Revocare questa API Key? Le applicazioni che la usano smetteranno di funzionare.')) {
            return;
        }
        
        var id = $(this).data('id');
        
        $.ajax({
            url: scAdmin.apiUrl + '/api-keys/' + id,
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
});
</script>
