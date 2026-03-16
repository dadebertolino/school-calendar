<?php
namespace SchoolCalendar;

use SchoolCalendar\Models\Plesso;
use SchoolCalendar\Models\Evento;

defined('ABSPATH') || exit;

class CalendarWidget extends \WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'school_calendar_widget',
            'Calendario Scolastico',
            [
                'description' => 'Mostra i prossimi eventi del calendario scolastico',
                'classname' => 'widget-school-calendar',
            ]
        );
    }
    
    /**
     * Frontend widget
     */
    public function widget($args, $instance) {
        $title = apply_filters('widget_title', $instance['title'] ?? 'Prossimi Eventi');
        $limit = $instance['limit'] ?? 5;
        $plesso_id = $instance['plesso_id'] ?? '';
        
        // Recupera eventi
        $params = [
            'start' => date('Y-m-d H:i:s'),
            'limit' => (int) $limit,
        ];
        
        if ($plesso_id) {
            $params['plesso_id'] = (int) $plesso_id;
        }
        
        $can_view_private = is_user_logged_in() && current_user_can('sc_view_private_events');
        $eventi = Evento::filter($params, $can_view_private);
        
        // Enqueue stili
        wp_enqueue_style('school-calendar');
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        
        if (empty($eventi)) {
            echo '<p class="sc-widget-empty">Nessun evento in programma</p>';
        } else {
            echo '<ul class="sc-widget-list">';
            
            foreach ($eventi as $evento) {
                $date = strtotime($evento->data_inizio);
                ?>
                <li class="sc-widget-event">
                    <span class="sc-widget-date">
                        <?php 
                        echo date_i18n('d M', $date);
                        if (!$evento->tutto_giorno) {
                            echo ' · ' . date('H:i', $date);
                        }
                        ?>
                    </span>
                    <span class="sc-widget-title"><?php echo esc_html($evento->titolo); ?></span>
                </li>
                <?php
            }
            
            echo '</ul>';
        }
        
        echo $args['after_widget'];
    }
    
    /**
     * Backend form
     */
    public function form($instance) {
        $title = $instance['title'] ?? 'Prossimi Eventi';
        $limit = $instance['limit'] ?? 5;
        $plesso_id = $instance['plesso_id'] ?? '';
        
        $plessi = Plesso::attivi();
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Titolo:</label>
            <input class="widefat" 
                   id="<?php echo $this->get_field_id('title'); ?>" 
                   name="<?php echo $this->get_field_name('title'); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        
        <p>
            <label for="<?php echo $this->get_field_id('limit'); ?>">Numero eventi:</label>
            <input class="tiny-text" 
                   id="<?php echo $this->get_field_id('limit'); ?>" 
                   name="<?php echo $this->get_field_name('limit'); ?>" 
                   type="number" 
                   min="1" 
                   max="20" 
                   value="<?php echo esc_attr($limit); ?>">
        </p>
        
        <p>
            <label for="<?php echo $this->get_field_id('plesso_id'); ?>">Plesso:</label>
            <select class="widefat" 
                    id="<?php echo $this->get_field_id('plesso_id'); ?>" 
                    name="<?php echo $this->get_field_name('plesso_id'); ?>">
                <option value="">Tutti i plessi</option>
                <?php foreach ($plessi as $plesso): ?>
                    <option value="<?php echo $plesso->id; ?>" <?php selected($plesso_id, $plesso->id); ?>>
                        <?php echo esc_html($plesso->descrizione); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }
    
    /**
     * Salva impostazioni
     */
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');
        $instance['limit'] = absint($new_instance['limit'] ?? 5);
        $instance['plesso_id'] = absint($new_instance['plesso_id'] ?? 0);
        
        return $instance;
    }
}

// Registra widget
add_action('widgets_init', function() {
    register_widget('SchoolCalendar\CalendarWidget');
});
