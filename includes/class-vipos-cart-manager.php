<?php
/**
 * VIPOS Cart Manager Class
 * 
 * Manages shopping cart for POS operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_Cart_Manager {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Session key for POS cart
     */
    private $session_key = 'vipos_cart';
    
    /**
     * Current cart data
     */
    private $cart_data = null;
    
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
        $this->load_cart();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_vipos_add_to_cart', array($this, 'handle_add_to_cart'));
        add_action('wp_ajax_vipos_update_cart_item', array($this, 'handle_update_cart_item'));
        add_action('wp_ajax_vipos_remove_cart_item', array($this, 'handle_remove_cart_item'));
        add_action('wp_ajax_vipos_clear_cart', array($this, 'handle_clear_cart'));
        add_action('wp_ajax_vipos_apply_discount', array($this, 'handle_apply_discount'));
        add_action('wp_ajax_vipos_remove_discount', array($this, 'handle_remove_discount'));
        add_action('wp_ajax_vipos_set_customer', array($this, 'handle_set_customer'));
    }
    
    /**
     * Load cart from session
     */
    private function load_cart() {
        $this->cart_data = VIPOS_Core::instance()->get_session($this->session_key);
        
        if (empty($this->cart_data)) {
            $this->cart_data = $this->get_empty_cart();
        }
    }
    
    /**
     * Save cart to session
     */
    private function save_cart() {
        VIPOS_Core::instance()->set_session($this->session_key, $this->cart_data);
    }
    
    /**
     * Get empty cart structure
     */
    private function get_empty_cart() {
        return array(
            'items' => array(),
            'customer_id' => 0,
            'discount_type' => '',
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'subtotal' => 0,
            'total' => 0,
            'notes' => '',
            'created_at' => current_time('mysql')
        );
    }
    
    /**
     * Get current cart
     */
    public function get_current_cart() {
        $this->calculate_totals();
        return $this->cart_data;
    }
    
    /**
     * Add item to cart
     */
    public function add_item($product_id, $quantity = 1, $variation_id = 0, $variation_data = array()) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            throw new Exception(__('Product not found', 'vipos'));
        }
        
        // Handle variation products
        if ($variation_id > 0) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                throw new Exception(__('Product variation not found', 'vipos'));
            }
            $product = $variation;
            $product_id = $variation_id;
        }
        
        // Check stock
        if (!$product->is_in_stock()) {
            throw new Exception(__('Product is out of stock', 'vipos'));
        }
        
        if ($product->get_manage_stock()) {
            $stock_quantity = $product->get_stock_quantity();
            if ($stock_quantity < $quantity) {
                throw new Exception(sprintf(__('Only %d items available in stock', 'vipos'), $stock_quantity));
            }
        }
        
        // Create cart item key
        $cart_item_key = $this->generate_cart_item_key($product_id, $variation_id, $variation_data);
        
        // Check if item already exists in cart
        if (isset($this->cart_data['items'][$cart_item_key])) {
            $existing_quantity = $this->cart_data['items'][$cart_item_key]['quantity'];
            $new_quantity = $existing_quantity + $quantity;
            
            // Check stock for new total quantity
            if ($product->get_manage_stock() && $product->get_stock_quantity() < $new_quantity) {
                throw new Exception(sprintf(__('Cannot add more items. Only %d available in stock', 'vipos'), $product->get_stock_quantity()));
            }
            
            $this->cart_data['items'][$cart_item_key]['quantity'] = $new_quantity;
        } else {
            // Add new item
            $this->cart_data['items'][$cart_item_key] = array(
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'variation_data' => $variation_data,
                'quantity' => $quantity,
                'price' => $product->get_price(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                'tax_class' => $product->get_tax_class(),
                'added_at' => current_time('mysql')
            );
        }
        
        $this->calculate_totals();
        $this->save_cart();
        
        return $cart_item_key;
    }
    
    /**
     * Update cart item quantity
     */
    public function update_item_quantity($cart_item_key, $quantity) {
        if (!isset($this->cart_data['items'][$cart_item_key])) {
            throw new Exception(__('Cart item not found', 'vipos'));
        }
        
        if ($quantity <= 0) {
            return $this->remove_item($cart_item_key);
        }
        
        $item = $this->cart_data['items'][$cart_item_key];
        $product_id = $item['variation_id'] > 0 ? $item['variation_id'] : $item['product_id'];
        $product = wc_get_product($product_id);
        
        if (!$product) {
            throw new Exception(__('Product not found', 'vipos'));
        }
        
        // Check stock
        if ($product->get_manage_stock() && $product->get_stock_quantity() < $quantity) {
            throw new Exception(sprintf(__('Only %d items available in stock', 'vipos'), $product->get_stock_quantity()));
        }
        
        $this->cart_data['items'][$cart_item_key]['quantity'] = $quantity;
        
        $this->calculate_totals();
        $this->save_cart();
        
        return true;
    }
    
    /**
     * Remove item from cart
     */
    public function remove_item($cart_item_key) {
        if (!isset($this->cart_data['items'][$cart_item_key])) {
            throw new Exception(__('Cart item not found', 'vipos'));
        }
        
        unset($this->cart_data['items'][$cart_item_key]);
        
        $this->calculate_totals();
        $this->save_cart();
        
        return true;
    }
    
    /**
     * Clear cart
     */
    public function clear_cart() {
        $this->cart_data = $this->get_empty_cart();
        $this->save_cart();
        
        return true;
    }
    
    /**
     * Apply discount
     */
    public function apply_discount($type, $value) {
        $valid_types = array('percentage', 'fixed');
        
        if (!in_array($type, $valid_types)) {
            throw new Exception(__('Invalid discount type', 'vipos'));
        }
        
        if ($value < 0) {
            throw new Exception(__('Discount value cannot be negative', 'vipos'));
        }
        
        if ($type === 'percentage' && $value > 100) {
            throw new Exception(__('Percentage discount cannot exceed 100%', 'vipos'));
        }
        
        $this->cart_data['discount_type'] = $type;
        $this->cart_data['discount_value'] = $value;
        
        $this->calculate_totals();
        $this->save_cart();
        
        return true;
    }
    
    /**
     * Remove discount
     */
    public function remove_discount() {
        $this->cart_data['discount_type'] = '';
        $this->cart_data['discount_value'] = 0;
        $this->cart_data['discount_amount'] = 0;
        
        $this->calculate_totals();
        $this->save_cart();
        
        return true;
    }
    
    /**
     * Set customer
     */
    public function set_customer($customer_id) {
        $this->cart_data['customer_id'] = intval($customer_id);
        
        $this->calculate_totals();
        $this->save_cart();
        
        return true;
    }
    
    /**
     * Calculate cart totals
     */
    private function calculate_totals() {
        $subtotal = 0;
        $tax_amount = 0;
        
        // Calculate subtotal
        foreach ($this->cart_data['items'] as $item) {
            $line_total = $item['price'] * $item['quantity'];
            $subtotal += $line_total;
            
            // Calculate tax for this item
            if (wc_tax_enabled()) {
                $tax_rates = WC_Tax::get_rates($item['tax_class']);
                $item_taxes = WC_Tax::calc_tax($line_total, $tax_rates, wc_prices_include_tax());
                $tax_amount += array_sum($item_taxes);
            }
        }
        
        $this->cart_data['subtotal'] = $subtotal;
        
        // Calculate discount
        $discount_amount = 0;
        if ($this->cart_data['discount_type'] && $this->cart_data['discount_value'] > 0) {
            if ($this->cart_data['discount_type'] === 'percentage') {
                $discount_amount = ($subtotal * $this->cart_data['discount_value']) / 100;
            } else {
                $discount_amount = min($this->cart_data['discount_value'], $subtotal);
            }
        }
        
        $this->cart_data['discount_amount'] = $discount_amount;
        $this->cart_data['tax_amount'] = $tax_amount;
        
        // Calculate total
        $total = $subtotal - $discount_amount;
        if (!wc_prices_include_tax()) {
            $total += $tax_amount;
        }
        
        $this->cart_data['total'] = max(0, $total);
    }
    
    /**
     * Generate cart item key
     */
    private function generate_cart_item_key($product_id, $variation_id = 0, $variation_data = array()) {
        $key_parts = array($product_id);
        
        if ($variation_id > 0) {
            $key_parts[] = $variation_id;
        }
        
        if (!empty($variation_data)) {
            ksort($variation_data);
            $key_parts[] = md5(serialize($variation_data));
        }
        
        return md5(implode('_', $key_parts));
    }
    
    /**
     * Handle add to cart AJAX
     */
    public function handle_add_to_cart() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']) ?: 1;
            $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
            $variation_data = isset($_POST['variation_data']) ? (array) $_POST['variation_data'] : array();
            
            $cart_item_key = $this->add_item($product_id, $quantity, $variation_id, $variation_data);
            
            wp_send_json_success(array(
                'cart_item_key' => $cart_item_key,
                'cart' => $this->get_current_cart(),
                'message' => __('Item added to cart', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle update cart item AJAX
     */
    public function handle_update_cart_item() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
            $quantity = intval($_POST['quantity']);
            
            $this->update_item_quantity($cart_item_key, $quantity);
            
            wp_send_json_success(array(
                'cart' => $this->get_current_cart(),
                'message' => __('Cart updated', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle remove cart item AJAX
     */
    public function handle_remove_cart_item() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
            
            $this->remove_item($cart_item_key);
            
            wp_send_json_success(array(
                'cart' => $this->get_current_cart(),
                'message' => __('Item removed from cart', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle clear cart AJAX
     */
    public function handle_clear_cart() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $this->clear_cart();
            
            wp_send_json_success(array(
                'cart' => $this->get_current_cart(),
                'message' => __('Cart cleared', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle apply discount AJAX
     */
    public function handle_apply_discount() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $type = sanitize_text_field($_POST['discount_type']);
            $value = floatval($_POST['discount_value']);
            
            $this->apply_discount($type, $value);
            
            wp_send_json_success(array(
                'cart' => $this->get_current_cart(),
                'message' => __('Discount applied', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle remove discount AJAX
     */
    public function handle_remove_discount() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $this->remove_discount();
            
            wp_send_json_success(array(
                'cart' => $this->get_current_cart(),
                'message' => __('Discount removed', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle set customer AJAX
     */
    public function handle_set_customer() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $customer_id = intval($_POST['customer_id']);
            
            $this->set_customer($customer_id);
            
            wp_send_json_success(array(
                'cart' => $this->get_current_cart(),
                'message' => __('Customer updated', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}
