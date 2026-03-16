/**
 * School Calendar Admin JS
 */
(function($) {
    'use strict';
    
    window.SchoolCalendarAdmin = {
        
        apiUrl: scAdmin.apiUrl,
        nonce: scAdmin.nonce,
        
        /**
         * API request helper
         */
        request: function(endpoint, method, data) {
            return $.ajax({
                url: this.apiUrl + endpoint,
                method: method || 'GET',
                headers: {
                    'X-WP-Nonce': this.nonce
                },
                contentType: 'application/json',
                data: data ? JSON.stringify(data) : undefined
            });
        },
        
        /**
         * Show notice
         */
        notice: function(message, type) {
            type = type || 'success';
            var $notice = $('<div class="sc-notice sc-notice-' + type + '">' + message + '</div>');
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Confirm dialog
         */
        confirm: function(message) {
            return confirm(message);
        }
    };
    
    // Inizializzazione
    $(document).ready(function() {
        // Chiusura modal con ESC
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                $('.sc-modal').hide();
            }
        });
        
        // Chiusura modal click fuori
        $(document).on('click', '.sc-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
    });
    
})(jQuery);
