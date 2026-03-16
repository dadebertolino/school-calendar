<?php
defined('ABSPATH') || exit;

// Salva permessi
if (isset($_POST['sc_save_permissions']) && wp_verify_nonce($_POST['sc_permissions_nonce'], 'sc_save_permissions')) {
    $users_with_permission = isset($_POST['sc_users']) ? array_map('intval', $_POST['sc_users']) : [];
    
    // Rimuovi permesso a tutti gli utenti
    $all_users = get_users(['fields' => 'ID']);
    foreach ($all_users as $user_id) {
        $user = new WP_User($user_id);
        $user->remove_cap('sc_create_events');
        $user->remove_cap('sc_edit_own_events');
    }
    
    // Aggiungi permesso agli utenti selezionati
    foreach ($users_with_permission as $user_id) {
        $user = new WP_User($user_id);
        $user->add_cap('sc_create_events');
        $user->add_cap('sc_edit_own_events');
    }
    
    echo '<div class="notice notice-success"><p>Permessi salvati con successo!</p></div>';
}

// Ottieni utenti con ruolo subscriber, contributor, author, editor (escludi admin che hanno già tutto)
$users = get_users([
    'role__not_in' => ['administrator'],
    'orderby' => 'display_name',
    'order' => 'ASC'
]);

// Utenti che hanno già il permesso
$users_with_cap = get_users([
    'capability' => 'sc_create_events',
    'fields' => 'ID'
]);
?>

<div class="wrap">
    <h1>Permessi Calendario</h1>
    <p class="description">Seleziona quali utenti possono creare e modificare i propri eventi nel calendario. Gli amministratori hanno sempre tutti i permessi.</p>
    
    <form method="post" action="">
        <?php wp_nonce_field('sc_save_permissions', 'sc_permissions_nonce'); ?>
        
        <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
            
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="sc-select-all">
                    <strong>Seleziona/Deseleziona tutti</strong>
                </label>
            </div>
            
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Ruolo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="4">Nessun utente trovato (esclusi gli amministratori)</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" 
                                           name="sc_users[]" 
                                           value="<?php echo $user->ID; ?>" 
                                           class="sc-user-checkbox"
                                           <?php checked(in_array($user->ID, $users_with_cap)); ?>>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($user->display_name); ?></strong>
                                    <?php if ($user->first_name || $user->last_name): ?>
                                        <br><small><?php echo esc_html(trim($user->first_name . ' ' . $user->last_name)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <?php 
                                    $roles = array_map(function($role) {
                                        return translate_user_role(ucfirst($role));
                                    }, $user->roles);
                                    echo esc_html(implode(', ', $roles));
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px;">
                <h3>Legenda permessi</h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><strong>Utenti selezionati:</strong> possono creare eventi e modificare/eliminare solo i propri</li>
                    <li><strong>Amministratori:</strong> possono creare, modificare, eliminare tutti gli eventi</li>
                    <li><strong>Utenti non selezionati:</strong> possono solo visualizzare il calendario</li>
                </ul>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="sc_save_permissions" class="button button-primary" value="Salva Permessi">
        </p>
    </form>
</div>

<script>
document.getElementById('sc-select-all').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('.sc-user-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = this.checked;
    }.bind(this));
});
</script>
