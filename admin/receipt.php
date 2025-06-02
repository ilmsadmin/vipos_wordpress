<?php
/**
 * VIPOS Receipt Template
 * 
 * @package VIPOS
 * @since 1.0.0
 */

// Allow direct access for receipt page
defined('ABSPATH') || defined('VIPOS_RECEIPT_PAGE') || exit;

// Debug receipt loading
if (WP_DEBUG) {
    error_log('VIPOS Receipt Template: Starting to render receipt');
}

// Receipt data should be passed to this template
$order = isset($order) ? $order : null;

// If order is not set, try to get it from the URL
if (!$order && isset($_GET['vipos_receipt'])) {
    $order_id = intval($_GET['vipos_receipt']);
    $order = wc_get_order($order_id);
    if (WP_DEBUG) {
        error_log('VIPOS Receipt Template: Retrieved order from URL, ID: ' . $order_id);
    }
}

// Make sure we can access settings
if (!class_exists('VIPOS_Settings')) {
    require_once dirname(__FILE__) . '/../admin/class-vipos-settings.php';
}

// Initialize settings
if (class_exists('VIPOS_Settings')) {
    $settings = VIPOS_Settings::instance();
} else {
    // Create a dummy settings object if the class isn't available
    if (WP_DEBUG) {
        error_log('VIPOS Receipt Template: VIPOS_Settings class not available');
    }
    $settings = new stdClass();
    $settings->get_setting = function($group, $key, $default) {
        return $default;
    };
}

// Bail if no order is available
if (!$order) {
    if (WP_DEBUG) {
        error_log('VIPOS Receipt Template: No order available!');
    }
    wp_die('Order not found. Cannot generate receipt.', 'Receipt Error');
    return;
}

// Debug order data
if (WP_DEBUG) {
    error_log('VIPOS Receipt Template: Order #' . $order->get_id() . ' loaded successfully');
}

// Get settings - fallback to direct option retrieval if settings class fails
try {
    $store_name = $settings->get_setting('general', 'store_name', get_bloginfo('name'));
    $store_address = $settings->get_setting('general', 'store_address', '');
    $receipt_header = $settings->get_setting('receipt', 'header', '');
    $receipt_footer = $settings->get_setting('receipt', 'footer', '');
    $show_sku = $settings->get_setting('receipt', 'show_sku', false);
    $receipt_width = $settings->get_setting('receipt', 'width', '80');
} catch (Exception $e) {
    // Fallback to direct option retrieval
    if (WP_DEBUG) {
        error_log('VIPOS Receipt Template: Error getting settings: ' . $e->getMessage());
    }
    $store_name = get_option('vipos_store_name', get_bloginfo('name'));
    $store_address = get_option('vipos_store_address', '');
    $receipt_header = get_option('vipos_receipt_header', '');
    $receipt_footer = get_option('vipos_receipt_footer', '');
    $show_sku = get_option('vipos_receipt_show_sku', false);
    $receipt_width = get_option('vipos_receipt_width', '80');
}

// Receipt styling based on width
$receipt_class = 'vipos-receipt-' . $receipt_width . 'mm';

// Set the page title
$page_title = sprintf(__('Receipt for Order #%s', 'vipos'), $order->get_order_number());
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($page_title); ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
            line-height: 1.4;
            color: #000;
        }
        
        .vipos-receipt {
            font-family: 'Courier New', monospace;
            background: white;
            margin: 0 auto;
            padding: 10px;
            line-height: 1.4;
            color: #000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 100%;
        }
        
        .vipos-receipt-58mm {
            width: 58mm;
            font-size: 11px;
        }
        
        .vipos-receipt-80mm {
            width: 80mm;
            font-size: 12px;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .store-name {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .store-address,
        .custom-header {
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        
        .receipt-order-info {
            margin-bottom: 10px;
            font-size: 0.9em;
        }
        
        .receipt-order-info > div {
            margin-bottom: 3px;
        }
        
        .receipt-separator {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        
        .items-header {
            display: flex;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        
        .items-header .item-name {
            flex: 2;
        }
        
        .items-header .item-qty,
        .items-header .item-price,
        .items-header .item-total {
            flex: 1;
            text-align: right;
        }
        
        .receipt-item {
            margin-bottom: 8px;
            font-size: 0.9em;
        }
        
        .item-details .item-name {
            margin-bottom: 2px;
        }
        
        .item-sku,
        .item-meta {
            font-size: 0.8em;
            color: #666;
        }
        
        .item-line {
            display: flex;
        }
        
        .item-line .item-qty,
        .item-line .item-price,
        .item-line .item-total {
            flex: 1;
            text-align: right;
        }
        
        .receipt-totals {
            font-size: 0.9em;
        }
        
        .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .total-line.discount .total-value {
            color: #dc3232;
        }
        
        .total-line.grand-total {
            border-top: 1px solid #000;
            padding-top: 3px;
            margin-top: 5px;
            font-size: 1.1em;
        }
        
        .receipt-coupons {
            margin-top: 10px;
            font-size: 0.9em;
        }
        
        .coupons-title {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .coupon-code {
            margin-left: 10px;
        }
        
        .receipt-footer,
        .receipt-thanks {
            text-align: center;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .receipt-thanks {
            font-weight: bold;
            font-size: 1em;
        }
        
        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .vipos-receipt {
                margin: 0;
                padding: 5mm;
                box-shadow: none;
            }
            
            .receipt-separator {
                border-top-style: solid;
            }
            
            .print-button {
                display: none !important;
            }
        }
        
        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background: #2271b1;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        
        .print-button:hover {
            background: #135e96;
        }
    </style>    <script>
        // Automatically open print dialog when the page loads
        window.onload = function() {
            // Add a small delay to ensure the page is fully rendered
            setTimeout(function() {
                try {
                    window.print();
                    console.log('Print dialog triggered automatically');
                } catch (e) {
                    console.error('Auto-print failed:', e);
                }
            }, 800); // Increased delay for better compatibility
        };
        
        // Print button functionality
        function printReceipt() {
            try {
                window.print();
            } catch (e) {
                console.error('Print failed:', e);
                alert('Printing failed. Please try using your browser\'s print function.');
            }
        }
    </script>
</head>
<body>    <div class="vipos-receipt <?php echo esc_attr($receipt_class); ?>" id="vipos-receipt-<?php echo $order->get_id(); ?>">
        <!-- Receipt Header -->
        <div class="receipt-header">
            <?php if ($store_name) : ?>
                <div class="store-name"><?php echo esc_html($store_name); ?></div>
            <?php endif; ?>
            
            <?php if ($store_address) : ?>
                <div class="store-address"><?php echo nl2br(esc_html($store_address)); ?></div>
            <?php endif; ?>
            
            <?php if ($receipt_header) : ?>
                <div class="custom-header"><?php echo nl2br(esc_html($receipt_header)); ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Order Information -->
        <div class="receipt-order-info">
            <div class="order-number">
                <strong><?php _e('Order #:', 'vipos'); ?></strong> <?php echo $order->get_order_number(); ?>
            </div>
            <div class="order-date">
                <strong><?php _e('Date:', 'vipos'); ?></strong> <?php echo $order->get_date_created() ? $order->get_date_created()->format(get_option('date_format') . ' ' . get_option('time_format')) : date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?>
            </div>
            <?php if ($order->get_customer_id()) : ?>
                <div class="customer-info">
                    <strong><?php _e('Customer:', 'vipos'); ?></strong> 
                    <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>
                    <?php if ($order->get_billing_email()) : ?>
                        <br><?php echo esc_html($order->get_billing_email()); ?>
                    <?php endif; ?>
                    <?php if ($order->get_billing_phone()) : ?>
                        <br><?php echo esc_html($order->get_billing_phone()); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php 
            // Get cashier information - try different meta keys
            $cashier_name = $order->get_meta('_vipos_cashier_name');
            
            // Fallback to other possible meta keys if the main one is empty
            if (empty($cashier_name)) {
                $cashier_name = $order->get_meta('_pos_cashier');
            }
            
            // Final fallback - use the user who created the order
            if (empty($cashier_name)) {
                $user_id = $order->get_customer_id();
                if ($user_id) {
                    $user_info = get_userdata($user_id);
                    if ($user_info) {
                        $cashier_name = $user_info->display_name;
                    }
                }
            }
            
            if (!empty($cashier_name)) : 
            ?>
                <div class="cashier-info">
                    <strong><?php _e('Cashier:', 'vipos'); ?></strong> <?php echo esc_html($cashier_name); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Separator -->
        <div class="receipt-separator"></div>
        
        <!-- Order Items -->
        <div class="receipt-items">
            <div class="items-header">
                <div class="item-name"><?php _e('Item', 'vipos'); ?></div>
                <div class="item-qty"><?php _e('Qty', 'vipos'); ?></div>
                <div class="item-price"><?php _e('Price', 'vipos'); ?></div>
                <div class="item-total"><?php _e('Total', 'vipos'); ?></div>            </div>
            
            <?php 
            $items = $order->get_items();
            if (!empty($items)) : 
                foreach ($items as $item_id => $item) : 
                    $product = $item->get_product();
                    $item_name = $item->get_name();
                    $quantity = $item->get_quantity();
                    $total = $item->get_total();
                    $price = $quantity > 0 ? $total / $quantity : 0;
            ?>
                <div class="receipt-item">
                    <div class="item-details">
                        <div class="item-name">
                            <?php echo esc_html($item_name); ?>
                            <?php if ($show_sku && $product && $product->get_sku()) : ?>
                                <br><small class="item-sku"><?php _e('SKU:', 'vipos'); ?> <?php echo esc_html($product->get_sku()); ?></small>
                            <?php endif; ?>
                            
                            <?php
                            // Show item meta (variations, etc.)
                            if (method_exists($item, 'get_formatted_meta_data')) {
                                $item_meta = $item->get_formatted_meta_data();
                                if (!empty($item_meta)) :
                                    foreach ($item_meta as $meta) :
                                        echo '<br><small class="item-meta">' . esc_html($meta->display_key) . ': ' . esc_html($meta->display_value) . '</small>';
                                    endforeach;
                                endif;
                            }
                            ?>
                        </div>
                    </div>
                    <div class="item-line">
                        <span class="item-qty"><?php echo $quantity; ?></span>
                        <span class="item-price"><?php echo wc_price($price); ?></span>
                        <span class="item-total"><?php echo wc_price($total); ?></span>
                    </div>
                </div>
            <?php 
                endforeach; 
            else : 
            ?>
                <div class="no-items"><?php _e('No items in this order', 'vipos'); ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Separator -->
        <div class="receipt-separator"></div>
        
        <!-- Order Totals -->
        <div class="receipt-totals">
            <div class="total-line">
                <span class="total-label"><?php _e('Subtotal:', 'vipos'); ?></span>
                <span class="total-value"><?php echo wc_price($order->get_subtotal()); ?></span>
            </div>
            
            <?php if ($order->get_total_discount() > 0) : ?>
                <div class="total-line discount">
                    <span class="total-label"><?php _e('Discount:', 'vipos'); ?></span>
                    <span class="total-value">-<?php echo wc_price($order->get_total_discount()); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($order->get_shipping_total() > 0) : ?>
                <div class="total-line">
                    <span class="total-label"><?php _e('Shipping:', 'vipos'); ?></span>
                    <span class="total-value"><?php echo wc_price($order->get_shipping_total()); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($order->get_total_tax() > 0) : ?>
                <div class="total-line">
                    <span class="total-label"><?php _e('Tax:', 'vipos'); ?></span>
                    <span class="total-value"><?php echo wc_price($order->get_total_tax()); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="total-line grand-total">
                <span class="total-label"><strong><?php _e('Total:', 'vipos'); ?></strong></span>
                <span class="total-value"><strong><?php echo wc_price($order->get_total()); ?></strong></span>
            </div>
            
            <?php if ($order->get_payment_method_title()) : ?>
                <div class="total-line payment-method">
                    <span class="total-label"><?php _e('Payment:', 'vipos'); ?></span>
                    <span class="total-value"><?php echo esc_html($order->get_payment_method_title()); ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Applied Coupons -->
        <?php $coupons = $order->get_coupon_codes(); ?>
        <?php if (!empty($coupons)) : ?>
            <div class="receipt-coupons">
                <div class="coupons-title"><?php _e('Coupons Applied:', 'vipos'); ?></div>
                <?php foreach ($coupons as $coupon_code) : ?>
                    <div class="coupon-code"><?php echo esc_html($coupon_code); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Receipt Footer -->
        <?php if ($receipt_footer) : ?>
            <div class="receipt-separator"></div>
            <div class="receipt-footer">
                <?php echo nl2br(esc_html($receipt_footer)); ?>
            </div>
        <?php endif; ?>
        
        <!-- Thank You Message -->
        <div class="receipt-separator"></div>
        <div class="receipt-thanks">
            <?php _e('Thank you for your business!', 'vipos'); ?>
        </div>
    </div>
    
    <!-- Print Button (visible only on screen) -->
    <button class="print-button" onclick="printReceipt()"><?php _e('Print Receipt', 'vipos'); ?></button>
</body>
</html>
