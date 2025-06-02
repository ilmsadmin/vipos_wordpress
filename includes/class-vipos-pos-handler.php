<?php
/**
 * VIPOS POS Handler Class
 * 
 * Handles POS interface initialization and main POS operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_POS_Handler {
    
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
        // Add AJAX handlers for POS operations
        add_action('wp_ajax_vipos_get_pos_data', array($this, 'handle_get_pos_data'));
        add_action('wp_ajax_vipos_search_products', array($this, 'handle_search_products'));
        
        // Delegate customer search to the Customer Manager
        // Don't add 'wp_ajax_vipos_search_customers' hook here, as it's in Customer Manager
        
        // Enqueue scripts and styles for POS interface
        add_action('admin_enqueue_scripts', array($this, 'enqueue_pos_assets'));
        
        // Add a debug action to log when this class is initialized
        add_action('init', function() {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('VIPOS POS Handler: Hooks initialized');
            }
        });
    }
      /**
     * Enqueue POS assets
     */
    public function enqueue_pos_assets($hook) {
        // Only load on POS page
        if ($hook !== 'toplevel_page_vipos') {
            return;
        }
          // Enqueue CSS
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
            VIPOS_VERSION,
            true
        );
        
        // Enqueue layout adjuster script
        wp_enqueue_script(
            'vipos-layout-adjuster',
            VIPOS_PLUGIN_URL . 'assets/js/layout-adjuster.js',
            array('jquery', 'vipos-pos-script'),
            VIPOS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('vipos-pos-script', 'vipos_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vipos_nonce'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'currency_position' => get_option('woocommerce_currency_pos'),
            'price_decimal_sep' => wc_get_price_decimal_separator(),
            'price_thousand_sep' => wc_get_price_thousand_separator(),
            'price_decimals' => wc_get_price_decimals(),
            'i18n' => array(
                'loading' => __('Loading...', 'vipos'),
                'error' => __('Error occurred', 'vipos'),
                'success' => __('Success', 'vipos'),
                'add_to_cart' => __('Add to Cart', 'vipos'),
                'remove_item' => __('Remove Item', 'vipos'),
                'checkout' => __('Checkout', 'vipos'),
                'clear_cart' => __('Clear Cart', 'vipos'),
                'search_products' => __('Search products...', 'vipos'),
                'search_customers' => __('Search customers...', 'vipos'),
                'no_products_found' => __('No products found', 'vipos'),
                'no_customers_found' => __('No customers found', 'vipos'),
            )
        ));
    }
    
    /**
     * Handle get POS data AJAX request
     */
    public function handle_get_pos_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        // Check user capabilities
        if (!current_user_can('vipos_access')) {
            wp_die(__('Access denied', 'vipos'));
        }
        
        try {
            $response = array(
                'success' => true,
                'data' => array(
                    'products' => VIPOS_Product_Manager::instance()->get_products_for_pos(),
                    'categories' => VIPOS_Product_Manager::instance()->get_categories(),
                    'customers' => VIPOS_Customer_Manager::instance()->get_customers_for_pos(),
                    'cart' => VIPOS_Cart_Manager::instance()->get_current_cart(),
                    'settings' => $this->get_pos_settings()
                )
            );
            
            wp_send_json($response);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Handle search products AJAX request
     */
    public function handle_search_products() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        // Check user capabilities
        if (!current_user_can('vipos_access')) {
            wp_die(__('Access denied', 'vipos'));
        }
        
        $search_term = sanitize_text_field($_POST['search_term']);
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        
        try {
            $products = VIPOS_Product_Manager::instance()->search_products($search_term, $category_id, $page);
            
            wp_send_json_success($products);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
      /**
     * Handle search customers AJAX request
     */
    public function handle_search_customers() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        // Check user capabilities
        if (!current_user_can('vipos_access')) {
            wp_die(__('Access denied', 'vipos'));
        }
        
        $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        
        // Debug log
        error_log('VIPOS POS Handler: Searching for customers with term: ' . $search_term);
        
        try {
            // Directly use the Customer Manager instance for searching
            $customers = VIPOS_Customer_Manager::instance()->search_customers($search_term, $limit);
            
            // Debug log
            error_log('VIPOS POS Handler: Found ' . count($customers) . ' customers');
            
            wp_send_json_success($customers);
            
        } catch (Exception $e) {
            error_log('VIPOS POS Handler: Customer search error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get POS settings
     */
    private function get_pos_settings() {
        return array(
            'tax_enabled' => wc_tax_enabled(),
            'tax_display_shop' => get_option('woocommerce_tax_display_shop'),
            'calc_taxes' => get_option('woocommerce_calc_taxes'),
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'currency_position' => get_option('woocommerce_currency_pos'),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimal_separator' => wc_get_price_decimal_separator(),
            'num_decimals' => wc_get_price_decimals(),
            'products_per_page' => get_option('vipos_products_per_page', 20),
            'auto_print_receipt' => get_option('vipos_auto_print_receipt', 'no'),
            'default_customer' => get_option('vipos_default_customer', 0)
        );
    }
}
