<?php
/**
 * VIPOS Order Manager Class
 * 
 * Manages orders and checkout for POS operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_Order_Manager {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_vipos_process_checkout', array($this, 'handle_process_checkout'));
        add_action('wp_ajax_vipos_get_orders', array($this, 'handle_get_orders'));
        add_action('wp_ajax_vipos_get_order_details', array($this, 'handle_get_order_details'));
        add_action('wp_ajax_vipos_refund_order', array($this, 'handle_refund_order'));
        add_action('wp_ajax_vipos_print_receipt', array($this, 'handle_print_receipt'));
    }
    
    /**
     * Process checkout
     */
    public function process_checkout($cart_data, $payment_method, $customer_data = array(), $notes = '') {
        // Validate cart
        if (empty($cart_data['items'])) {
            throw new Exception(__('Cart is empty', 'vipos'));
        }
        
        // Validate payment method
        $valid_payment_methods = array('cash', 'card', 'bank_transfer', 'other');
        if (!in_array($payment_method, $valid_payment_methods)) {
            throw new Exception(__('Invalid payment method', 'vipos'));
        }
        
        // Start transaction
        $order = null;
        
        try {
            // Create WooCommerce order
            $order = wc_create_order();
            
            if (is_wp_error($order)) {
                throw new Exception(__('Failed to create order', 'vipos'));
            }
            
            // Set customer
            if (!empty($cart_data['customer_id'])) {
                $order->set_customer_id($cart_data['customer_id']);
            } elseif (!empty($customer_data)) {
                $this->set_order_customer_data($order, $customer_data);
            }
            
            // Add products to order
            foreach ($cart_data['items'] as $item) {
                $product_id = $item['variation_id'] > 0 ? $item['variation_id'] : $item['product_id'];
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    throw new Exception(sprintf(__('Product %s not found', 'vipos'), $product_id));
                }
                
                // Check stock again
                if (!$product->is_in_stock()) {
                    throw new Exception(sprintf(__('Product %s is out of stock', 'vipos'), $product->get_name()));
                }
                
                if ($product->get_manage_stock() && $product->get_stock_quantity() < $item['quantity']) {
                    throw new Exception(sprintf(__('Insufficient stock for %s', 'vipos'), $product->get_name()));
                }
                
                // Add item to order
                $order_item = $order->add_product($product, $item['quantity'], array(
                    'variation' => $item['variation_data']
                ));
                
                if (!$order_item) {
                    throw new Exception(sprintf(__('Failed to add %s to order', 'vipos'), $product->get_name()));
                }
            }
            
            // Apply discount
            if (!empty($cart_data['discount_amount']) && $cart_data['discount_amount'] > 0) {
                $discount_item = new WC_Order_Item_Fee();
                $discount_item->set_name(__('POS Discount', 'vipos'));
                $discount_item->set_amount(-$cart_data['discount_amount']);
                $discount_item->set_tax_status('none');
                $discount_item->set_total(-$cart_data['discount_amount']);
                $order->add_item($discount_item);
            }
            
            // Set order meta
            $order->add_meta_data('_vipos_order', true);
            $order->add_meta_data('_vipos_payment_method', $payment_method);
            $order->add_meta_data('_vipos_cashier_id', get_current_user_id());
            $order->add_meta_data('_vipos_cashier_name', wp_get_current_user()->display_name);
            $order->add_meta_data('_vipos_created_at', current_time('mysql'));
            
            if (!empty($notes)) {
                $order->add_meta_data('_vipos_notes', sanitize_textarea_field($notes));
            }
            
            // Calculate totals
            $order->calculate_totals();
            
            // Set payment method
            $order->set_payment_method($payment_method);
            $order->set_payment_method_title($this->get_payment_method_title($payment_method));
            
            // Set order status based on payment method
            if ($payment_method === 'cash') {
                $order->set_status('completed');
                $order->payment_complete();
            } else {
                $order->set_status('processing');
            }
            
            // Add order note
            $order->add_order_note(sprintf(
                __('Order created via VIPOS by %s. Payment method: %s', 'vipos'),
                wp_get_current_user()->display_name,
                $this->get_payment_method_title($payment_method)
            ));
            
            // Save order
            $order->save();
            
            // Reduce stock
            wc_reduce_stock_levels($order->get_id());
            
            // Clear POS cart
            VIPOS_Cart_Manager::instance()->clear_cart();
            
            // Return order data
            return array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'order_key' => $order->get_order_key(),
                'total' => $order->get_total(),
                'status' => $order->get_status(),
                'payment_method' => $payment_method,
                'receipt_url' => $this->get_receipt_url($order->get_id())
            );
              } catch (Exception $e) {
            // Rollback on error
            if ($order && !is_wp_error($order)) {
                // Use HPOS-compatible deletion method
                $order->delete(true);
            }
            
            throw $e;
        }
    }
    
    /**
     * Set order customer data
     */
    private function set_order_customer_data($order, $customer_data) {
        $billing_data = array(
            'first_name' => $customer_data['first_name'] ?? '',
            'last_name' => $customer_data['last_name'] ?? '',
            'email' => $customer_data['email'] ?? '',
            'phone' => $customer_data['phone'] ?? '',
            'address_1' => $customer_data['address_1'] ?? '',
            'address_2' => $customer_data['address_2'] ?? '',
            'city' => $customer_data['city'] ?? '',
            'state' => $customer_data['state'] ?? '',
            'postcode' => $customer_data['postcode'] ?? '',
            'country' => $customer_data['country'] ?? wc_get_base_location()['country']
        );
        
        $order->set_address($billing_data, 'billing');
        $order->set_address($billing_data, 'shipping');
    }
    
    /**
     * Get payment method title
     */
    private function get_payment_method_title($payment_method) {
        $titles = array(        'cash' => __('Cash', 'vipos'),
            'card' => __('Card', 'vipos'),
            'bank_transfer' => __('Bank Transfer', 'vipos'),
            'other' => __('Other', 'vipos')
        );
        
        return $titles[$payment_method] ?? $payment_method;
    }
      /**
     * Get receipt URL
     */
    private function get_receipt_url($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('VIPOS: Cannot generate receipt URL - order not found: ' . $order_id);
            }
            return '';
        }
        
        // Log order data for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('VIPOS: Generating receipt URL for order ID: ' . $order_id);
            error_log('VIPOS: Order key: ' . $order->get_order_key());
        }
        
        // Add test parameter to help troubleshoot if needed
        return add_query_arg(array(
            'vipos_receipt' => $order_id,
            'key' => $order->get_order_key(),
            'ts' => time() // Add timestamp to prevent caching issues
        ), home_url('/'));
    }
    
    /**
     * Get POS orders
     */
    public function get_pos_orders($page = 1, $per_page = 20, $status = '', $date_from = '', $date_to = '') {
        $args = array(
            'type' => 'shop_order',
            'status' => $status ? array('wc-' . $status) : array_keys(wc_get_order_statuses()),
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_vipos_order',
            'meta_value' => true
        );
        
        // Add date filters
        if (!empty($date_from)) {
            $args['date_created'] = '>=' . $date_from;
        }
        
        if (!empty($date_to)) {
            $args['date_created'] = '<=' . $date_to;
        }
        
        $orders = wc_get_orders($args);
        $formatted_orders = array();
        
        foreach ($orders as $order) {
            $formatted_orders[] = $this->format_order_for_list($order);
        }
        
        // Get total count
        $count_args = $args;
        $count_args['limit'] = -1;
        $count_args['offset'] = 0;
        $count_args['fields'] = 'ids';
        $total_orders = count(wc_get_orders($count_args));
        
        return array(
            'orders' => $formatted_orders,
            'total_orders' => $total_orders,
            'total_pages' => ceil($total_orders / $per_page),
            'current_page' => $page
        );
    }
    
    /**
     * Format order for list
     */
    private function format_order_for_list($order) {
        return array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'date_created' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'customer_name' => $order->get_formatted_billing_full_name(),
            'payment_method' => $order->get_meta('_vipos_payment_method'),
            'cashier_name' => $order->get_meta('_vipos_cashier_name'),
            'items_count' => $order->get_item_count()
        );
    }
    
    /**
     * Get order details
     */
    public function get_order_details($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || !$order->get_meta('_vipos_order')) {
            throw new Exception(__('Order not found', 'vipos'));
        }
        
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'id' => $item->get_id(),
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name' => $item->get_name(),
                'sku' => $product ? $product->get_sku() : '',
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total() / $item->get_quantity(),
                'total' => $item->get_total(),
                'meta_data' => $item->get_formatted_meta_data()
            );
        }
        
        return array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'subtotal' => $order->get_subtotal(),
            'tax_total' => $order->get_total_tax(),
            'currency' => $order->get_currency(),
            'date_created' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'customer' => array(
                'id' => $order->get_customer_id(),
                'name' => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone()
            ),
            'billing_address' => $order->get_address('billing'),
            'payment_method' => $order->get_meta('_vipos_payment_method'),
            'payment_method_title' => $order->get_payment_method_title(),
            'cashier_name' => $order->get_meta('_vipos_cashier_name'),
            'notes' => $order->get_meta('_vipos_notes'),
            'items' => $items,
            'order_notes' => $this->get_order_notes($order_id)
        );
    }
    
    /**
     * Get order notes
     */
    private function get_order_notes($order_id) {
        $notes = wc_get_order_notes(array(
            'order_id' => $order_id,
            'order_by' => 'date_created',
            'order' => 'DESC'
        ));
        
        $formatted_notes = array();
        foreach ($notes as $note) {
            $formatted_notes[] = array(
                'id' => $note->id,
                'content' => $note->content,
                'date_created' => $note->date_created,
                'added_by' => $note->added_by
            );
        }
        
        return $formatted_notes;
    }
    
    /**
     * Handle process checkout AJAX
     */
    public function handle_process_checkout() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $cart_data = VIPOS_Cart_Manager::instance()->get_current_cart();
            $payment_method = sanitize_text_field($_POST['payment_method']);
            $customer_data = isset($_POST['customer_data']) ? (array) $_POST['customer_data'] : array();
            $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
            
            // Sanitize customer data
            if (!empty($customer_data)) {
                $customer_data = array_map('sanitize_text_field', $customer_data);
            }
            
            $result = $this->process_checkout($cart_data, $payment_method, $customer_data, $notes);
            
            wp_send_json_success(array(
                'order' => $result,
                'message' => __('Order completed successfully', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle get orders AJAX
     */
    public function handle_get_orders() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
            $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
            $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
            
            $orders = $this->get_pos_orders($page, $per_page, $status, $date_from, $date_to);
            
            wp_send_json_success($orders);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle get order details AJAX
     */
    public function handle_get_order_details() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $order_id = intval($_POST['order_id']);
            $order_details = $this->get_order_details($order_id);
            
            wp_send_json_success($order_details);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle refund order AJAX
     */
    public function handle_refund_order() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $order_id = intval($_POST['order_id']);
            $amount = floatval($_POST['amount']);
            $reason = sanitize_textarea_field($_POST['reason']);
            
            $order = wc_get_order($order_id);
            if (!$order || !$order->get_meta('_vipos_order')) {
                throw new Exception(__('Order not found', 'vipos'));
            }
            
            // Create refund
            $refund = wc_create_refund(array(
                'order_id' => $order_id,
                'amount' => $amount,
                'reason' => $reason
            ));
            
            if (is_wp_error($refund)) {
                throw new Exception($refund->get_error_message());
            }
            
            wp_send_json_success(array(
                'refund_id' => $refund->get_id(),
                'message' => __('Refund processed successfully', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle print receipt AJAX
     */
    public function handle_print_receipt() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $order_id = intval($_POST['order_id']);
            $receipt_html = $this->generate_receipt_html($order_id);
            
            wp_send_json_success(array(
                'receipt_html' => $receipt_html
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Generate receipt HTML
     */
    private function generate_receipt_html($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || !$order->get_meta('_vipos_order')) {
            throw new Exception(__('Order not found', 'vipos'));
        }
        
        ob_start();
        include VIPOS_PLUGIN_PATH . 'admin/templates/receipt.php';
        return ob_get_clean();
    }
}
