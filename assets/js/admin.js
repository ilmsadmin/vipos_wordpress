/**
 * VIPOS Admin JavaScript
 * General admin functionality for VIPOS plugin
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        VIPOS_Admin.init();
    });

    /**
     * Main Admin Object
     */
    window.VIPOS_Admin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle admin notices dismissal
            $(document).on('click', '.vipos-admin-notice .notice-dismiss', this.dismissNotice);
            
            // Handle POS access button clicks
            $(document).on('click', '.vipos-pos-access', this.handlePOSAccess);
            
            // Handle quick actions
            $(document).on('click', '.vipos-quick-action', this.handleQuickAction);
            
            // Handle system status checks
            $(document).on('click', '.vipos-system-check', this.performSystemCheck);
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Simple tooltip implementation
            $('[data-vipos-tooltip]').hover(
                function() {
                    const tooltip = $('<div class="vipos-tooltip">' + $(this).data('vipos-tooltip') + '</div>');
                    $('body').append(tooltip);
                    
                    const pos = $(this).offset();
                    tooltip.css({
                        position: 'absolute',
                        top: pos.top - tooltip.outerHeight() - 5,
                        left: pos.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2),
                        background: '#333',
                        color: '#fff',
                        padding: '5px 10px',
                        borderRadius: '3px',
                        fontSize: '12px',
                        zIndex: 999999
                    });
                },
                function() {
                    $('.vipos-tooltip').remove();
                }
            );
        },
        
        /**
         * Dismiss admin notice
         */
        dismissNotice: function(e) {
            e.preventDefault();
            
            const $notice = $(this).closest('.vipos-admin-notice');
            const noticeId = $notice.data('notice-id');
            
            if (noticeId) {
                // Send AJAX request to dismiss notice
                $.post(vipos_admin.ajax_url, {
                    action: 'vipos_dismiss_notice',
                    notice_id: noticeId,
                    nonce: vipos_admin.nonce
                });
            }
            
            $notice.fadeOut();
        },
        
        /**
         * Handle POS access
         */
        handlePOSAccess: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const action = $button.data('action');
            
            if (action === 'open-pos') {
                // Open POS in new tab/window
                window.open($button.attr('href'), '_blank');
            }
        },
        
        /**
         * Handle quick actions
         */
        handleQuickAction: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const action = $button.data('action');
            
            // Add loading state
            $button.addClass('vipos-loading');
            
            // Send AJAX request
            $.post(vipos_admin.ajax_url, {
                action: 'vipos_quick_action',
                quick_action: action,
                nonce: vipos_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // Show success message
                    VIPOS_Admin.showMessage(response.data.message || vipos_admin.strings.success, 'success');
                } else {
                    // Show error message
                    VIPOS_Admin.showMessage(response.data.message || vipos_admin.strings.error, 'error');
                }
            })
            .fail(function() {
                VIPOS_Admin.showMessage(vipos_admin.strings.error, 'error');
            })
            .always(function() {
                $button.removeClass('vipos-loading');
            });
        },
        
        /**
         * Perform system check
         */
        performSystemCheck: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $results = $('#vipos-system-results');
            
            // Show loading state
            $button.addClass('vipos-loading');
            $results.html('<p>' + vipos_admin.strings.loading + '</p>');
            
            // Send AJAX request
            $.post(vipos_admin.ajax_url, {
                action: 'vipos_system_check',
                nonce: vipos_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $results.html(response.data.html);
                } else {
                    $results.html('<p class="error">' + (response.data.message || vipos_admin.strings.error) + '</p>');
                }
            })
            .fail(function() {
                $results.html('<p class="error">' + vipos_admin.strings.error + '</p>');
            })
            .always(function() {
                $button.removeClass('vipos-loading');
            });
        },
        
        /**
         * Show admin message
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            const $message = $('<div class="vipos-admin-notice ' + type + '">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>');
            
            // Insert after the first heading or at the top of the content
            const $target = $('.wrap h1').first();
            if ($target.length) {
                $target.after($message);
            } else {
                $('.wrap').prepend($message);
            }
            
            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },
        
        /**
         * Utility function to format currency
         */
        formatCurrency: function(amount) {
            if (typeof vipos_admin.currency_format !== 'undefined') {
                return vipos_admin.currency_format.replace('%s', amount);
            }
            return amount;
        }
    };

})(jQuery);
