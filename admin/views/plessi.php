<?php
defined('ABSPATH') || exit;

use SchoolCalendar\Models\Plesso;
use SchoolCalendar\Models\Classe;

$plessi = Plesso::all();
$anno_corrente = Classe::getAnnoCorrente();
?>
<div class="wrap">
    <h1>Plessi e Classi</h1>
    
    <div class="notice notice-info">
        <p>
            <strong>Nota:</strong> Plessi, specializzazioni e classi sono gestiti dal plugin 
            <strong>Gestione Scuola</strong>. Questa pagina mostra i dati in sola lettura.
            <?php if ($anno_corrente): ?>
                <br>Anno scolastico corrente: <strong><?php echo esc_html($anno_corrente->descrizione); ?></strong>
            <?php endif; ?>
        </p>
    </div>
    
    <?php if (empty($plessi)): ?>
        <div class="notice notice-warning">
            <p>Nessun plesso trovato. Configura i plessi dal plugin Gestione Scuola.</p>
        </div>
    <?php else: ?>
        <div class="sc-plessi-grid">
            <?php foreach ($plessi as $plesso): ?>
                <div class="sc-card">
                    <h2><?php echo esc_html($plesso->descrizione); ?></h2>
                    
                    <?php if ($plesso->descrizione_pubblica): ?>
                        <p class="description"><?php echo esc_html($plesso->descrizione_pubblica); ?></p>
                    <?php endif; ?>
                    
                    <?php 
                    $specializzazioni = $plesso->specializzazioni();
                    if ($specializzazioni): 
                    ?>
                        <h4>Specializzazioni</h4>
                        <ul class="sc-spec-list">
                            <?php foreach ($specializzazioni as $spec): ?>
                                <li>
                                    <strong><?php echo esc_html($spec->descrizione); ?></strong>
                                    <?php 
                                    $classi_spec = Classe::bySpecializzazione($spec->id);
                                    if ($classi_spec): 
                                    ?>
                                        <div class="sc-classi-badges">
                                            <?php foreach ($classi_spec as $classe): ?>
                                                <span class="sc-badge"><?php echo esc_html($classe->descrizione); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="description">Nessuna specializzazione configurata</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.sc-plessi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.sc-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    border-radius: 4px;
}
.sc-card h2 {
    margin-top: 0;
    color: #1d2327;
    border-bottom: 2px solid #2271b1;
    padding-bottom: 10px;
}
.sc-card h4 {
    margin: 15px 0 10px;
    color: #50575e;
}
.sc-spec-list {
    margin: 0;
    padding: 0;
    list-style: none;
}
.sc-spec-list li {
    padding: 10px;
    margin-bottom: 8px;
    background: #f6f7f7;
    border-left: 3px solid #2271b1;
}
.sc-classi-badges {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}
.sc-badge {
    background: #2271b1;
    color: #fff;
    padding: 2px 10px;
    border-radius: 3px;
    font-size: 12px;
}
</style>
