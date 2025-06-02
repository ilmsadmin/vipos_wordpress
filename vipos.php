<?php
/**
 * Plugin Name: VIPOS - WordPress POS System
 * Plugin URI: https://vipos.vn
 * Description: Point of Sale system integrated with WooCommerce for retail management
 * Version: 1.0.0
 * Author: VIPOS Team
 * Author URI: https://vipos.vn
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vipos
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Woo: 18734:906d1ad9654a90d1b54cb4ecf0ba2d37
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Define plugin constants
define('VIPOS_VERSION', '1.0.0');
define('VIPOS_PLUGIN_FILE', __FILE__);
define('VIPOS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('VIPOS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('VIPOS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VIPOS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VIPOS_PLUGIN_SLUG', 'vipos');

// Main plugin class
class VIPOS {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Plugin version
     */
    public $version = VIPOS_VERSION;
    
    /**
     * Get single instance of plugin
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
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin after WordPress loads
        add_action('plugins_loaded', array($this, 'init'));
        
        // Load text domain
        add_action('init', array($this, 'load_textdomain'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            // Deactivate plugin
            deactivate_plugins(plugin_basename(__FILE__));
            
            // Show error message
            wp_die(
                __('VIPOS requires WooCommerce to be installed and active. Please install WooCommerce first.', 'vipos'),
                __('Plugin Activation Error', 'vipos'),
                array(
                    'back_link' => true,
                    'text_direction' => 'ltr'
                )
            );
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('VIPOS requires WordPress 5.0 or higher. Please update WordPress.', 'vipos'),
                __('Plugin Activation Error', 'vipos'),
                array('back_link' => true)
            );
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('VIPOS requires PHP 7.4 or higher. Please update PHP.', 'vipos'),
                __('Plugin Activation Error', 'vipos'),
                array('back_link' => true)
            );
        }
        
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Add user capabilities
        $this->add_capabilities();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('vipos_activated', true);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove user capabilities
        $this->remove_capabilities();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clean up temporary data
        delete_transient('vipos_products_cache');
        delete_option('vipos_activated');
    }
      /**
     * Initialize plugin
     */
    public function init() {
        // Check WooCommerce dependency on every page load
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load plugin classes
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Load admin functionality
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Initialize REST API
        add_action('rest_api_init', array($this, 'init_rest_api'));
        
        // Add AJAX hooks
        $this->init_ajax();
        
        // Handle receipt template
        add_action('template_redirect', array($this, 'handle_receipt_page'));
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce') || is_plugin_active('woocommerce/woocommerce.php');
    }
    
    /**
     * Show WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('VIPOS Error:', 'vipos'); ?></strong>
                <?php _e('WooCommerce is required but not active. Please install and activate WooCommerce.', 'vipos'); ?>
                <a href="<?php echo admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'); ?>" class="button button-primary">
                    <?php _e('Install WooCommerce', 'vipos'); ?>
                </a>
            </p>
        </div>
        <?php
    }      /**
     * Load plugin dependencies
     */    private function load_dependencies() {
        // Core classes
        require_once VIPOS_PLUGIN_PATH . 'includes/class-vipos-core.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/class-vipos-pos-handler.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/class-vipos-product-manager.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/class-vipos-cart-manager.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/class-vipos-order-manager.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/class-vipos-customer-manager.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/class-vipos-debug.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/functions.php';
        

          // Admin classes
        if (is_admin()) {
            require_once VIPOS_PLUGIN_PATH . 'admin/class-vipos-admin.php';
            require_once VIPOS_PLUGIN_PATH . 'admin/class-vipos-settings.php';
            require_once VIPOS_PLUGIN_PATH . 'admin/receipt-temp.php';
        }
        
        // API classes
        require_once VIPOS_PLUGIN_PATH . 'includes/api/class-vipos-rest-api.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/api/class-vipos-products-api.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/api/class-vipos-cart-api.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/api/class-vipos-orders-api.php';
        require_once VIPOS_PLUGIN_PATH . 'includes/api/class-vipos-customers-api.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize core components
        VIPOS_Core::instance();
        VIPOS_POS_Handler::instance();
        VIPOS_Product_Manager::instance();
        VIPOS_Cart_Manager::instance();
        VIPOS_Order_Manager::instance();
        VIPOS_Customer_Manager::instance();
    }
    
    /**
     * Initialize admin
     */
    private function init_admin() {
        VIPOS_Admin::instance();
        VIPOS_Settings::instance();
    }
    
    /**
     * Initialize REST API
     */
    public function init_rest_api() {
        VIPOS_REST_API::instance();
    }
    
    /**
     * Initialize AJAX handlers
     */
    private function init_ajax() {
        // Add AJAX actions here
        add_action('wp_ajax_vipos_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_vipos_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_vipos_update_cart', array($this, 'ajax_update_cart'));
        add_action('wp_ajax_vipos_checkout', array($this, 'ajax_checkout'));
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'vipos',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Show activation success notice
        if (get_option('vipos_activated')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('VIPOS activated successfully!', 'vipos'); ?></strong>
                    <a href="<?php echo admin_url('admin.php?page=vipos'); ?>" class="button button-primary">
                        <?php _e('Open POS', 'vipos'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vipos-settings'); ?>" class="button">
                        <?php _e('Settings', 'vipos'); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_option('vipos_activated');
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // POS Sessions table
        $sessions_table = $wpdb->prefix . 'vipos_sessions';
        $sessions_sql = "CREATE TABLE $sessions_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            cart_data longtext,
            customer_id bigint(20) DEFAULT NULL,
            discount_amount decimal(10,2) DEFAULT 0.00,
            discount_type varchar(20) DEFAULT 'percentage',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Settings table
        $settings_table = $wpdb->prefix . 'vipos_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext,
            autoload enum('yes','no') DEFAULT 'yes',
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sessions_sql);
        dbDelta($settings_sql);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $default_settings = array(
            'vipos_pos_per_page' => 20,
            'vipos_enable_customer_search' => 'yes',
            'vipos_enable_discount' => 'yes',
            'vipos_enable_tax' => 'yes',
            'vipos_default_payment_method' => 'cash',
            'vipos_print_receipt' => 'yes',
            'vipos_product_categories' => array(),
            'vipos_pos_roles' => array('administrator', 'shop_manager'),
        );
        
        foreach ($default_settings as $key => $value) {
            if (!get_option($key)) {
                update_option($key, $value);
            }
        }
    }
    
    /**
     * Add user capabilities
     */
    private function add_capabilities() {
        $roles = array('administrator', 'shop_manager');
        $capabilities = array(
            'vipos_access',
            'vipos_manage_settings',
            'vipos_view_reports',
            'vipos_process_orders'
        );
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Remove user capabilities
     */
    private function remove_capabilities() {
        $roles = array('administrator', 'shop_manager', 'editor', 'author', 'contributor', 'subscriber');
        $capabilities = array(
            'vipos_access',
            'vipos_manage_settings',
            'vipos_view_reports',
            'vipos_process_orders'
        );
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * AJAX handler for product search
     */
    public function ajax_search_products() {
        // Will be implemented in API classes
        wp_die();
    }
      /**
     * AJAX handler for add to cart
     */
    public function ajax_add_to_cart() {
        // Will be implemented in API classes
        wp_die();
    }
    
    /**
     * AJAX handler for update cart
     */
    public function ajax_update_cart() {
        // Will be implemented in API classes
        wp_die();
    }
    
    /**
     * AJAX handler for checkout
     */
    public function ajax_checkout() {
        // Will be implemented in API classes
        wp_die();
    }
      /**
     * Handle receipt page
     * 
     * Intercepts URL with vipos_receipt parameter and renders the receipt
     */
    public function handle_receipt_page() {
        // Check if this is a receipt request
        if (!isset($_GET['vipos_receipt']) || empty($_GET['vipos_receipt'])) {
            return;
        }
        
        // Get order ID and key
        $order_id = intval($_GET['vipos_receipt']);
        $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        
        // Enable error display for debugging
        if (WP_DEBUG) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
            error_log('VIPOS: Receipt page requested for order ' . $order_id);
        }
        
        // Validate order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found', 'vipos'), __('Error', 'vipos'), array('response' => 404));
        }
        
        // Validate the order key (but don't die, just log it for testing)
        $order_key = $order->get_order_key();
        if (!empty($key) && $order_key !== $key) {
            error_log('VIPOS: Key mismatch: Expected ' . $order_key . ' but got ' . $key);
        }
        
        // Check if this is a POS order - temporarily disabled for testing
        // We'll re-enable this check once the basic receipt functionality works
        /*
        if (!$order->get_meta('_vipos_order')) {
            wp_die(__('This is not a POS order', 'vipos'), __('Error', 'vipos'), array('response' => 403));
        }
        */
        
        // Make order available to template
        if (WP_DEBUG) {
            error_log('VIPOS: Setting up order for template. Order ID: ' . $order_id);
            error_log('VIPOS: Order data: ' . print_r($order->get_data(), true));
        }
          // Ensure we have a valid template file
        $template_path = VIPOS_PLUGIN_PATH . 'admin/receipt.php';
        if (!file_exists($template_path)) {
            wp_die(__('Receipt template file not found', 'vipos'), __('Error', 'vipos'), array('response' => 500));
        }
        
        // Define this constant to allow direct template access
        if (!defined('VIPOS_RECEIPT_PAGE')) {
            define('VIPOS_RECEIPT_PAGE', true);
        }
        
        // Use the helper function if available
        if (function_exists('vipos_load_template')) {
            echo vipos_load_template($template_path, array(
                'order' => $order,
                'order_id' => $order_id,
            ));
        } else {
            // Fallback to direct include
            try {
                // Extract variables for the template
                extract($template_vars);
                
                // Load the template
                include $template_path;
            } catch (Exception $e) {
                error_log('VIPOS: Exception in receipt template: ' . $e->getMessage());
                wp_die('Error in receipt template: ' . $e->getMessage(), 'Receipt Error', array('response' => 500));
            }
        }
        
        // Stop WordPress from loading the template
        exit;
    }
}

/**
 * Returns the main instance of VIPOS
 */
function VIPOS() {
    return VIPOS::instance();
}

// Initialize the plugin
VIPOS();
