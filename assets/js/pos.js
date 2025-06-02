/**
 * VIPOS JavaScript for POS Interface
 */

(function($) {
    'use strict';

    // Global variables
    let viposData = {
        cart: {},
        products: [],
        customers: [],
        currentCustomer: null,
        currentPage: 1,
        searchTerm: '',
        selectedCategory: 0,
        isLoading: false
    };

    // Main POS object
    const VIPOS = {
        
        /**
         * Initialize POS interface
         */
        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Unbind previous events to prevent duplicates
            $(document).off('click.vipos');
            $(document).off('change.vipos');
            $(document).off('input.vipos');
            
            // Product search
            $('#product-search').off('input.vipos').on('input.vipos', this.debounce(this.searchProducts, 300));
            
            // Customer search
            $('#customer-search').off('input.vipos').on('input.vipos', this.debounce(this.searchCustomers, 300));
            $('#customer-search').off('focus.vipos').on('focus.vipos', function() {
                // Show results again on focus if there are results
                if (viposData.customers.length > 0) {
                    $('#customer-results').show();
                }
            });
              // Customer modal events
            $('#add-customer-btn').off('click.vipos').on('click.vipos', this.showAddCustomerModal);
            $('#save-customer-btn').off('click.vipos').on('click.vipos', this.saveNewCustomer);
            $('#cancel-customer-btn, #close-customer-modal').off('click.vipos').on('click.vipos', function() {
                $('#add-customer-modal').removeClass('active').hide();
            });
            
            // Hide customer results when clicking outside
            $(document).off('click.vipos.customer').on('click.vipos.customer', function(e) {
                if (!$(e.target).closest('.vipos-customer-section').length) {
                    $('#customer-results').hide();
                }
            });
            
            // Customer remove button
            $('.remove-customer').off('click.vipos').on('click.vipos', this.removeCustomer);
            
            // Category filter
            $(document).off('click.vipos', '.category-filter').on('click.vipos', '.category-filter', this.filterByCategory);
            
            // Product add to cart
            $(document).off('click.vipos', '.add-to-cart-btn, .add-to-cart-icon').on('click.vipos', '.add-to-cart-btn, .add-to-cart-icon', this.addToCart);              
            
            // Cart item actions
            $(document).off('click.vipos', '.remove-item-btn').on('click.vipos', '.remove-item-btn', this.removeFromCart);
            $(document).off('change.vipos', '.cart-quantity-input').on('change.vipos', '.cart-quantity-input', this.updateCartQuantity);
            $(document).off('click.vipos', '.increase-btn').on('click.vipos', '.increase-btn', this.increaseCartQuantity);
            $(document).off('click.vipos', '.decrease-btn').on('click.vipos', '.decrease-btn', this.decreaseCartQuantity);
              // Discount controls
            $('#apply-discount').off('click.vipos').on('click.vipos', this.applyDiscount);
            $('#remove-discount-btn').off('click.vipos').on('click.vipos', this.removeDiscount);
            
            // Customer selection
            $(document).off('click.vipos', '.customer-item').on('click.vipos', '.customer-item', this.selectCustomer);
            
            // Clear cart
            $('#clear-cart-btn').off('click.vipos').on('click.vipos', this.clearCart);            // Checkout
            $('#checkout-btn').off('click.vipos').on('click.vipos', this.showCheckoutModal);
            $('#process-checkout-btn').off('click.vipos').on('click.vipos', this.processCheckout);
            
            // Pagination
            $(document).off('click.vipos', '.page-btn').on('click.vipos', '.page-btn', this.changePage);
            
            // Modal close
            $('.modal-close, .vipos-modal').off('click.vipos').on('click.vipos', this.closeModal);
            
            // Keyboard shortcuts
            $(document).off('keydown.vipos').on('keydown.vipos', this.handleKeyboardShortcuts);
        },        /**
         * Load initial data
         */
        loadInitialData: function() {
            this.showLoading();
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_get_pos_data',
                    nonce: vipos_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Safely extract and store data with proper checks
                        if (response.data.products && response.data.products.products) {
                            viposData.products = response.data.products.products;
                        } else {
                            viposData.products = [];
                            console.warn('No product data received or invalid format');
                        }
                        
                        // Safely handle customers data
                        if (Array.isArray(response.data.customers)) {
                            viposData.customers = response.data.customers;
                        } else {
                            viposData.customers = [];
                            console.warn('No customer data received or invalid format');
                        }
                        
                        viposData.cart = response.data.cart || {};
                        
                        // Log initial cart data for debugging
                        console.log('Initial cart data:', viposData.cart);
                        console.log('Initial customers data:', viposData.customers);
                        
                        VIPOS.renderProducts(response.data.products);
                        
                        // Only render customers if we have valid data
                        if (Array.isArray(viposData.customers)) {
                            VIPOS.renderCustomers(viposData.customers);
                        }
                        
                        if (response.data.categories) {
                            VIPOS.renderCategories(response.data.categories);
                        }
                        
                        VIPOS.updateCartDisplay();
                    } else {
                        VIPOS.showError(response.data ? response.data.message : 'Error loading data');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading initial data:', error);
                    VIPOS.showError(vipos_ajax.i18n.error || 'Error loading data');
                },
                complete: function() {
                    VIPOS.hideLoading();
                }
            });
        },

        /**
         * Search products
         */
        searchProducts: function() {
            const searchTerm = $('#product-search').val();
            const categoryId = viposData.selectedCategory;
            
            viposData.searchTerm = searchTerm;
            viposData.currentPage = 1;
            
            VIPOS.loadProducts(searchTerm, categoryId, 1);
        },

        /**
         * Load products
         */
        loadProducts: function(searchTerm = '', categoryId = 0, page = 1) {
            if (viposData.isLoading) return;
            
            viposData.isLoading = true;
            this.showLoading('.products-grid');
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_search_products',
                    nonce: vipos_ajax.nonce,
                    search_term: searchTerm,
                    category_id: categoryId,
                    page: page
                },
                success: function(response) {
                    if (response.success) {
                        viposData.products = response.data.products;
                        VIPOS.renderProducts(response.data);
                    } else {
                        VIPOS.showError(response.data.message);
                    }
                },
                error: function() {
                    VIPOS.showError(vipos_ajax.i18n.error);
                },
                complete: function() {
                    viposData.isLoading = false;
                    VIPOS.hideLoading('.products-grid');
                }
            });
        },        /**
         * Search customers
         */
        searchCustomers: function() {
            const searchTerm = $('#customer-search').val();
            
            if (searchTerm.length < 2) {
                $('#customer-results').hide();
                return;
            }
            
            // Show loading state
            $('#customer-results').html('<div class="loading-customers">Searching...</div>').show();
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_search_customers',
                    nonce: vipos_ajax.nonce,
                    search_term: searchTerm,
                    limit: 20
                },
                success: function(response) {
                    // Log response for debugging
                    console.log('Customer search response:', response);
                      if (response && response.success && Array.isArray(response.data)) {
                        viposData.customers = response.data;
                        
                        if (response.data.length === 0) {
                            $('#customer-results').html('<div class="no-customers">No customers found</div>');
                        } else {
                            VIPOS.renderCustomers(response.data);
                            // Show the results container
                            $('#customer-results').show();
                        }
                    } else {
                        // Handle invalid response
                        $('#customer-results').html('<div class="no-customers">Error fetching customer data</div>');
                        console.error('Invalid customer search response:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Customer search error:', error);
                    $('#customer-results').html('<div class="no-customers">Error fetching customer data</div>');
                    VIPOS.showError('Error searching customers: ' + error);
                }
            });
        },

        /**
         * Filter products by category
         */
        filterByCategory: function(e) {
            e.preventDefault();
            
            const categoryId = $(this).data('category-id');
            viposData.selectedCategory = categoryId;
            viposData.currentPage = 1;
            
            $('.category-filter').removeClass('active');
            $(this).addClass('active');
            
            VIPOS.loadProducts(viposData.searchTerm, categoryId, 1);
        },

        /**
         * Add product to cart
         */
        addToCart: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const productId = $btn.data('product-id');
            const variationId = $btn.data('variation-id') || 0;
            const quantity = 1;
            
            $btn.prop('disabled', true).text(vipos_ajax.i18n.loading);
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_add_to_cart',
                    nonce: vipos_ajax.nonce,
                    product_id: productId,
                    variation_id: variationId,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        viposData.cart = response.data.cart;
                        // Log cart data for debugging
                        console.log('Cart data after add to cart:', viposData.cart);
                        VIPOS.updateCartDisplay();
                        VIPOS.showSuccess(response.data.message);
                    } else {
                        VIPOS.showError(response.data.message);
                    }
                },
                error: function() {
                    VIPOS.showError(vipos_ajax.i18n.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(vipos_ajax.i18n.add_to_cart);
                }
            });
        },        /**
         * Remove item from cart
         */
        removeFromCart: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const cartItemKey = $btn.data('cart-item-key');
            const $cartItem = $(`.cart-item[data-cart-item-key="${cartItemKey}"]`);
            
            // Disable button and show visual feedback immediately
            $btn.prop('disabled', true);
            $cartItem.addClass('removing');
            
            // Add a temporary loading indicator
            $cartItem.append('<div class="cart-item-loading">Removing...</div>');
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_remove_cart_item',
                    nonce: vipos_ajax.nonce,
                    cart_item_key: cartItemKey
                },
                success: function(response) {
                    if (response.success) {
                        // Animate the item removal before updating the cart display
                        $cartItem.fadeOut(300, function() {
                            // Update cart data and redraw the cart display
                            viposData.cart = response.data.cart;
                            VIPOS.updateCartDisplay();
                            VIPOS.showSuccess(response.data.message);
                        });
                    } else {
                        // Remove loading state if there was an error
                        $cartItem.removeClass('removing');
                        $cartItem.find('.cart-item-loading').remove();
                        $btn.prop('disabled', false);
                        VIPOS.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    // Remove loading state on error
                    $cartItem.removeClass('removing');
                    $cartItem.find('.cart-item-loading').remove();
                    $btn.prop('disabled', false);
                    VIPOS.showError('Error removing item: ' + error);
                }
            });
        },/**
         * Update cart item quantity
         */
        updateCartQuantity: function() {
            const $input = $(this);
            const cartItemKey = $input.data('cart-item-key');
            const quantity = parseInt($input.val());
            
            // Prevent multiple simultaneous calls
            if ($input.hasClass('updating')) return;
            
            if (quantity < 1) {
                $input.val(1);
                return;
            }
            
            // Store the current value to prevent re-rendering issues
            const prevValue = $input.data('prev-value') || 1;
            $input.data('prev-value', quantity);
            
            // Skip if no change
            if (quantity === prevValue) return;
            
            $input.addClass('updating');
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_update_cart_item',
                    nonce: vipos_ajax.nonce,
                    cart_item_key: cartItemKey,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        viposData.cart = response.data.cart;
                        // Only update totals, not the entire cart display
                        VIPOS.updateCartTotals();
                        $input.data('prev-value', quantity);
                    } else {
                        VIPOS.showError(response.data.message);
                        // Revert to previous value
                        $input.val(prevValue);
                        $input.data('prev-value', prevValue);
                    }
                },
                error: function() {
                    // Revert to previous value on error
                    $input.val(prevValue);
                    $input.data('prev-value', prevValue);
                },
                complete: function() {
                    $input.removeClass('updating');
                }
            });
        },/**
         * Increase cart quantity
         */
        increaseCartQuantity: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            if ($btn.hasClass('processing')) return;
            
            const cartItemKey = $btn.data('cart-item-key');
            const $input = $(`.cart-quantity-input[data-cart-item-key="${cartItemKey}"]`);
            const currentQty = parseInt($input.val()) || 0;
            const newQty = currentQty + 1;
            
            $btn.addClass('processing');
            $input.val(newQty);
            
            // Directly call updateCartQuantity with the input context
            VIPOS.updateCartQuantity.call($input[0]);
            
            setTimeout(() => $btn.removeClass('processing'), 500);
        },

        /**
         * Decrease cart quantity
         */
        decreaseCartQuantity: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            if ($btn.hasClass('processing')) return;
            
            const cartItemKey = $btn.data('cart-item-key');
            const $input = $(`.cart-quantity-input[data-cart-item-key="${cartItemKey}"]`);
            const currentQty = parseInt($input.val()) || 0;
            
            if (currentQty > 1) {
                const newQty = currentQty - 1;
                $btn.addClass('processing');
                $input.val(newQty);
                
                // Directly call updateCartQuantity with the input context
                VIPOS.updateCartQuantity.call($input[0]);
                
                setTimeout(() => $btn.removeClass('processing'), 500);
            }
        },

        /**
         * Apply discount
         */        applyDiscount: function(e) {
            e.preventDefault();
            
            const discountType = $('#discount-type').val();
            const discountValue = parseFloat($('#discount-amount').val());
            
            if (!discountValue || discountValue <= 0) {
                VIPOS.showError('Please enter a valid discount value');
                return;
            }
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_apply_discount',
                    nonce: vipos_ajax.nonce,
                    discount_type: discountType,
                    discount_value: discountValue
                },
                success: function(response) {
                    if (response.success) {                        viposData.cart = response.data.cart;
                        // Log cart data for debugging
                        console.log('Cart data after discount:', viposData.cart);
                        VIPOS.updateCartDisplay();
                        VIPOS.showSuccess(response.data.message);
                        $('#discount-amount').val('');
                    } else {
                        VIPOS.showError(response.data.message);
                    }
                }
            });
        },

        /**
         * Remove discount
         */
        removeDiscount: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_remove_discount',
                    nonce: vipos_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        viposData.cart = response.data.cart;
                        VIPOS.updateCartDisplay();
                        VIPOS.showSuccess(response.data.message);
                    }
                }
            });
        },        /**
         * Select customer
         */
        selectCustomer: function(e) {
            e.preventDefault();
            
            const $customerItem = $(this);
            const customerId = $customerItem.data('customer-id');
            
            if (!customerId) {
                VIPOS.showError('Invalid customer selection');
                return;
            }
            
            // Safely extract customer info with fallbacks
            const customerName = $customerItem.find('.customer-name').text() || 'Unknown Customer';
            let customerPhone = $customerItem.find('.customer-phone').text() || '';
            
            viposData.currentCustomer = {
                id: customerId,
                name: customerName,
                phone: customerPhone
            };
            
            // Log the selected customer for debugging
            console.log('Selected customer:', viposData.currentCustomer);
            
            // Update the customer info display
            $('#selected-customer').show();
            $('#selected-customer .customer-name-phone').text(customerName + (customerPhone ? ' (' + customerPhone + ')' : ''));
            
            // Hide search results and clear search
            $('#customer-search').val('');
            $('#customer-results').hide();
            
            // Update cart with customer
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_set_customer',
                    nonce: vipos_ajax.nonce,
                    customer_id: customerId
                },
                success: function(response) {
                    if (response.success) {
                        viposData.cart = response.data.cart;
                        console.log('Cart updated with customer:', viposData.cart);
                        VIPOS.updateCartDisplay();
                        VIPOS.showSuccess("Customer added to cart: " + customerName);
                    } else {
                        // Handle error
                        VIPOS.showError(response.data ? response.data.message : "Error adding customer to cart");
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error setting customer:', error);
                    VIPOS.showError("Error adding customer to cart: " + error);
                }
            });
        },/**
         * Remove customer from cart
         */
        removeCustomer: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Reset current customer
            viposData.currentCustomer = null;
            
            // Hide customer display
            $('#selected-customer').hide();
            $('#selected-customer .customer-name-phone').text('');
            
            // Update cart with no customer (guest checkout)
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_set_customer',
                    nonce: vipos_ajax.nonce,
                    customer_id: 0
                },
                success: function(response) {
                    if (response.success) {
                        viposData.cart = response.data.cart;
                        VIPOS.updateCartDisplay();
                        VIPOS.showSuccess("Customer removed from cart");
                    }
                }
            });
        },

        /**
         * Clear cart
         */
        clearCart: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear the cart?')) {
                return;
            }
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_clear_cart',
                    nonce: vipos_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        viposData.cart = response.data.cart;
                        viposData.currentCustomer = null;
                        VIPOS.updateCartDisplay();
                        $('#selected-customer').hide();
                        VIPOS.showSuccess(response.data.message);
                    }
                }
            });
        },        /**
         * Show checkout modal
         */
        showCheckoutModal: function(e) {
            e.preventDefault();
            
            // Validate cart has items
            if (!viposData.cart.items || Object.keys(viposData.cart.items).length === 0) {
                VIPOS.showError('Cart is empty');
                return;
            }
            
            // Reset form fields
            $('#checkout-notes').val('');
            $('#payment-method').val('cash');
            
            // Set the total
            $('#checkout-total').text(VIPOS.formatPrice(viposData.cart.total));
            
            // Show the modal
            $('#checkout-modal').addClass('active');
            
            // Focus on the payment method dropdown
            setTimeout(function() {
                $('#payment-method').focus();
            }, 100);
        },/**
         * Process checkout
         */
        processCheckout: function(e) {
            e.preventDefault();
            
            const paymentMethod = $('#payment-method').val();
            const notes = $('#checkout-notes').val();
            
            // Validate payment method
            if (!paymentMethod) {
                VIPOS.showError('Please select a payment method');
                return;
            }
              // Show loading state
            $('#process-checkout-btn').prop('disabled', true).html('<span class="processing-spinner"></span> Processing...');
            $('#checkout-modal').addClass('processing');
            
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_process_checkout',
                    nonce: vipos_ajax.nonce,
                    payment_method: paymentMethod,
                    notes: notes
                },
                success: function(response) {
                    if (response.success) {
                        // Format the success message with order details
                        const order = response.data.order;
                        const orderNumber = order.order_number;
                        const total = VIPOS.formatPrice(order.total);
                        
                        // Show success notification
                        VIPOS.showSuccess(`Order #${orderNumber} completed successfully.`);
                        
                        // Close checkout modal
                        VIPOS.closeModal();
                        
                        // Reset cart and customer
                        viposData.cart = { items: {}, total: 0, subtotal: 0, discount_amount: 0, tax_amount: 0 };
                        viposData.currentCustomer = null;
                        VIPOS.updateCartDisplay();
                        $('#selected-customer').hide();
                        
                        // Show receipt option if available
                        if (order.receipt_url) {
                            VIPOS.showReceiptOption(order);
                        }
                    } else {
                        VIPOS.showError(response.data.message || 'Error processing checkout');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Checkout error:', error);
                    VIPOS.showError('Error processing checkout: ' + error);
                },                complete: function() {
                    // Reset loading state
                    $('#process-checkout-btn').prop('disabled', false).text('Complete Sale');
                    $('#checkout-modal').removeClass('processing');
                }
            });
        },

        /**
         * Change page
         */
        changePage: function(e) {
            e.preventDefault();
            
            const page = $(this).data('page');
            viposData.currentPage = page;
            
            VIPOS.loadProducts(viposData.searchTerm, viposData.selectedCategory, page);
        },        /**
         * Close modal
         */
        closeModal: function(e) {
            // If called programmatically without event
            if (!e) {
                $('.vipos-modal').removeClass('active');
                $('#add-customer-modal').removeClass('active').hide();
                $('#checkout-modal').removeClass('active');
                return;
            }
            
            // If clicked on modal overlay or close button
            if (e.target === this || $(e.target).hasClass('modal-close')) {
                $('.vipos-modal').removeClass('active');
                $('#add-customer-modal').removeClass('active').hide();
                $('#checkout-modal').removeClass('active');
            }
        },/**
         * Handle keyboard shortcuts
         */        handleKeyboardShortcuts: function(e) {
            // ESC to close modals
            if (e.keyCode === 27) {
                $('.vipos-modal').removeClass('active');
                $('#add-customer-modal').removeClass('active').hide();
                $('#checkout-modal').removeClass('active');
            }
            
            // F1 for product search focus
            if (e.keyCode === 112) {
                e.preventDefault();
                $('#product-search').focus();
            }
            
            // F2 for customer search focus
            if (e.keyCode === 113) {
                e.preventDefault();
                $('#customer-search').focus();
            }
            
            // F10 for checkout
            if (e.keyCode === 121) {
                e.preventDefault();
                $('#checkout-btn').click();
            }
        },

        /**
         * Render products
         */
        renderProducts: function(data) {
            const $grid = $('.products-grid');
            const products = data.products || [];
            
            if (products.length === 0) {
                $grid.html('<div class="no-products">' + vipos_ajax.i18n.no_products_found + '</div>');
                return;
            }
            
            let html = '';
            products.forEach(function(product) {
                html += VIPOS.getProductHtml(product);
            });
            
            $grid.html(html);
            
            // Update pagination
            if (data.total_pages > 1) {
                VIPOS.renderPagination(data);
            }
        },        /**
         * Get product HTML
         */
        getProductHtml: function(product) {
            const imageUrl = product.image_url || '';
            const price = VIPOS.formatPrice(product.price);
            
            return `
                <div class="product-card" data-product-id="${product.id}">
                    <div class="product-image-wrapper">
                        <img src="${imageUrl}" alt="${product.name}" class="product-image" loading="lazy">
                    </div>
                    <div class="add-to-cart-icon" data-product-id="${product.id}"></div>
                    <div class="product-content">
                        <div class="product-name">${product.name}</div>
                        <div class="product-price-section">
                            <div class="product-price">${price}</div>
                        </div>
                    </div>
                </div>
            `;
        },        /**
         * Render customers
         */
        renderCustomers: function(customers) {
            const $list = $('#customer-results');
            
            // Safety check
            if (!Array.isArray(customers)) {
                console.error('Invalid customers data:', customers);
                $list.html('<div class="no-customers">Invalid customer data received</div>');
                return;
            }
            
            if (customers.length === 0) {
                $list.html('<div class="no-customers">' + (vipos_ajax.i18n.no_customers_found || 'No customers found') + '</div>');
                return;
            }
            
            let html = '';
            customers.forEach(function(customer) {
                // Safety checks for each customer property
                const id = customer.id || 0;
                const name = customer.name || 'Unknown';
                const email = customer.email || '';
                const phone = customer.phone || '';
                
                html += `
                    <div class="customer-item" data-customer-id="${id}">
                        <div class="customer-name">${name}</div>
                        <div class="customer-phone">${phone}</div>
                    </div>
                `;
            });
            
            $list.html(html);
            
            // Re-bind click event for newly rendered customer items
            $('.customer-item').on('click', VIPOS.selectCustomer);
        },

        /**
         * Render categories
         */
        renderCategories: function(categories) {
            const $filters = $('.category-filters');
            
            let html = '<button class="category-filter active" data-category-id="0">All Products</button>';
            
            categories.forEach(function(category) {
                html += `<button class="category-filter" data-category-id="${category.id}">${category.name}</button>`;
            });
            
            $filters.html(html);
        },

        /**
         * Update cart display
         */
        updateCartDisplay: function() {
            const cart = viposData.cart;
            const $cartItems = $('.cart-items');
            const $cartTotal = $('.cart-total');
            
            // Update cart items
            if (!cart.items || Object.keys(cart.items).length === 0) {
                $cartItems.html('<div class="empty-cart">Cart is empty</div>');
                $('#checkout-btn').prop('disabled', true);
            } else {
                let html = '';
                Object.keys(cart.items).forEach(function(key) {
                    const item = cart.items[key];
                    html += VIPOS.getCartItemHtml(key, item);
                });
                $cartItems.html(html);
                $('#checkout-btn').prop('disabled', false);
            }
            
            // Update totals
            const subtotal = cart.subtotal || 0;
            const discount = cart.discount_amount || 0;
            const tax = cart.tax_amount || 0;
            const total = cart.total || 0;
            
            $('#cart-subtotal').text(VIPOS.formatPrice(subtotal));
            // Display discount with a negative sign
            $('#cart-discount').text('-' + VIPOS.formatPrice(discount));
            $('#cart-tax').text(VIPOS.formatPrice(tax));
            $('#cart-total').text(VIPOS.formatPrice(total));
            
            // Show/hide discount row
            if (discount > 0) {
                $('#discount-row').show();
                $('#remove-discount-btn').show();
            } else {
                $('#discount-row').hide();
                $('#remove-discount-btn').hide();
            }
              // Update cart count
            const itemCount = Object.keys(cart.items || {}).length;
            $('.cart-count').text(itemCount);
        },

        /**
         * Update only cart totals without re-rendering items
         */
        updateCartTotals: function() {
            const cart = viposData.cart;
            
            // Update totals
            const subtotal = cart.subtotal || 0;
            const discount = cart.discount_amount || 0;
            const tax = cart.tax_amount || 0;
            const total = cart.total || 0;
            
            $('#cart-subtotal').text(VIPOS.formatPrice(subtotal));
            // Display discount with a negative sign
            $('#cart-discount').text('-' + VIPOS.formatPrice(discount));
            $('#cart-tax').text(VIPOS.formatPrice(tax));
            $('#cart-total').text(VIPOS.formatPrice(total));
            
            // Show/hide discount row
            if (discount > 0) {
                $('#discount-row').show();
                $('#remove-discount-btn').show();
            } else {
                $('#discount-row').hide();
                $('#remove-discount-btn').hide();
            }
            
            // Update cart count
            const itemCount = Object.keys(cart.items || {}).length;
            $('.cart-count').text(itemCount);
            
            // Update individual item totals
            Object.keys(cart.items || {}).forEach(function(key) {
                const item = cart.items[key];
                const lineTotal = item.price * item.quantity;
                $(`.cart-item[data-cart-item-key="${key}"] .cart-item-total`).text(VIPOS.formatPrice(lineTotal));
            });
        },        /**
         * Get cart item HTML
         */
        getCartItemHtml: function(key, item) {
            const lineTotal = item.price * item.quantity;
            
            return `
                <div class="cart-item" data-cart-item-key="${key}">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">${VIPOS.formatPrice(item.price)}</div>
                    <div class="cart-quantity-controls">
                        <button class="cart-quantity-btn decrease-btn" data-cart-item-key="${key}">-</button>
                        <input type="text" class="cart-quantity-input" 
                               value="${item.quantity}" min="1" 
                               data-cart-item-key="${key}"
                               data-prev-value="${item.quantity}">
                        <button class="cart-quantity-btn increase-btn" data-cart-item-key="${key}">+</button>
                    </div>
                    <div class="cart-item-total">${VIPOS.formatPrice(lineTotal)}</div>
                    <button class="remove-item-btn" data-cart-item-key="${key}">×</button>
                </div>
            `;
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            const $pagination = $('.pagination');
            const currentPage = data.current_page;
            const totalPages = data.total_pages;
            
            let html = '';
            
            // Previous button
            if (currentPage > 1) {
                html += `<button class="page-btn" data-page="${currentPage - 1}">‹</button>`;
            }
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                html += `<button class="page-btn ${activeClass}" data-page="${i}">${i}</button>`;
            }
            
            // Next button
            if (currentPage < totalPages) {
                html += `<button class="page-btn" data-page="${currentPage + 1}">›</button>`;
            }
            
            $pagination.html(html);
        },

        /**
         * Format price
         */
        formatPrice: function(amount) {
            const decimals = vipos_ajax.price_decimals || 2;
            const decimalSep = vipos_ajax.price_decimal_sep || '.';
            const thousandSep = vipos_ajax.price_thousand_sep || ',';
            const symbol = vipos_ajax.currency_symbol || '$';
            const position = vipos_ajax.currency_position || 'left';
            
            // Format number
            let formattedAmount = parseFloat(amount).toFixed(decimals);
            formattedAmount = formattedAmount.replace('.', decimalSep);
            
            // Add thousand separator
            if (thousandSep) {
                const parts = formattedAmount.split(decimalSep);
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
                formattedAmount = parts.join(decimalSep);
            }
            
            // Add currency symbol
            switch (position) {
                case 'left':
                    return symbol + formattedAmount;
                case 'right':
                    return formattedAmount + symbol;
                case 'left_space':
                    return symbol + ' ' + formattedAmount;
                case 'right_space':
                    return formattedAmount + ' ' + symbol;
                default:
                    return symbol + formattedAmount;
            }
        },

        /**
         * Show loading
         */
        showLoading: function(selector = '.pos-container') {
            $(selector).addClass('loading');
        },

        /**
         * Hide loading
         */
        hideLoading: function(selector = '.pos-container') {
            $(selector).removeClass('loading');
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotification(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotification(message, 'error');
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            const $notification = $(`
                <div class="notification notification-${type}">
                    <span class="notification-message">${message}</span>
                    <button class="notification-close">×</button>
                </div>
            `);
            
            $('.notifications').append($notification);
            
            // Auto remove after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual close
            $notification.find('.notification-close').on('click', function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            });
        },        /**
         * Show receipt option and automatically print it
         */
        showReceiptOption: function(order) {
            // Notify user about successful order and automatic printing
            VIPOS.showSuccess(`Order #${order.order_number} completed successfully. Printing receipt...`);
            
            // Open the receipt in a new window and automatically print it
            const receiptWindow = window.open(order.receipt_url, '_blank');
            
            // Try to trigger print dialog automatically when receipt is loaded
            if (receiptWindow) {
                receiptWindow.onload = function() {
                    try {
                        receiptWindow.print();
                    } catch (e) {
                        console.error('Auto-print failed:', e);
                    }
                };
            }
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },        /**
         * Show Add Customer Modal
         */
        showAddCustomerModal: function(e) {
            e.preventDefault();
            
            // Reset form
            $('#add-customer-form')[0].reset();
            
            // Show modal
            $('#add-customer-modal').addClass('active').show();
        },
        
        /**
         * Save new customer
         */
        saveNewCustomer: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const $spinner = $btn.find('.loading-spinner');
            const $form = $('#add-customer-form');
            
            // Basic form validation
            const email = $('#customer-email').val();
            const firstName = $('#customer-first-name').val();
            const lastName = $('#customer-last-name').val();
            
            if (!email || !firstName || !lastName) {
                VIPOS.showError('Please fill in all required fields');
                return;
            }
            
            // Disable button and show spinner
            $btn.prop('disabled', true);
            $spinner.show();
            
            // Collect form data
            const formData = {};
            $form.find('input, select').each(function() {
                formData[$(this).attr('name')] = $(this).val();
            });
            
            // Send Ajax request to create customer
            $.ajax({
                url: vipos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vipos_create_customer',
                    nonce: vipos_ajax.nonce,
                    customer_data: formData
                },
                success: function(response) {
                    if (response.success) {
                        // Add customer to local data
                        viposData.customers.push(response.data.customer);
                        
                        // Select the new customer
                        viposData.currentCustomer = response.data.customer;
                        
                        // Update customer display
                        $('#selected-customer').show();
                        $('#selected-customer .customer-name-phone').text(response.data.customer.name + (response.data.customer.phone ? ' (' + response.data.customer.phone + ')' : ''));
                        
                        // Update cart with customer
                        $.ajax({
                            url: vipos_ajax.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'vipos_set_customer',
                                nonce: vipos_ajax.nonce,
                                customer_id: response.data.customer.id
                            },
                            success: function(cartResponse) {
                                if (cartResponse.success) {
                                    viposData.cart = cartResponse.data.cart;
                                    VIPOS.updateCartDisplay();
                                }
                            }                        });
                        
                        // Hide modal
                        $('#add-customer-modal').removeClass('active').hide();
                        
                        // Show success message
                        VIPOS.showSuccess(response.data.message);
                    } else {
                        VIPOS.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error creating customer:', error);
                    VIPOS.showError('Error creating customer: ' + error);
                },
                complete: function() {
                    // Re-enable button and hide spinner
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        VIPOS.init();
    });

    // Update clock
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        $('.current-time').text(timeString);
    }
    
    setInterval(updateClock, 1000);
    updateClock();

})(jQuery);
