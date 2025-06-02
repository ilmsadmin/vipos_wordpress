/**
 * VIPOS Layout Adjuster
 * 
 * This script enhances the POS layout for better space utilization
 */

(function($) {
    'use strict';

    const VIPOSLayout = {
        
        /**
         * Initialize the layout adjuster
         */
        init: function() {
            $(document).ready(this.adjustLayout);
            $(window).resize(this.adjustLayout);
            
            // Adjust specific elements
            this.setupCompactItemHandling();
            this.setupDynamicResizing();
            this.setupSearchImprovements();
            this.setupCartImprovements();
        },
        
        /**
         * Adjust the overall layout
         */
        adjustLayout: function() {
            // Adjust panels based on screen size
            const windowWidth = $(window).width();
            
            if (windowWidth < 1200) {
                $('.vipos-cart-panel').css('width', '35%');
                $('.vipos-products-panel').css('width', '65%');
            } else {
                $('.vipos-cart-panel').css('width', '30%');
                $('.vipos-products-panel').css('width', '70%');
            }
        },
        
        /**
         * Set up more compact cart item handling
         */
        setupCompactItemHandling: function() {
            // Adjust cart items display for space optimization
            $('.cart-item').each(function() {
                // Make cart item interactions more compact
                $(this).off('mouseenter').on('mouseenter', function() {
                    $(this).addClass('compact-hover');
                    $(this).find('.remove-item-btn').fadeIn(200);
                });
                
                $(this).off('mouseleave').on('mouseleave', function() {
                    $(this).removeClass('compact-hover');
                    $(this).find('.remove-item-btn').fadeOut(100);
                });
                
                // Initially hide remove buttons to save space
                $(this).find('.remove-item-btn').hide();
            });
            
            // Set up delegated events for newly added items
            $(document).off('mouseenter', '.cart-item').on('mouseenter', '.cart-item', function() {
                $(this).addClass('compact-hover');
                $(this).find('.remove-item-btn').fadeIn(200);
            });
            
            $(document).off('mouseleave', '.cart-item').on('mouseleave', '.cart-item', function() {
                $(this).removeClass('compact-hover');
                $(this).find('.remove-item-btn').fadeOut(100);
            });
        },
        
        /**
         * Set up dynamic resizing for various elements
         */
        setupDynamicResizing: function() {
            // Adjust customer selection area based on whether customer is selected
            if ($('.selected-customer').length) {
                $('.vipos-customer-section').addClass('has-customer');
            } else {
                $('.vipos-customer-section').removeClass('has-customer');
            }
            
            // Adjust panels based on content
            const cartItemsCount = $('.cart-items .cart-item').length;
            if (cartItemsCount > 5) {
                $('.cart-items').addClass('many-items');
                $('.cart-items').css('max-height', '300px');
            } else {
                $('.cart-items').removeClass('many-items');
                $('.cart-items').css('max-height', '');
            }
        },
        
        /**
         * Set up search improvements
         */
        setupSearchImprovements: function() {
            // Make search input always accessible
            const $searchInput = $('#product-search, #customer-search');
            
            // Add placeholder text that's more compact
            $('#product-search').attr('placeholder', 'Search products (Alt+P)');
            $('#customer-search').attr('placeholder', 'Search customers (Alt+C)');
            
            // Add keyboard shortcuts
            $(document).off('keydown.vipos.search').on('keydown.vipos.search', function(e) {
                // Alt+P for product search
                if (e.altKey && e.which === 80) {
                    $('#product-search').focus();
                    e.preventDefault();
                }
                
                // Alt+C for customer search
                if (e.altKey && e.which === 67) {
                    $('#customer-search').focus();
                    e.preventDefault();
                }
            });
        },
        
        /**
         * Set up cart improvements
         */
        setupCartImprovements: function() {
            // Add dynamic labels to make buttons more compact
            $('#checkout-btn').html('<i class="dashicons dashicons-cart"></i> Checkout');
            $('#apply-discount').html('<i class="dashicons dashicons-yes"></i> Apply');
            
            // Make discount section collapsible to save space
            const $discountSection = $('.discount-section');
            const $discountTitle = $discountSection.find('h4');
            
            $discountTitle.addClass('collapsible').prepend('<span class="toggle-icon">+</span> ');
            const $discountContent = $discountSection.find('.discount-input-group, .discount-actions');
            
            // Initially hide discount content to save space
            $discountContent.hide();
            
            // Toggle discount section on click
            $discountTitle.off('click').on('click', function() {
                $discountContent.slideToggle(200);
                const $icon = $(this).find('.toggle-icon');
                $icon.text($icon.text() === '+' ? '-' : '+');
            });
        }
    };
    
    // Initialize the layout adjuster
    VIPOSLayout.init();
    
})(jQuery);
