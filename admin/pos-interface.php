<?php

/**
 * POS Interface Template
 *
 * @package VIPOS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check WooCommerce
if (!class_exists('WooCommerce')) {
?>
    <div class="wrap">
        <h1><?php _e('VIPOS - Point of Sale', 'vipos'); ?></h1>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('WooCommerce Required', 'vipos'); ?></strong><br>
                <?php _e('VIPOS requires WooCommerce to function. Please install and activate WooCommerce first.', 'vipos'); ?>
            </p>
            <p>
                <a href="<?php echo admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'); ?>" class="button button-primary">
                    <?php _e('Install WooCommerce', 'vipos'); ?>
                </a>
            </p>
        </div>
    </div>
<?php
    return;
}

// Get current user
$current_user = wp_get_current_user();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php _e('VIPOS - Point of Sale', 'vipos'); ?></title> <?php
                                                                    // Force enqueue POS assets
                                                                    wp_enqueue_style(
                                                                        'vipos-pos-style',
                                                                        VIPOS_PLUGIN_URL . 'assets/css/pos.css',
                                                                        array(),
                                                                        VIPOS_VERSION
                                                                    );
                                                                    wp_enqueue_script(
                                                                        'vipos-pos-script',
                                                                        VIPOS_PLUGIN_URL . 'assets/js/pos.js',
                                                                        array('jquery'),
                                                                        VIPOS_VERSION . '-' . time(), // Add timestamp for cache busting
                                                                        true
                                                                    );

                                                                    // Localize script data
                                                                    wp_localize_script('vipos-pos-script', 'vipos_ajax', array(
                                                                        'ajax_url' => admin_url('admin-ajax.php'),
                                                                        'nonce' => wp_create_nonce('vipos_nonce'),
                                                                        'currency_symbol' => get_woocommerce_currency_symbol(),
                                                                        'currency_position' => get_option('woocommerce_currency_pos'),
                                                                        'decimal_separator' => wc_get_price_decimal_separator(),
                                                                        'thousand_separator' => wc_get_price_thousand_separator(),
                                                                        'decimals' => wc_get_price_decimals(),
                                                                        'i18n' => array(
                                                                            'loading' => __('Loading...', 'vipos'),
                                                                            'error' => __('An error occurred. Please try again.', 'vipos'),
                                                                            'success' => __('Operation completed successfully.', 'vipos'),
                                                                            'confirm_delete' => __('Are you sure you want to delete this item?', 'vipos'),
                                                                            'cart_empty' => __('Cart is empty', 'vipos'),
                                                                            'checkout_success' => __('Order completed successfully', 'vipos'),
                                                                            'checkout_error' => __('Error processing checkout', 'vipos'),
                                                                        )
                                                                    ));

                                                                    // Load WordPress head but exclude admin bar
                                                                    remove_action('wp_head', '_admin_bar_bump_cb');
                                                                    wp_head();
                                                                    ?>
    <style>
        html {
            margin-top: 0 !important;
        }

        * html body {
            margin-top: 0 !important;
        }

        @media screen and (max-width: 782px) {
            html {
                margin-top: 0 !important;
            }

            * html body {
                margin-top: 0 !important;
            }
        }
    </style>
</head>

<body class="vipos-fullscreen">

    <div id="vipos-container" class="vipos-container">
        <!-- Header -->
        <header class="vipos-header">
            <div class="vipos-header-left">
                <div class="vipos-logo">
                    <h1><?php _e('VIPOS', 'vipos'); ?></h1>
                    <span class="vipos-version">v<?php echo VIPOS_VERSION; ?></span>
                </div>
            </div>

            <div class="vipos-header-center">
                <div class="vipos-user-info">
                    <span class="user-greeting">
                        <?php printf(__('Hello, %s', 'vipos'), $current_user->display_name); ?>
                    </span>
                    <span class="current-time" id="current-time"></span>
                </div>
            </div>
            <div class="vipos-header-right">
                <div class="vipos-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=vipos-settings'); ?>" class="button button-secondary" id="vipos-settings-btn">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Settings', 'vipos'); ?>
                    </a>
                    <a href="<?php echo admin_url('index.php'); ?>" class="button button-primary" id="back-to-admin">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php _e('Back to Admin', 'vipos'); ?>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="vipos-main">
            <!-- Left Panel: Cart -->
            <aside class="vipos-cart-panel">
                <!-- Product Search -->
                <div class="vipos-search-section">
                    <div class="search-input-wrapper"> <input
                            type="text"
                            id="product-search"
                            class="search-input"
                            placeholder="<?php esc_attr_e('Search products (Alt+P)...', 'vipos'); ?>"
                            autocomplete="off">
                    </div>
                    <div id="search-results" class="search-results" style="display: none;"></div>
                </div> <!-- Cart Items -->
                <div class="vipos-cart-section">
                    <h3 class="cart-title">
                        <?php _e('Cart', 'vipos'); ?>
                        <span class="cart-count" id="cart-count">0</span>
                        <button type="button" class="clear-cart-btn" id="clear-cart-btn" title="<?php esc_attr_e('Clear Cart', 'vipos'); ?>" style="display: none;">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </h3>

                    <div class="cart-items" id="cart-items">
                        <div class="cart-empty" id="cart-empty">
                            <div class="empty-icon">
                                <span class="dashicons dashicons-cart"></span>
                            </div>
                            <p><?php _e('Your cart is empty', 'vipos'); ?></p>
                            <small><?php _e('Search and add products to get started', 'vipos'); ?></small>
                        </div>
                    </div>
                </div>

                <!-- Cart Totals -->
                <div class="vipos-totals-section"> <!-- Discount Section -->
                    <div class="discount-section">
                        <h4 class="collapsible"><?php _e('Discount', 'vipos'); ?></h4>
                        <div class="discount-controls">
                            <div class="discount-input-group">
                                <input
                                    type="number"
                                    id="discount-amount"
                                    class="discount-input"
                                    placeholder="0"
                                    min="0"
                                    step="0.01">
                                <select id="discount-type" class="discount-type">
                                    <option value="percentage"><?php _e('%', 'vipos'); ?></option>
                                    <option value="fixed"><?php echo get_woocommerce_currency_symbol(); ?></option>
                                </select>
                                <button type="button" class="button apply-discount" id="apply-discount">
                                    <i class="dashicons dashicons-yes"></i> <?php _e('Apply', 'vipos'); ?>
                                </button>
                            </div>
                            <div class="discount-actions">
                                <button type="button" class="button button-small" id="remove-discount-btn" style="display: none;">
                                    <i class="dashicons dashicons-no"></i> <?php _e('Remove Discount', 'vipos'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Totals Display -->
                    <div class="totals-display">
                        <div class="totals-row subtotal-row">
                            <span class="label"><?php _e('Subtotal:', 'vipos'); ?></span>
                            <span class="amount" id="cart-subtotal"><?php echo wc_price(0); ?></span>
                        </div>
                        <div class="totals-row discount-row" id="discount-row" style="display: none;">
                            <span class="label"><?php _e('Discount:', 'vipos'); ?></span>
                            <span class="amount discount" id="cart-discount"><?php echo wc_price(0); ?></span>
                        </div>
                        <div class="totals-row tax-row">
                            <span class="label"><?php _e('Tax:', 'vipos'); ?></span>
                            <span class="amount" id="cart-tax"><?php echo wc_price(0); ?></span>
                        </div>
                        <div class="totals-row total-row">
                            <span class="label"><?php _e('Total:', 'vipos'); ?></span>
                            <span class="amount total" id="cart-total"><?php echo wc_price(0); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Checkout Section -->
                <div class="vipos-checkout-section"> <button
                        type="button"
                        class="checkout-btn"
                        id="checkout-btn"
                        disabled>
                        <span class="dashicons dashicons-cart"></span>
                        <?php _e('Checkout', 'vipos'); ?>
                    </button>

                    <div class="payment-methods" id="payment-methods" style="display: none;">
                        <h4><?php _e('Payment Method', 'vipos'); ?></h4>
                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="cash" checked>
                                <span><?php _e('Cash', 'vipos'); ?></span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="card">
                                <span><?php _e('Card', 'vipos'); ?></span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="transfer">
                                <span><?php _e('Bank Transfer', 'vipos'); ?></span>
                            </label>
                        </div>
                        <div class="checkout-actions">
                            <button type="button" class="button button-secondary" id="cancel-checkout">
                                <?php _e('Cancel', 'vipos'); ?>
                            </button>
                            <button type="button" class="button button-primary" id="confirm-checkout">
                                <?php _e('Confirm Payment', 'vipos'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Right Panel: Products -->
            <section class="vipos-products-panel"> <!-- Customer Search -->
                <div class="vipos-customer-section">
                    <div class="customer-search-wrapper">
                        <div class="customer-search-input-group">
                            <input
                                type="text"
                                id="customer-search"
                                class="search-input"
                                placeholder="<?php esc_attr_e('Search customers (Alt+C)...', 'vipos'); ?>"
                                autocomplete="off">
                            <button type="button" class="add-customer-btn" id="add-customer-btn" title="<?php esc_attr_e('Add New Customer', 'vipos'); ?>">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php _e('Add Customer', 'vipos'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="selected-customer" id="selected-customer" style="display: none;">
                        <div class="customer-info">
                            <span class="customer-name-phone"></span>
                        </div>
                        <button type="button" class="remove-customer" title="<?php esc_attr_e('Remove Customer', 'vipos'); ?>">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    <div id="customer-results" class="customer-results" style="display: none;"></div>
                </div>

                <!-- Add Customer Modal -->
                <div id="add-customer-modal" class="vipos-modal" style="display: none;">
                    <div class="vipos-modal-content">
                        <div class="vipos-modal-header">
                            <h3><?php _e('Add New Customer', 'vipos'); ?></h3>
                            <button type="button" class="vipos-modal-close" id="close-customer-modal">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <div class="vipos-modal-body">
                            <form id="add-customer-form">
                                <div class="customer-form-section">
                                    <h4><?php _e('Basic Information', 'vipos'); ?></h4>
                                    <div class="form-row">
                                        <div class="form-field">
                                            <label for="customer-first-name"><?php _e('First Name', 'vipos'); ?> <span class="required">*</span></label>
                                            <input type="text" id="customer-first-name" name="first_name" required>
                                        </div>
                                        <div class="form-field">
                                            <label for="customer-last-name"><?php _e('Last Name', 'vipos'); ?> <span class="required">*</span></label>
                                            <input type="text" id="customer-last-name" name="last_name" required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-field">
                                            <label for="customer-email"><?php _e('Email', 'vipos'); ?> <span class="required">*</span></label>
                                            <input type="email" id="customer-email" name="email" required>
                                        </div>
                                        <div class="form-field">
                                            <label for="customer-phone"><?php _e('Phone', 'vipos'); ?></label>
                                            <input type="tel" id="customer-phone" name="phone">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-field">
                                            <label for="customer-company"><?php _e('Company', 'vipos'); ?></label>
                                            <input type="text" id="customer-company" name="billing_company">
                                        </div>
                                    </div>
                                </div>

                                <div class="customer-form-section">
                                    <h4><?php _e('Billing Address', 'vipos'); ?></h4>
                                    <div class="form-row">
                                        <div class="form-field">
                                            <label for="billing-address-1"><?php _e('Address Line 1', 'vipos'); ?></label>
                                            <input type="text" id="billing-address-1" name="billing_address_1">
                                        </div>
                                        <div class="form-field">
                                            <label for="billing-address-2"><?php _e('Address Line 2', 'vipos'); ?></label>
                                            <input type="text" id="billing-address-2" name="billing_address_2">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-field">
                                            <label for="billing-city"><?php _e('City', 'vipos'); ?></label>
                                            <input type="text" id="billing-city" name="billing_city">
                                        </div>
                                        <div class="form-field">
                                            <label for="billing-state"><?php _e('State/Province', 'vipos'); ?></label>
                                            <input type="text" id="billing-state" name="billing_state">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-field">
                                            <label for="billing-postcode"><?php _e('Postal Code', 'vipos'); ?></label>
                                            <input type="text" id="billing-postcode" name="billing_postcode">
                                        </div>
                                        <div class="form-field">
                                            <label for="billing-country"><?php _e('Country', 'vipos'); ?></label>
                                            <select id="billing-country" name="billing_country">
                                                <option value=""><?php _e('Select Country', 'vipos'); ?></option>
                                                <?php
                                                // Get WooCommerce countries if available
                                                if (class_exists('WC')) {
                                                    $countries = WC()->countries->get_countries();
                                                    foreach ($countries as $code => $name) {
                                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                                    }
                                                } else {
                                                    // Basic country list
                                                    $basic_countries = array(
                                                        'US' => 'United States',
                                                        'CA' => 'Canada',
                                                        'GB' => 'United Kingdom',
                                                        'AU' => 'Australia',
                                                        'DE' => 'Germany',
                                                        'FR' => 'France',
                                                        'IT' => 'Italy',
                                                        'ES' => 'Spain',
                                                        'NL' => 'Netherlands',
                                                        'JP' => 'Japan',
                                                    );
                                                    foreach ($basic_countries as $code => $name) {
                                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="vipos-modal-footer">
                            <button type="button" class="button" id="cancel-customer-btn"><?php _e('Cancel', 'vipos'); ?></button>
                            <button type="button" class="button button-primary" id="save-customer-btn">
                                <span class="loading-spinner" style="display: none;"></span>
                                <?php _e('Create Customer', 'vipos'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Product Categories Filter -->
                <div class="vipos-filters-section">
                    <div class="category-filters"> <button type="button" class="category-filter active" data-category-id="0">
                            <?php _e('All Products', 'vipos'); ?>
                        </button>
                        <?php
                        // Get categories that have in-stock products
                        $categories = array();

                        // First get all product categories
                        $all_categories = get_terms(array(
                            'taxonomy' => 'product_cat',
                            'hide_empty' => true,
                        ));

                        if (!is_wp_error($all_categories) && !empty($all_categories)) {
                            // For each category, check if it has in-stock products
                            foreach ($all_categories as $category) {
                                // Query products in this category that are in stock
                                $args = array(
                                    'post_type' => 'product',
                                    'posts_per_page' => 1, // We only need to know if at least one exists
                                    'post_status' => 'publish',
                                    'meta_query' => array(
                                        array(
                                            'key' => '_stock_status',
                                            'value' => 'instock',
                                            'compare' => '=',
                                        )
                                    ),
                                    'tax_query' => array(
                                        array(
                                            'taxonomy' => 'product_cat',
                                            'field' => 'term_id',
                                            'terms' => $category->term_id,
                                        )
                                    )
                                );

                                $query = new WP_Query($args);

                                // If this category has at least one in-stock product, add it to our list
                                if ($query->have_posts()) {
                                    $categories[] = $category;
                                }

                                wp_reset_postdata();
                            }
                            // Display the categories with in-stock products
                            foreach ($categories as $category) {
                                printf(
                                    '<button type="button" class="category-filter" data-category-id="%d">%s</button>',
                                    $category->term_id,
                                    esc_html($category->name)
                                );
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="vipos-products-section">
                    <div class="products-grid" id="products-grid">
                        <div class="loading-spinner" id="products-loading">
                            <div class="spinner"></div>
                            <p><?php _e('Loading products...', 'vipos'); ?></p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="products-pagination" id="products-pagination" style="display: none;">
                        <button type="button" class="button" id="prev-page" disabled>
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <?php _e('Previous', 'vipos'); ?>
                        </button>
                        <span class="page-info">
                            <span id="current-page">1</span> / <span id="total-pages">1</span>
                        </span>
                        <button type="button" class="button" id="next-page">
                            <?php _e('Next', 'vipos'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loading-content">
            <div class="loading-spinner large">
                <div class="spinner"></div>
            </div>
            <p class="loading-text"><?php _e('Processing...', 'vipos'); ?></p>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div id="checkout-modal" class="vipos-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Checkout', 'vipos'); ?></h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="checkout-summary">
                    <h4><?php _e('Order Summary', 'vipos'); ?></h4>
                    <div class="checkout-totals">
                        <div class="checkout-total-row">
                            <span><?php _e('Total:', 'vipos'); ?></span>
                            <span id="checkout-total">0.00</span>
                        </div>
                    </div>
                </div>

                <div class="payment-method-section">
                    <h4><?php _e('Payment Method', 'vipos'); ?></h4>
                    <select id="payment-method" class="payment-method-select">
                        <option value="cash"><?php _e('Cash', 'vipos'); ?></option>
                        <option value="card"><?php _e('Credit/Debit Card', 'vipos'); ?></option>
                        <option value="bank_transfer"><?php _e('Bank Transfer', 'vipos'); ?></option>
                        <option value="other"><?php _e('Other', 'vipos'); ?></option>
                    </select>
                </div>

                <div class="order-notes-section">
                    <h4><?php _e('Order Notes', 'vipos'); ?></h4>
                    <textarea id="checkout-notes" rows="3" placeholder="<?php _e('Add notes for this order (optional)', 'vipos'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close"><?php _e('Cancel', 'vipos'); ?></button>
                <button type="button" class="button button-primary" id="process-checkout-btn"><?php _e('Complete Sale', 'vipos'); ?></button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="success-modal" class="vipos-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Order Completed', 'vipos'); ?></h3>
            </div>
            <div class="modal-body">
                <div class="success-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <p class="success-message"></p>
                <div class="order-details"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary" id="print-receipt">
                    <span class="dashicons dashicons-printer"></span>
                    <?php _e('Print Receipt', 'vipos'); ?>
                </button>
                <button type="button" class="button button-primary" id="new-order">
                    <?php _e('New Order', 'vipos'); ?>
                </button>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>

    <script>
        // Initialize POS when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof ViposApp !== 'undefined') {
                window.viposApp = new ViposApp();
            }

            // Update current time
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                const timeElement = document.getElementById('current-time');
                if (timeElement) {
                    timeElement.textContent = timeString;
                }
            }

            updateTime();
            setInterval(updateTime, 1000);
        });
    </script>

</body>

</html>