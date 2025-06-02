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
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
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
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on VIPOS pages
        if (strpos($hook, 'vipos') === false) {
            return;
        }
        
        // Load future development assets for settings and reports pages
        if (in_array($hook, array('vipos_page_vipos-settings', 'vipos_page_vipos-reports'))) {
            wp_enqueue_style(
                'vipos-future-development',
                VIPOS_PLUGIN_URL . 'assets/css/future-development.css',
                array(),
                VIPOS_VERSION
            );
            
            wp_enqueue_script(
                'vipos-future-development',
                VIPOS_PLUGIN_URL . 'assets/js/future-development.js',
                array('jquery'),
                VIPOS_VERSION,
                true
            );
            
            wp_localize_script('vipos-future-development', 'viposFuture', array(
                'phase2Message' => __('This feature will be available in Phase 2', 'vipos'),
                'developmentNotice' => __('Feature Under Development', 'vipos'),
                'phase2Description' => __('This functionality will be available in Phase 2 of VIPOS development.', 'vipos')
            ));
        }
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
     */
    public function settings_page() {
        // Check permissions
        if (!current_user_can('vipos_manage_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'vipos'));
        }
        
        ?>
        <div class="wrap vipos-settings-future">
            <h1><?php _e('VIPOS Settings', 'vipos'); ?></h1>
            
            <!-- Future Development Notice -->
            <div class="notice notice-info vipos-future-notice">
                <h2 style="margin-top: 0;"><?php _e('Coming Soon in Phase 2', 'vipos'); ?></h2>
                <p style="font-size: 16px;"><strong><?php _e('Settings functionality will be available in future updates.', 'vipos'); ?></strong></p>
                <p><?php _e('This feature is planned for development in Phase 2 of the VIPOS project. Stay tuned for updates!', 'vipos'); ?></p>
            </div>
            
            <!-- Dimmed Settings Content -->
            <div class="vipos-settings-dimmed">
                <?php include_once VIPOS_PLUGIN_PATH . 'admin/settings-page.php'; ?>
            </div>
        </div>
          <style>
        .vipos-settings-future .vipos-future-notice {
            border-left: 4px solid #007cba;
            padding: 20px;
            margin: 20px 0;
            background: #f0f6fc;
            font-size: 14px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            animation: futureNoticeSlideIn 0.5s ease-out;
        }
        
        .vipos-settings-future .vipos-future-notice h2 {
            color: #007cba;
            margin-bottom: 10px;
            margin-top: 0;
            font-size: 20px;
        }
        
        .vipos-settings-future .vipos-future-notice .future-main-message {
            font-size: 16px;
            font-weight: 600;
            color: #1e3a5f;
            margin-bottom: 15px;
        }
        
        .vipos-settings-dimmed {
            opacity: 0.4;
            pointer-events: none;
            position: relative;
            user-select: none;
        }
        
        .vipos-settings-dimmed::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            z-index: 10;
            cursor: not-allowed;
        }
        
        .vipos-settings-dimmed::after {
            content: 'Settings will be available in Phase 2';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 124, 186, 0.9);
            color: white;
            padding: 15px 25px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            z-index: 11;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .vipos-settings-dimmed:hover::after {
            opacity: 1;
        }
        
        .vipos-settings-dimmed .wrap {
            margin: 0 !important;
        }
        
        .vipos-settings-dimmed h1 {
            display: none;
        }
        
        @keyframes futureNoticeSlideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .vipos-settings-future .vipos-future-notice:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 124, 186, 0.15);
            transition: all 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .vipos-settings-future .vipos-future-notice {
                padding: 15px;
                margin: 15px 0;
            }
            
            .vipos-settings-future .vipos-future-notice h2 {
                font-size: 18px;
            }
            
            .vipos-settings-dimmed::after {
                font-size: 12px;
                padding: 12px 20px;
                max-width: 80%;
                text-align: center;
                white-space: normal;
            }
        }
        </style>
        <script>
        jQuery(document).ready(function($) {
            // Handle clicks on dimmed settings area
            $(document).on('click', '.vipos-settings-dimmed', function(e) {
                e.preventDefault();
                e.stopPropagation();
                showPhase2Message();
            });
            
            // Prevent form interactions in dimmed areas
            $(document).on('click keydown', '.vipos-settings-dimmed *', function(e) {
                e.preventDefault();
                e.stopPropagation();
                showPhase2Message();
                return false;
            });
            
            // Prevent form submissions
            $('.vipos-settings-dimmed form').on('submit', function(e) {
                e.preventDefault();
                showPhase2Message();
                return false;
            });
            
            function showPhase2Message() {
                // Remove any existing notices
                $('.vipos-temp-notice').remove();
                
                // Create notification
                var $notice = $('<div class="vipos-temp-notice" style="position: fixed; top: 50px; right: 20px; z-index: 999999; max-width: 350px; opacity: 0; transform: translateX(100%); transition: all 0.3s ease;">' +
                    '<div class="vipos-temp-notice-content" style="background: linear-gradient(135deg, #007cba, #005a87); color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0, 124, 186, 0.3); display: flex; align-items: flex-start; gap: 12px; position: relative;">' +
                        '<span class="dashicons dashicons-info" style="font-size: 20px; margin-top: 2px; flex-shrink: 0;"></span>' +
                        '<div class="vipos-temp-notice-text" style="flex: 1; font-size: 14px; line-height: 1.4;">' +
                            '<strong style="font-weight: 600; font-size: 15px;">Feature Under Development</strong><br>' +
                            'This functionality will be available in Phase 2 of VIPOS development.' +
                        '</div>' +
                        '<button class="vipos-temp-notice-close" style="background: none; border: none; color: white; font-size: 18px; font-weight: bold; cursor: pointer; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background-color 0.2s ease; flex-shrink: 0;">&times;</button>' +
                    '</div>' +
                '</div>');
                
                // Add to page
                $('body').append($notice);
                
                // Show with animation
                setTimeout(function() {
                    $notice.css({
                        'opacity': '1',
                        'transform': 'translateX(0)'
                    });
                }, 10);
                
                // Auto-hide after 4 seconds
                setTimeout(function() {
                    hideNotice($notice);
                }, 4000);
                
                // Close button handler
                $notice.find('.vipos-temp-notice-close').on('click', function() {
                    hideNotice($notice);
                });
                
                // Hover effect for close button
                $notice.find('.vipos-temp-notice-close').hover(
                    function() { $(this).css('background-color', 'rgba(255, 255, 255, 0.2)'); },
                    function() { $(this).css('background-color', 'transparent'); }
                );
            }
            
            function hideNotice($notice) {
                $notice.css({
                    'opacity': '0',
                    'transform': 'translateX(100%)'
                });
                setTimeout(function() {
                    $notice.remove();
                }, 300);
            }
        });
        </script>
        <?php
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
        <div class="wrap vipos-reports-future">
            <h1><?php _e('VIPOS Reports', 'vipos'); ?></h1>
            
            <!-- Enhanced Future Development Notice -->
            <div class="notice notice-info vipos-future-notice-large">
                <div class="vipos-future-content">
                    <div class="vipos-future-icon">
                        <span class="dashicons dashicons-chart-bar" style="font-size: 48px; color: #007cba;"></span>
                    </div>
                    <div class="vipos-future-text">
                        <h2><?php _e('Coming Soon in Phase 2', 'vipos'); ?></h2>
                        <p class="vipos-future-main-message"><strong><?php _e('Reports functionality will be available in future updates.', 'vipos'); ?></strong></p>
                        <p><?php _e('Comprehensive reporting features including sales analytics, inventory reports, and performance metrics are planned for development in Phase 2 of the VIPOS project.', 'vipos'); ?></p>
                        
                        <div class="vipos-future-features">
                            <h3><?php _e('Planned Features:', 'vipos'); ?></h3>
                            <ul>
                                <li><?php _e('Sales Analytics & Performance Metrics', 'vipos'); ?></li>
                                <li><?php _e('Inventory Management Reports', 'vipos'); ?></li>
                                <li><?php _e('Customer Purchase History', 'vipos'); ?></li>
                                <li><?php _e('Revenue & Profit Analysis', 'vipos'); ?></li>
                                <li><?php _e('Export Reports to PDF/Excel', 'vipos'); ?></li>
                            </ul>
                        </div>
                        
                        <p class="vipos-future-cta">
                            <a href="<?php echo admin_url('admin.php?page=vipos'); ?>" class="button button-primary">
                                <?php _e('Go to POS Interface', 'vipos'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=vipos-settings'); ?>" class="button button-secondary">
                                <?php _e('View Settings', 'vipos'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .vipos-reports-future .vipos-future-notice-large {
            border: none;
            padding: 0;
            margin: 20px 0;
            background: linear-gradient(135deg, #f0f6fc 0%, #e8f4f8 100%);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1);
        }
        
        .vipos-future-content {
            display: flex;
            padding: 30px;
            align-items: flex-start;
            gap: 30px;
        }
        
        .vipos-future-icon {
            flex-shrink: 0;
        }
        
        .vipos-future-text {
            flex: 1;
        }
        
        .vipos-future-text h2 {
            color: #007cba;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .vipos-future-main-message {
            font-size: 18px;
            color: #1e3a5f;
            margin-bottom: 20px;
        }
        
        .vipos-future-features {
            background: white;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #007cba;
        }
        
        .vipos-future-features h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #007cba;
        }
        
        .vipos-future-features ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .vipos-future-features li {
            margin-bottom: 8px;
            color: #555;
        }
        
        .vipos-future-cta {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 124, 186, 0.2);
        }
        
        .vipos-future-cta .button {
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .vipos-future-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
        }
        </style>
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
