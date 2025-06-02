<?php
/**
 * VIPOS Core Class
 *
 * @package VIPOS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_Core {
    
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
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        
        // Security hooks
        add_action('wp_ajax_nopriv_vipos_check_nonce', array($this, 'check_nonce'));
        add_action('wp_ajax_vipos_check_nonce', array($this, 'check_nonce'));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on VIPOS pages
        if (strpos($hook, 'vipos') === false) {
            return;
        }
        
        // JavaScript
        wp_enqueue_script(
            'vipos-admin',
            VIPOS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            VIPOS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('vipos-admin', 'vipos_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vipos_admin_nonce'),
            'strings' => array(
                'error' => __('An error occurred. Please try again.', 'vipos'),
                'success' => __('Operation completed successfully.', 'vipos'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'vipos'),
                'loading' => __('Loading...', 'vipos'),
            )
        ));
    }
    
    /**
     * Enqueue frontend scripts for POS interface
     */
    public function frontend_enqueue_scripts() {
        // Only load on POS pages
        if (!$this->is_pos_page()) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'vipos-pos',
            VIPOS_PLUGIN_URL . 'assets/css/pos.css',
            array(),
            VIPOS_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'vipos-pos',
            VIPOS_PLUGIN_URL . 'assets/js/pos.js',
            array('jquery', 'wp-api'),
            VIPOS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('vipos-pos', 'vipos_pos', array(
            'api_url' => rest_url('vipos/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currency' => array(
                'symbol' => get_woocommerce_currency_symbol(),
                'position' => get_option('woocommerce_currency_pos'),
                'decimal_separator' => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
                'decimals' => wc_get_price_decimals(),
            ),
            'settings' => array(
                'products_per_page' => get_option('vipos_pos_per_page', 20),
                'enable_customer_search' => get_option('vipos_enable_customer_search', 'yes'),
                'enable_discount' => get_option('vipos_enable_discount', 'yes'),
                'enable_tax' => get_option('vipos_enable_tax', 'yes'),
            ),
            'strings' => array(
                'add_to_cart' => __('Add to Cart', 'vipos'),
                'remove_item' => __('Remove Item', 'vipos'),
                'checkout' => __('Checkout', 'vipos'),
                'total' => __('Total', 'vipos'),
                'subtotal' => __('Subtotal', 'vipos'),
                'tax' => __('Tax', 'vipos'),
                'discount' => __('Discount', 'vipos'),
                'search_products' => __('Search products...', 'vipos'),
                'search_customers' => __('Search customers...', 'vipos'),
                'no_products_found' => __('No products found', 'vipos'),
                'cart_empty' => __('Cart is empty', 'vipos'),
                'error_adding_product' => __('Error adding product to cart', 'vipos'),
                'confirm_checkout' => __('Confirm checkout?', 'vipos'),
                'order_success' => __('Order created successfully!', 'vipos'),
                'order_error' => __('Error creating order', 'vipos'),
            )
        ));
    }
    
    /**
     * Check if current page is POS
     */
    public function is_pos_page() {
        return isset($_GET['page']) && $_GET['page'] === 'vipos';
    }
    
    /**
     * Verify nonce for security
     */
    public function verify_nonce($nonce, $action = 'vipos_nonce') {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Check user capabilities
     */
    public function check_capability($capability = 'vipos_access') {
        return current_user_can($capability);
    }
    
    /**
     * Sanitize input data
     */
    public function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($data);
            case 'url':
                return esc_url_raw($data);
            case 'int':
                return absint($data);
            case 'float':
                return floatval($data);
            case 'textarea':
                return sanitize_textarea_field($data);
            case 'html':
                return wp_kses_post($data);
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Log errors
     */
    public function log_error($message, $data = array()) {
        if (WP_DEBUG_LOG) {
            $log_message = sprintf(
                '[VIPOS] %s - %s',
                current_time('Y-m-d H:i:s'),
                $message
            );
            
            if (!empty($data)) {
                $log_message .= ' - Data: ' . print_r($data, true);
            }
            
            error_log($log_message);
        }
    }
    
    /**
     * Get formatted price
     */
    public function format_price($price) {
        return wc_price($price);
    }
    
    /**
     * Get product stock status
     */
    public function get_stock_status($product) {
        if (!$product instanceof WC_Product) {
            return 'outofstock';
        }
        
        $stock_status = $product->get_stock_status();
        $stock_quantity = $product->get_stock_quantity();
        
        if ($stock_status === 'outofstock') {
            return 'outofstock';
        }
        
        if ($product->managing_stock() && $stock_quantity !== null) {
            $low_stock_amount = get_option('woocommerce_notify_low_stock_amount', 2);
            if ($stock_quantity <= $low_stock_amount) {
                return 'lowstock';
            }
        }
        
        return 'instock';
    }
    
    /**
     * Generate session ID
     */
    public function generate_session_id() {
        return wp_generate_uuid4();
    }
    
    /**
     * Get current session
     */
    public function get_current_session() {
        $session_id = $this->get_session_id();
        if (!$session_id) {
            return null;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'vipos_sessions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %s AND user_id = %d",
            $session_id,
            get_current_user_id()
        ));
    }
    
    /**
     * Get session ID from cookie or create new
     */
    public function get_session_id() {
        $session_id = isset($_COOKIE['vipos_session_id']) ? $_COOKIE['vipos_session_id'] : null;
        
        if (!$session_id) {
            $session_id = $this->generate_session_id();
            setcookie('vipos_session_id', $session_id, time() + DAY_IN_SECONDS, '/');
        }
        
        return $session_id;
    }
    
    /**
     * Save session data
     */
    public function save_session($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'vipos_sessions';
        $session_id = $this->get_session_id();
        $user_id = get_current_user_id();
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE session_id = %s AND user_id = %d",
            $session_id,
            $user_id
        ));
        
        $data_json = json_encode($data);
        
        if ($existing) {
            return $wpdb->update(
                $table,
                array(
                    'cart_data' => $data_json,
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'session_id' => $session_id,
                    'user_id' => $user_id
                )
            );
        } else {
            return $wpdb->insert(
                $table,
                array(
                    'session_id' => $session_id,
                    'user_id' => $user_id,
                    'cart_data' => $data_json,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        }
    }
    
    /**
     * Clear old sessions
     */
    public function cleanup_old_sessions() {
        global $wpdb;
        $table = $wpdb->prefix . 'vipos_sessions';
        
        // Delete sessions older than 7 days
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE updated_at < %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
    }
      /**
     * Get session data by key
     */
    public function get_session($key = 'vipos_cart') {
        $session = $this->get_current_session();
        
        if (!$session) {
            return null;
        }
        
        $session_data = json_decode($session->cart_data, true);
        
        if (empty($session_data)) {
            return null;
        }
        
        return isset($session_data[$key]) ? $session_data[$key] : null;
    }
    
    /**
     * Set session data by key
     */
    public function set_session($key, $value) {
        $session = $this->get_current_session();
        $session_data = array();
        
        if ($session && !empty($session->cart_data)) {
            $session_data = json_decode($session->cart_data, true);
        }
        
        $session_data[$key] = $value;
        
        return $this->save_session($session_data);
    }

    /**
     * AJAX nonce check
     */
    public function check_nonce() {
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $action = isset($_POST['action_name']) ? $_POST['action_name'] : 'vipos_nonce';
        
        if (!$this->verify_nonce($nonce, $action)) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'vipos')
            ));
        }
        
        wp_send_json_success();
    }
    
    /**
     * Check WooCommerce functions availability
     */
    public function check_woocommerce_functions() {
        $required_functions = array(
            'wc_get_products',
            'wc_create_order',
            'wc_get_product',
            'wc_price',
            'get_woocommerce_currency_symbol'
        );
        
        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                $this->log_error("Required WooCommerce function not found: $function");
                return false;
            }
        }
        
        return true;
    }
}
