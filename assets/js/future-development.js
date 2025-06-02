/**
 * VIPOS Future Development JavaScript
 * Handles interactions for features under development
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        VIPOSFuture.init();
    });

    /**
     * Future Development Handler
     */
    window.VIPOSFuture = {
        
        /**
         * Initialize future development features
         */
        init: function() {
            this.setupDimmedInteractions();
            this.setupNotificationEffects();
            this.preventFormSubmissions();
        },
        
        /**
         * Setup interactions for dimmed content
         */
        setupDimmedInteractions: function() {
            // Add click handler to dimmed areas
            $(document).on('click', '.vipos-settings-dimmed, .vipos-content-dimmed', function(e) {
                e.preventDefault();
                e.stopPropagation();
                VIPOSFuture.showPhase2Notice();
            });
            
            // Prevent any form interactions in dimmed areas
            $(document).on('click keydown', '.vipos-settings-dimmed *, .vipos-content-dimmed *', function(e) {
                e.preventDefault();
                e.stopPropagation();
                VIPOSFuture.showPhase2Notice();
                return false;
            });
        },
        
        /**
         * Setup notification effects
         */
        setupNotificationEffects: function() {
            // Add pulse effect to future notices
            $('.vipos-future-notice').each(function() {
                const $notice = $(this);
                
                // Add breathing animation
                setInterval(function() {
                    $notice.css('transform', 'scale(1.02)');
                    setTimeout(function() {
                        $notice.css('transform', 'scale(1)');
                    }, 300);
                }, 3000);
            });
        },
        
        /**
         * Prevent form submissions in dimmed areas
         */
        preventFormSubmissions: function() {
            $('.vipos-settings-dimmed form, .vipos-content-dimmed form').on('submit', function(e) {
                e.preventDefault();
                VIPOSFuture.showPhase2Notice();
                return false;
            });
        },
        
        /**
         * Show Phase 2 development notice
         */
        showPhase2Notice: function() {
            // Remove any existing notices
            $('.vipos-temp-notice').remove();
            
            // Create notification
            const $notice = $('<div class="vipos-temp-notice">' +
                '<div class="vipos-temp-notice-content">' +
                    '<span class="dashicons dashicons-info"></span>' +
                    '<div class="vipos-temp-notice-text">' +
                        '<strong>Feature Under Development</strong><br>' +
                        'This functionality will be available in Phase 2 of VIPOS development.' +
                    '</div>' +
                    '<button class="vipos-temp-notice-close">&times;</button>' +
                '</div>' +
            '</div>');
            
            // Add to page
            $('body').append($notice);
            
            // Show with animation
            setTimeout(function() {
                $notice.addClass('vipos-temp-notice-show');
            }, 10);
            
            // Auto-hide after 4 seconds
            setTimeout(function() {
                VIPOSFuture.hidePhase2Notice($notice);
            }, 4000);
            
            // Close button handler
            $notice.find('.vipos-temp-notice-close').on('click', function() {
                VIPOSFuture.hidePhase2Notice($notice);
            });
        },
        
        /**
         * Hide Phase 2 notice
         */
        hidePhase2Notice: function($notice) {
            $notice.removeClass('vipos-temp-notice-show');
            setTimeout(function() {
                $notice.remove();
            }, 300);
        },
        
        /**
         * Add development badges to menu items
         */
        addDevelopmentBadges: function() {
            // Add badges to settings and reports menu items
            $('#toplevel_page_vipos .wp-submenu a[href*="vipos-settings"]').append(
                '<span class="vipos-future-badge">Phase 2</span>'
            );
            $('#toplevel_page_vipos .wp-submenu a[href*="vipos-reports"]').append(
                '<span class="vipos-future-badge">Phase 2</span>'
            );
        }
    };

})(jQuery);

// Add CSS for temporary notices
jQuery(document).ready(function($) {
    const tempNoticeCSS = `
        <style>
        .vipos-temp-notice {
            position: fixed;
            top: 50px;
            right: 20px;
            z-index: 999999;
            max-width: 350px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }
        
        .vipos-temp-notice-show {
            opacity: 1 !important;
            transform: translateX(0) !important;
        }
        
        .vipos-temp-notice-content {
            background: linear-gradient(135deg, #007cba, #005a87);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 124, 186, 0.3);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            position: relative;
        }
        
        .vipos-temp-notice .dashicons {
            font-size: 20px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .vipos-temp-notice-text {
            flex: 1;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .vipos-temp-notice-text strong {
            font-weight: 600;
            font-size: 15px;
        }
        
        .vipos-temp-notice-close {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s ease;
            flex-shrink: 0;
        }
        
        .vipos-temp-notice-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        @media (max-width: 768px) {
            .vipos-temp-notice {
                top: 30px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
            
            .vipos-temp-notice-content {
                padding: 12px 15px;
            }
        }
        </style>
    `;
    
    $('head').append(tempNoticeCSS);
});
