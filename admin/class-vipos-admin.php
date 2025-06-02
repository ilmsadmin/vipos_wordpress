<?php
/**
 * VIPOS Admin Class
 *
 * @package VIPOS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_Admin {
    
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
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin bar
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        
        // Plugin action links
        add_filter('plugin_action_links_' . VIPOS_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        
        // Plugin row meta
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        
        // Check WooCommerce dependency
        add_action('admin_init', array($this, 'check_woocommerce_dependency'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('VIPOS', 'vipos'),
            __('VIPOS', 'vipos'),
            'vipos_access',
            'vipos',
            array($this, 'pos_page'),
            'dashicons-store',
            55
        );
        
        // POS submenu (same as main)
        add_submenu_page(
            'vipos',
            __('Point of Sale', 'vipos'),
            __('POS Interface', 'vipos'),
            'vipos_access',
            'vipos',
            array($this, 'pos_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'vipos',
            __('VIPOS Settings', 'vipos'),
            __('Settings', 'vipos'),
            'vipos_manage_settings',
            'vipos-settings',
            array($this, 'settings_page')
        );
          // Reports submenu
        add_submenu_page(
            'vipos',
            __('VIPOS Reports', 'vipos'),
            __('Reports', 'vipos'),
            'vipos_view_reports',
            'vipos-reports',
            array($this, 'reports_page')
        );
    }
    
    /**
     * Add admin bar menu
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('vipos_access')) {
            return;
        }
        
        $wp_admin_bar->add_node(array(
            'id' => 'vipos',
            'title' => '<span class="ab-icon dashicons-store"></span>' . __('POS', 'vipos'),
            'href' => admin_url('admin.php?page=vipos'),
            'meta' => array(
                'title' => __('Open Point of Sale', 'vipos'),
                'target' => '_blank'
            )
        ));
    }
    
    /**
     * Plugin action links
     */
    public function plugin_action_links($links) {
        $action_links = array(
            'pos' => '<a href="' . admin_url('admin.php?page=vipos') . '">' . __('Open POS', 'vipos') . '</a>',
            'settings' => '<a href="' . admin_url('admin.php?page=vipos-settings') . '">' . __('Settings', 'vipos') . '</a>',
        );
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Plugin row meta
     */
    public function plugin_row_meta($links, $file) {
        if (VIPOS_PLUGIN_BASENAME === $file) {
            $row_meta = array(
                'docs' => '<a href="https://vipos.com/docs" target="_blank">' . __('Documentation', 'vipos') . '</a>',
                'support' => '<a href="https://vipos.com/support" target="_blank">' . __('Support', 'vipos') . '</a>',
            );
            
            return array_merge($links, $row_meta);
        }
        
        return $links;
    }
    
    /**
     * Check WooCommerce dependency
     */
    public function check_woocommerce_dependency() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            
            // Disable plugin functionality
            remove_action('admin_menu', array($this, 'add_admin_menu'));
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('VIPOS Error:', 'vipos'); ?></strong>
                <?php _e('WooCommerce is required but not active. Please install and activate WooCommerce.', 'vipos'); ?>
            </p>
            <p>
                <a href="<?php echo admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'); ?>" class="button button-primary">
                    <?php _e('Install WooCommerce', 'vipos'); ?>
                </a>
                <?php if (file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php')): ?>
                <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php'), 'activate-plugin_woocommerce/woocommerce.php'); ?>" class="button">
                    <?php _e('Activate WooCommerce', 'vipos'); ?>
                </a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * POS page
     */
    public function pos_page() {
        // Check permissions
        if (!current_user_can('vipos_access')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'vipos'));
        }
        
        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            ?>
            <div class="wrap">
                <h1><?php _e('VIPOS - Point of Sale', 'vipos'); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('WooCommerce is required to use VIPOS. Please install and activate WooCommerce first.', 'vipos'); ?></p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Include POS interface template
        include_once VIPOS_PLUGIN_PATH . 'admin/pos-interface.php';
    }
    
    /**
     * Settings page
     */    public function settings_page() {
        // Check permissions
        if (!current_user_can('vipos_manage_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'vipos'));
        }
        
        // Include settings template
        include_once VIPOS_PLUGIN_PATH . 'admin/settings-page.php';
    }
    
    /**
     * Reports page
     */
    public function reports_page() {
        // Check permissions
        if (!current_user_can('vipos_view_reports')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'vipos'));
        }
          ?>
        <div class="wrap">
            <h1><?php _e('VIPOS Reports', 'vipos'); ?></h1>
            <div class="notice notice-info">
                <p><?php _e('Reports functionality will be available in future updates.', 'vipos'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Testing page (debug mode only)
     */
    public function testing_page() {
        // Only allow in debug mode
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            wp_die(__('Testing interface is only available in debug mode.', 'vipos'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'vipos'));
        }
          // Include testing template
        include_once VIPOS_PLUGIN_PATH . 'admin/testing.php';
    }
    
    /**
     * Get admin page URL
     */
    public function get_admin_url($page = 'vipos') {
        return admin_url('admin.php?page=' . $page);
    }
    
    /**
     * Check if current screen is VIPOS
     */
    public function is_vipos_screen() {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'vipos') !== false;
    }
    
    /**
     * Add help tabs
     */
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        if (!$this->is_vipos_screen()) {
            return;
        }
        
        $screen->add_help_tab(array(
            'id' => 'vipos-overview',
            'title' => __('Overview', 'vipos'),
            'content' => '<p>' . __('VIPOS is a Point of Sale system integrated with WooCommerce. Use it to sell products directly from your admin dashboard.', 'vipos') . '</p>'
        ));
        
        $screen->add_help_tab(array(
            'id' => 'vipos-getting-started',
            'title' => __('Getting Started', 'vipos'),
            'content' => '<p>' . __('To start using VIPOS:', 'vipos') . '</p>' .
                        '<ol>' .
                        '<li>' . __('Make sure WooCommerce is installed and configured', 'vipos') . '</li>' .
                        '<li>' . __('Add products to your WooCommerce store', 'vipos') . '</li>' .
                        '<li>' . __('Configure VIPOS settings', 'vipos') . '</li>' .
                        '<li>' . __('Start selling with the POS interface', 'vipos') . '</li>' .
                        '</ol>'
        ));
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('For more information:', 'vipos') . '</strong></p>' .
            '<p><a href="https://vipos.com/docs" target="_blank">' . __('Documentation', 'vipos') . '</a></p>' .
            '<p><a href="https://vipos.com/support" target="_blank">' . __('Support', 'vipos') . '</a></p>'
        );
    }
}
