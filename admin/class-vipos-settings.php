<?php
/**
 * VIPOS Settings Class
 * 
 * Manages plugin settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_Settings {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Settings page slug
     */
    private $page_slug = 'vipos-settings';
    
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
        // Add admin menu
        add_action('admin_menu', array($this, 'add_settings_page'), 20);
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_vipos_save_settings', array($this, 'handle_save_settings'));
        add_action('wp_ajax_vipos_reset_settings', array($this, 'handle_reset_settings'));
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'vipos-pos',
            __('Settings', 'vipos'),
            __('Settings', 'vipos'),
            'vipos_manage_settings',
            $this->page_slug,
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // General settings
        register_setting('vipos_general_settings', 'vipos_products_per_page', array(
            'type' => 'integer',
            'default' => 20,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('vipos_general_settings', 'vipos_auto_print_receipt', array(
            'type' => 'string',
            'default' => 'no',
            'sanitize_callback' => array($this, 'sanitize_yes_no')
        ));
        
        register_setting('vipos_general_settings', 'vipos_default_customer', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('vipos_general_settings', 'vipos_enable_barcode_scanner', array(
            'type' => 'string',
            'default' => 'no',
            'sanitize_callback' => array($this, 'sanitize_yes_no')
        ));
        
        register_setting('vipos_general_settings', 'vipos_enable_customer_display', array(
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => array($this, 'sanitize_yes_no')
        ));
        
        // Receipt settings
        register_setting('vipos_receipt_settings', 'vipos_receipt_header', array(
            'type' => 'string',
            'default' => get_bloginfo('name'),
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        register_setting('vipos_receipt_settings', 'vipos_receipt_footer', array(
            'type' => 'string',
            'default' => __('Thank you for your business!', 'vipos'),
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        register_setting('vipos_receipt_settings', 'vipos_receipt_logo', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('vipos_receipt_settings', 'vipos_receipt_paper_size', array(
            'type' => 'string',
            'default' => '80mm',
            'sanitize_callback' => array($this, 'sanitize_paper_size')
        ));
        
        // Tax settings
        register_setting('vipos_tax_settings', 'vipos_tax_inclusive', array(
            'type' => 'string',
            'default' => 'no',
            'sanitize_callback' => array($this, 'sanitize_yes_no')
        ));
        
        register_setting('vipos_tax_settings', 'vipos_tax_rounding', array(
            'type' => 'string',
            'default' => 'standard',
            'sanitize_callback' => array($this, 'sanitize_tax_rounding')
        ));
        
        // Access settings
        register_setting('vipos_access_settings', 'vipos_allowed_users', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array($this, 'sanitize_user_array')
        ));
        
        register_setting('vipos_access_settings', 'vipos_session_timeout', array(
            'type' => 'integer',
            'default' => 3600,
            'sanitize_callback' => 'absint'
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('vipos_manage_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'vipos'));
        }
        
        // Enqueue settings assets
        $this->enqueue_settings_assets();
        
        include VIPOS_PLUGIN_PATH . 'admin/settings-page.php';
    }
    
    /**
     * Enqueue settings assets
     */
    private function enqueue_settings_assets() {        wp_enqueue_style(
            'vipos-settings-style',
            VIPOS_PLUGIN_URL . 'assets/css/settings-modern.css',
            array(),
            VIPOS_VERSION
        );
        
        wp_enqueue_script(
            'vipos-settings-script',
            VIPOS_PLUGIN_URL . 'assets/js/settings.js',
            array('jquery'),
            VIPOS_VERSION,
            true
        );
        
        wp_localize_script('vipos-settings-script', 'vipos_settings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vipos_settings_nonce'),
            'i18n' => array(
                'saving' => __('Saving...', 'vipos'),
                'saved' => __('Settings saved successfully', 'vipos'),
                'error' => __('Error saving settings', 'vipos'),
                'confirm_reset' => __('Are you sure you want to reset all settings to default values?', 'vipos'),
                'resetting' => __('Resetting...', 'vipos'),
                'reset_success' => __('Settings reset successfully', 'vipos')
            )
        ));
        
        wp_enqueue_media();
    }
    
    /**
     * Get all settings
     */
    public function get_all_settings() {
        return array(
            'general' => array(
                'products_per_page' => get_option('vipos_products_per_page', 20),
                'auto_print_receipt' => get_option('vipos_auto_print_receipt', 'no'),
                'default_customer' => get_option('vipos_default_customer', 0),
                'enable_barcode_scanner' => get_option('vipos_enable_barcode_scanner', 'no'),
                'enable_customer_display' => get_option('vipos_enable_customer_display', 'yes')
            ),
            'receipt' => array(
                'header' => get_option('vipos_receipt_header', get_bloginfo('name')),
                'footer' => get_option('vipos_receipt_footer', __('Thank you for your business!', 'vipos')),
                'logo' => get_option('vipos_receipt_logo', 0),
                'paper_size' => get_option('vipos_receipt_paper_size', '80mm')
            ),
            'tax' => array(
                'inclusive' => get_option('vipos_tax_inclusive', 'no'),
                'rounding' => get_option('vipos_tax_rounding', 'standard')
            ),
            'access' => array(
                'allowed_users' => get_option('vipos_allowed_users', array()),
                'session_timeout' => get_option('vipos_session_timeout', 3600)
            )
        );
    }    /**
     * Get setting value
     */
    public function get_setting($group_or_key, $key_or_default = null, $default = null) {
        // Handle different calling patterns
        if ($key_or_default === null) {
            // Single parameter: get_setting('some_key')
            return get_option('vipos_' . $group_or_key, null);
        } elseif ($default === null) {
            // Two parameters could be either:
            // get_setting('some_key', 'default_value') OR get_setting('group', 'key')
            // We need to determine which pattern is being used
            // If the second parameter looks like a default value (string/number), treat as key+default
            // If it looks like a setting key, treat as group+key
            
            // Check if this looks like a group + key pattern (common groups: general, receipt, tax, access)
            $valid_groups = array('general', 'receipt', 'tax', 'access');
            if (in_array($group_or_key, $valid_groups)) {
                // This is group + key pattern, no default
                return get_option('vipos_' . $group_or_key . '_' . $key_or_default, '');
            } else {
                // This is key + default pattern
                return get_option('vipos_' . $group_or_key, $key_or_default);
            }
        } else {
            // Three parameters: get_setting('group', 'key', 'default_value')
            return get_option('vipos_' . $group_or_key . '_' . $key_or_default, $default);
        }
    }
    
    /**
     * Update setting value
     */
    public function update_setting($key, $value) {
        return update_option('vipos_' . $key, $value);
    }
    
    /**
     * Reset settings to default
     */
    public function reset_settings() {
        $settings = array(
            'vipos_products_per_page' => 20,
            'vipos_auto_print_receipt' => 'no',
            'vipos_default_customer' => 0,
            'vipos_enable_barcode_scanner' => 'no',
            'vipos_enable_customer_display' => 'yes',
            'vipos_receipt_header' => get_bloginfo('name'),
            'vipos_receipt_footer' => __('Thank you for your business!', 'vipos'),
            'vipos_receipt_logo' => 0,
            'vipos_receipt_paper_size' => '80mm',
            'vipos_tax_inclusive' => 'no',
            'vipos_tax_rounding' => 'standard',
            'vipos_allowed_users' => array(),
            'vipos_session_timeout' => 3600
        );
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        return true;
    }
    
    /**
     * Sanitize yes/no values
     */
    public function sanitize_yes_no($value) {
        return in_array($value, array('yes', 'no')) ? $value : 'no';
    }
    
    /**
     * Sanitize paper size
     */
    public function sanitize_paper_size($value) {
        $valid_sizes = array('58mm', '80mm', 'A4');
        return in_array($value, $valid_sizes) ? $value : '80mm';
    }
    
    /**
     * Sanitize tax rounding
     */
    public function sanitize_tax_rounding($value) {
        $valid_options = array('standard', 'down', 'up');
        return in_array($value, $valid_options) ? $value : 'standard';
    }
    
    /**
     * Sanitize user array
     */
    public function sanitize_user_array($value) {
        if (!is_array($value)) {
            return array();
        }
        
        return array_map('absint', $value);
    }
    
    /**
     * Handle save settings AJAX
     */
    public function handle_save_settings() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_settings_nonce') || !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $settings = $_POST['settings'];
            
            // Sanitize and save general settings
            if (isset($settings['general'])) {
                $general = $settings['general'];
                
                if (isset($general['products_per_page'])) {
                    update_option('vipos_products_per_page', absint($general['products_per_page']));
                }
                
                if (isset($general['auto_print_receipt'])) {
                    update_option('vipos_auto_print_receipt', $this->sanitize_yes_no($general['auto_print_receipt']));
                }
                
                if (isset($general['default_customer'])) {
                    update_option('vipos_default_customer', absint($general['default_customer']));
                }
                
                if (isset($general['enable_barcode_scanner'])) {
                    update_option('vipos_enable_barcode_scanner', $this->sanitize_yes_no($general['enable_barcode_scanner']));
                }
                
                if (isset($general['enable_customer_display'])) {
                    update_option('vipos_enable_customer_display', $this->sanitize_yes_no($general['enable_customer_display']));
                }
            }
            
            // Sanitize and save receipt settings
            if (isset($settings['receipt'])) {
                $receipt = $settings['receipt'];
                
                if (isset($receipt['header'])) {
                    update_option('vipos_receipt_header', sanitize_textarea_field($receipt['header']));
                }
                
                if (isset($receipt['footer'])) {
                    update_option('vipos_receipt_footer', sanitize_textarea_field($receipt['footer']));
                }
                
                if (isset($receipt['logo'])) {
                    update_option('vipos_receipt_logo', absint($receipt['logo']));
                }
                
                if (isset($receipt['paper_size'])) {
                    update_option('vipos_receipt_paper_size', $this->sanitize_paper_size($receipt['paper_size']));
                }
            }
            
            // Sanitize and save tax settings
            if (isset($settings['tax'])) {
                $tax = $settings['tax'];
                
                if (isset($tax['inclusive'])) {
                    update_option('vipos_tax_inclusive', $this->sanitize_yes_no($tax['inclusive']));
                }
                
                if (isset($tax['rounding'])) {
                    update_option('vipos_tax_rounding', $this->sanitize_tax_rounding($tax['rounding']));
                }
            }
            
            // Sanitize and save access settings
            if (isset($settings['access'])) {
                $access = $settings['access'];
                
                if (isset($access['allowed_users'])) {
                    update_option('vipos_allowed_users', $this->sanitize_user_array($access['allowed_users']));
                }
                
                if (isset($access['session_timeout'])) {
                    update_option('vipos_session_timeout', absint($access['session_timeout']));
                }
            }
            
            wp_send_json_success(array(
                'message' => __('Settings saved successfully', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Handle reset settings AJAX
     */
    public function handle_reset_settings() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_settings_nonce') || !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $this->reset_settings();
            
            wp_send_json_success(array(
                'message' => __('Settings reset successfully', 'vipos'),
                'settings' => $this->get_all_settings()
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get users for settings
     */
    public function get_users_for_settings() {
        $users = get_users(array(
            'capability' => 'vipos_access',
            'fields' => array('ID', 'display_name', 'user_email')
        ));
        
        $formatted_users = array();
        foreach ($users as $user) {
            $formatted_users[] = array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email
            );
        }
        
        return $formatted_users;
    }
    
    /**
     * Get customers for default customer setting
     */
    public function get_customers_for_default() {
        $customers = get_users(array(
            'role' => 'customer',
            'number' => 100,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'user_email')
        ));
        
        $formatted_customers = array();
        foreach ($customers as $customer) {
            $formatted_customers[] = array(
                'id' => $customer->ID,
                'name' => $customer->display_name,
                'email' => $customer->user_email
            );
        }
        
        return $formatted_customers;
    }
}
