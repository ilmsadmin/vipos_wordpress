<?php
/**
 * VIPOS Debug Class
 * 
 * Provides debugging functionality for VIPOS
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_Debug {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Debug enabled flag
     */
    private $debug_enabled = false;
    
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
        $this->debug_enabled = (defined('WP_DEBUG') && WP_DEBUG) || (defined('VIPOS_DEBUG') && VIPOS_DEBUG);
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_vipos_debug_info', array($this, 'handle_debug_info'));
    }
    
    /**
     * Log a debug message
     */
    public function log($message, $context = '') {
        if (!$this->debug_enabled) {
            return;
        }
        
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        
        $prefix = empty($context) ? 'VIPOS Debug: ' : "VIPOS Debug [$context]: ";
        error_log($prefix . $message);
    }
    
    /**
     * Handle debug info AJAX request
     */
    public function handle_debug_info() {
        // Only allow administrators to use this endpoint
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $info = array(
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'vipos_version' => defined('VIPOS_VERSION') ? VIPOS_VERSION : 'unknown',
            'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown',
            'debug_enabled' => $this->debug_enabled,
            'ajax_url' => admin_url('admin-ajax.php'),
            'customer_hooks' => $this->check_customer_hooks(),
            'pos_hooks' => $this->check_pos_hooks(),
            'environment' => array(
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            )
        );
        
        wp_send_json_success($info);
    }
    
    /**
     * Check if customer hooks are registered
     */
    private function check_customer_hooks() {
        global $wp_filter;
        
        $hooks = array(
            'wp_ajax_vipos_search_customers' => isset($wp_filter['wp_ajax_vipos_search_customers']),
            'wp_ajax_vipos_create_customer' => isset($wp_filter['wp_ajax_vipos_create_customer']),
            'wp_ajax_vipos_update_customer' => isset($wp_filter['wp_ajax_vipos_update_customer']),
            'wp_ajax_vipos_get_customer_details' => isset($wp_filter['wp_ajax_vipos_get_customer_details']),
            'wp_ajax_vipos_get_customer_orders' => isset($wp_filter['wp_ajax_vipos_get_customer_orders']),
        );
        
        return $hooks;
    }
    
    /**
     * Check if POS hooks are registered
     */
    private function check_pos_hooks() {
        global $wp_filter;
        
        $hooks = array(
            'wp_ajax_vipos_get_pos_data' => isset($wp_filter['wp_ajax_vipos_get_pos_data']),
            'wp_ajax_vipos_search_products' => isset($wp_filter['wp_ajax_vipos_search_products']),
            'wp_ajax_vipos_search_customers' => isset($wp_filter['wp_ajax_vipos_search_customers']),
        );
        
        return $hooks;
    }
}

// Initialize debug instance
function vipos_debug() {
    return VIPOS_Debug::instance();
}

vipos_debug();
