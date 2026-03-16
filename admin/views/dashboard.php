<?php
defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>School Calendar - Dashboard</h1>
    
    <div class="sc-dashboard">
        <div class="sc-card">
            <h2>Shortcodes</h2>
            
            <h3>Calendario Completo</h3>
            <code>[school_calendar]</code>
            <p class="description">Calendario interattivo con filtri, viste multiple e dettaglio eventi.</p>
            
            <table class="widefat" style="margin: 10px 0;">
                <thead>
                    <tr><th>Attributo</th><th>Default</th><th>Descrizione</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>view</code></td><td>dayGridMonth</td><td>Vista iniziale: dayGridMonth, timeGridWeek, timeGridDay, listMonth</td></tr>
                    <tr><td><code>plesso</code></td><td>all</td><td>ID plesso o "all"</td></tr>
                    <tr><td><code>classe</code></td><td></td><td>ID classe</td></tr>
                    <tr><td><code>show_filters</code></td><td>true</td><td>Mostra filtri</td></tr>
                    <tr><td><code>show_legend</code></td><td>true</td><td>Mostra legenda</td></tr>
                    <tr><td><code>height</code></td><td>auto</td><td>Altezza calendario</td></tr>
                    <tr><td><code>editable</code></td><td>false</td><td>Abilita drag&drop (utenti autorizzati)</td></tr>
                </tbody>
            </table>
            
            <h3>Lista Eventi</h3>
            <code>[school_calendar_list]</code>
            <p class="description">Lista eventi raggruppati per data.</p>
            
            <table class="widefat" style="margin: 10px 0;">
                <thead>
                    <tr><th>Attributo</th><th>Default</th><th>Descrizione</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>limit</code></td><td>10</td><td>Numero eventi</td></tr>
                    <tr><td><code>days</code></td><td>30</td><td>Giorni futuri</td></tr>
                    <tr><td><code>plesso</code></td><td></td><td>ID plesso</td></tr>
                    <tr><td><code>classe</code></td><td></td><td>ID classe</td></tr>
                </tbody>
            </table>
            
            <h3>Widget Compatto</h3>
            <code>[school_calendar_widget]</code>
            <p class="description">Widget minimalista per sidebar.</p>
            
            <table class="widefat" style="margin: 10px 0;">
                <thead>
                    <tr><th>Attributo</th><th>Default</th><th>Descrizione</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>title</code></td><td>Prossimi Eventi</td><td>Titolo widget</td></tr>
                    <tr><td><code>limit</code></td><td>5</td><td>Numero eventi</td></tr>
                    <tr><td><code>plesso</code></td><td></td><td>ID plesso</td></tr>
                </tbody>
            </table>
        </div>
        
        <div class="sc-card">
            <h2>API REST</h2>
            <p>Base URL: <code><?php echo rest_url('school-calendar/v1'); ?></code></p>
            
            <h3>Autenticazione</h3>
            <p>Header: <code>X-SC-API-Key: {your_api_key}</code></p>
            
            <h3>Endpoints</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>Metodi</th>
                        <th>Descrizione</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>/eventi</code></td>
                        <td>GET, POST</td>
                        <td>Lista e creazione eventi</td>
                    </tr>
                    <tr>
                        <td><code>/eventi/{id}</code></td>
                        <td>GET, PUT, DELETE</td>
                        <td>Dettaglio, modifica, elimina</td>
                    </tr>
                    <tr>
                        <td><code>/plessi</code></td>
                        <td>GET</td>
                        <td>Lista plessi</td>
                    </tr>
                    <tr>
                        <td><code>/classi</code></td>
                        <td>GET</td>
                        <td>Lista classi</td>
                    </tr>
                    <tr>
                        <td><code>/calendari-esterni</code></td>
                        <td>GET, POST</td>
                        <td>Gestione sync Google/iCal</td>
                    </tr>
                    <tr>
                        <td><code>/api-keys</code></td>
                        <td>GET, POST</td>
                        <td>Gestione API Keys</td>
                    </tr>
                </tbody>
            </table>
            
            <h3>Parametri GET /eventi</h3>
            <ul>
                <li><code>start</code> - Data inizio (YYYY-MM-DD)</li>
                <li><code>end</code> - Data fine (YYYY-MM-DD)</li>
                <li><code>plesso_id</code> - Filtra per plesso</li>
                <li><code>classe_id</code> - Filtra per classe</li>
                <li><code>source</code> - local, google, ical</li>
                <li><code>format</code> - default o fullcalendar</li>
            </ul>
        </div>
    </div>
</div>

<style>
.sc-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
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
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.sc-card h3 {
    margin-top: 20px;
    margin-bottom: 5px;
}
.sc-card code {
    background: #f0f0f1;
    padding: 2px 6px;
}
.sc-card table code {
    font-size: 12px;
}
.sc-card .description {
    color: #666;
    font-size: 13px;
}
</style>
