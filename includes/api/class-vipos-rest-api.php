<?php
/**
 * VIPOS REST API Base Class
 * 
 * Base class for all REST API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_REST_API {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * API namespace
     */
    private $namespace = 'vipos/v1';
    
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
        add_action('rest_api_init', array($this, 'register_routes'));
    }
      /**
     * Register REST API routes
     */
    public function register_routes() {
        // Include API endpoint classes
        require_once VIPOS_PLUGIN_DIR . 'includes/api/class-vipos-products-api.php';
        require_once VIPOS_PLUGIN_DIR . 'includes/api/class-vipos-cart-api.php';
        require_once VIPOS_PLUGIN_DIR . 'includes/api/class-vipos-orders-api.php';
        require_once VIPOS_PLUGIN_DIR . 'includes/api/class-vipos-customers-api.php';
        
        // Initialize and register API endpoint classes
        $products_api = new VIPOS_Products_API();
        $products_api->register_routes();
        
        $cart_api = new VIPOS_Cart_API();
        $cart_api->register_routes();
        
        $orders_api = new VIPOS_Orders_API();
        $orders_api->register_routes();
        
        $customers_api = new VIPOS_Customers_API();
        $customers_api->register_routes();
        
        // Register base routes
        register_rest_route($this->namespace, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route($this->namespace, '/settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_settings'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    /**
     * Get API status
     */
    public function get_status($request) {
        return new WP_REST_Response(array(
            'status' => 'ok',
            'version' => VIPOS_VERSION,
            'timestamp' => current_time('mysql'),
            'woocommerce_active' => class_exists('WooCommerce'),
            'currency' => get_woocommerce_currency(),
            'timezone' => wp_timezone_string()
        ), 200);
    }    /**
     * Get POS settings
     */
    public function get_settings($request) {
        $settings = VIPOS_Settings::instance()->get_all_settings();
        
        return new WP_REST_Response($settings, 200);
    }
      /**
     * Check API permissions
     */
    public function check_permissions($request) {
        return current_user_can('vipos_access');
    }
    
    /**
     * Check admin permissions
     */
    public function check_admin_permissions($request) {
        return current_user_can('vipos_manage_settings');
    }
    
    /**
     * Error response helper
     */
    public function error_response($message, $code = 'error', $status = 400) {
        return new WP_Error($code, $message, array('status' => $status));
    }
    
    /**
     * Success response helper
     */
    public function success_response($data, $status = 200) {
        return rest_ensure_response($data);
    }
    
    /**
     * Get collection parameters
     */
    public function get_collection_params() {
        return array(
            'page' => array(
                'description' => __('Current page of the collection', 'vipos'),
                'type'        => 'integer',
                'default'     => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description' => __('Maximum number of items to be returned in result set', 'vipos'),
                'type'        => 'integer',
                'default'     => 20,
                'minimum'     => 1,
                'maximum'     => 100,
                'sanitize_callback' => 'absint',
            ),
            'orderby' => array(
                'description' => __('Sort collection by object attribute', 'vipos'),
                'type'        => 'string',
                'default'     => 'date',
                'enum'        => array('date', 'id', 'title', 'slug'),
            ),
            'order' => array(
                'description' => __('Order sort attribute ascending or descending', 'vipos'),
                'type'        => 'string',
                'default'     => 'desc',
                'enum'        => array('asc', 'desc'),
            ),
        );
    }
    
    /**
     * Get namespace
     */
    public function get_namespace() {
        return $this->namespace;
    }
    
    /**
     * Validate required parameters
     */
    public function validate_required_params($request, $required_params) {
        foreach ($required_params as $param) {
            if (!$request->has_param($param) || empty($request->get_param($param))) {
                return new WP_Error(
                    'missing_parameter',
                    sprintf(__('Missing required parameter: %s', 'vipos'), $param),
                    array('status' => 400)
                );
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize and validate parameters
     */
    public function sanitize_params($request, $param_rules) {
        $sanitized = array();
        
        foreach ($param_rules as $param => $rules) {
            $value = $request->get_param($param);
            
            if ($value === null && isset($rules['default'])) {
                $value = $rules['default'];
            }
            
            if ($value !== null) {
                // Apply sanitization
                if (isset($rules['sanitize'])) {
                    $value = call_user_func($rules['sanitize'], $value);
                }
                
                // Apply validation
                if (isset($rules['validate'])) {
                    $validation_result = call_user_func($rules['validate'], $value);
                    if (is_wp_error($validation_result)) {
                        return $validation_result;
                    }
                }
                
                $sanitized[$param] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Format error response
     */
    public function format_error($code, $message, $data = null, $status = 400) {
        return new WP_Error($code, $message, array_merge(
            array('status' => $status),
            $data ? array('data' => $data) : array()
        ));
    }
    
    /**
     * Format success response
     */
    public function format_success($data, $status = 200) {
        return new WP_REST_Response($data, $status);
    }
}
