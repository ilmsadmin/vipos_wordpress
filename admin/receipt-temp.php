<?php
/**
 * VIPOS Receipt Template Manager
 * 
 * @package VIPOS
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class VIPOS_Receipt_Template_Manager
 * 
 * Manages receipt templates and customization options
 */
class VIPOS_Receipt_Template_Manager {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Default templates
     */
    private $default_templates = array();
    
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
     */    public function __construct() {
        $this->init_default_templates();
        add_action('wp_ajax_vipos_save_receipt_template', array($this, 'save_template'));
        add_action('wp_ajax_vipos_preview_receipt_template', array($this, 'preview_template'));
        add_action('wp_ajax_vipos_reset_receipt_template', array($this, 'reset_template'));
        add_action('wp_ajax_vipos_set_active_template', array($this, 'set_active_template'));
        add_action('wp_ajax_vipos_get_template_data', array($this, 'get_template_data'));
        add_action('wp_ajax_vipos_save_template_customization', array($this, 'save_template_customization'));
        add_action('wp_ajax_vipos_delete_template', array($this, 'delete_template'));
    }
    
    /**
     * Initialize default templates
     */
    private function init_default_templates() {
        $this->default_templates = array(
            'classic' => array(
                'name' => __('Classic', 'vipos'),
                'description' => __('Traditional thermal receipt style', 'vipos'),
                'css' => $this->get_classic_template_css(),
                'structure' => $this->get_classic_template_structure()
            ),
            'modern' => array(
                'name' => __('Modern', 'vipos'),
                'description' => __('Clean and modern receipt layout', 'vipos'),
                'css' => $this->get_modern_template_css(),
                'structure' => $this->get_modern_template_structure()
            ),
            'minimal' => array(
                'name' => __('Minimal', 'vipos'),
                'description' => __('Simple and clean layout', 'vipos'),
                'css' => $this->get_minimal_template_css(),
                'structure' => $this->get_minimal_template_structure()
            )
        );
    }
    
    /**
     * Get active template
     */
    public function get_active_template() {
        $active_template = get_option('vipos_active_receipt_template', 'classic');
        return $this->get_template($active_template);
    }
    
    /**
     * Get template by ID
     */
    public function get_template($template_id) {
        $custom_templates = get_option('vipos_custom_receipt_templates', array());
        
        // Check if it's a custom template
        if (isset($custom_templates[$template_id])) {
            return $custom_templates[$template_id];
        }
        
        // Check if it's a default template
        if (isset($this->default_templates[$template_id])) {
            return $this->default_templates[$template_id];
        }
        
        // Fallback to classic template
        return $this->default_templates['classic'];
    }
    
    /**
     * Get all templates
     */
    public function get_all_templates() {
        $custom_templates = get_option('vipos_custom_receipt_templates', array());
        return array_merge($this->default_templates, $custom_templates);
    }
    
    /**
     * Save template
     */
    public function save_template() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_receipt_template_nonce') || 
            !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $template_id = sanitize_text_field($_POST['template_id']);
            $template_data = array(
                'name' => sanitize_text_field($_POST['name']),
                'description' => sanitize_textarea_field($_POST['description']),
                'css' => wp_kses_post($_POST['css']),
                'structure' => wp_kses_post($_POST['structure']),
                'settings' => array(
                    'show_logo' => isset($_POST['show_logo']) ? 1 : 0,
                    'show_header' => isset($_POST['show_header']) ? 1 : 0,
                    'show_footer' => isset($_POST['show_footer']) ? 1 : 0,
                    'show_sku' => isset($_POST['show_sku']) ? 1 : 0,
                    'show_barcode' => isset($_POST['show_barcode']) ? 1 : 0,
                    'font_size' => sanitize_text_field($_POST['font_size']),
                    'paper_width' => sanitize_text_field($_POST['paper_width'])
                )
            );
            
            $custom_templates = get_option('vipos_custom_receipt_templates', array());
            $custom_templates[$template_id] = $template_data;
            
            update_option('vipos_custom_receipt_templates', $custom_templates);
            
            wp_send_json_success(array(
                'message' => __('Template saved successfully', 'vipos'),
                'template' => $template_data
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Preview template
     */
    public function preview_template() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_receipt_template_nonce') || 
            !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $template_id = sanitize_text_field($_POST['template_id']);
            $template = $this->get_template($template_id);
            
            // Generate preview HTML
            $preview_html = $this->generate_preview_html($template);
            
            wp_send_json_success(array(
                'html' => $preview_html
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Reset template to default
     */
    public function reset_template() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_receipt_template_nonce') || 
            !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $template_id = sanitize_text_field($_POST['template_id']);
            
            // Remove from custom templates if exists
            $custom_templates = get_option('vipos_custom_receipt_templates', array());
            if (isset($custom_templates[$template_id])) {
                unset($custom_templates[$template_id]);
                update_option('vipos_custom_receipt_templates', $custom_templates);
            }
            
            wp_send_json_success(array(
                'message' => __('Template reset to default', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Set active template
     */
    public function set_active_template() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_receipt_template_nonce') || 
            !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $template_id = sanitize_text_field($_POST['template_id']);
            
            // Update active template option
            update_option('vipos_active_receipt_template', $template_id);
            
            wp_send_json_success(array(
                'message' => __('Active template updated', 'vipos'),
                'template_id' => $template_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Get template data
     */
    public function get_template_data() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_receipt_template_nonce') || 
            !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $template_id = sanitize_text_field($_POST['template_id']);
            $template = $this->get_template($template_id);
            
            wp_send_json_success(array(
                'template' => $template
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Save template customization
     */
    public function save_template_customization() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_receipt_template_nonce') || 
            !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $template_id = sanitize_text_field($_POST['template_id']);
            $css = wp_kses_post($_POST['css']);
            $settings = array_map('sanitize_text_field', $_POST['settings']);
            
            // Get base template
            $template = $this->get_template($template_id);
            
            // Update template with customizations
            $template['css'] = $css;
            $template['settings'] = array_merge(
                isset($template['settings']) ? $template['settings'] : array(),
                $settings
            );
            
            // Save to custom templates
            $custom_templates = get_option('vipos_custom_receipt_templates', array());
            $custom_templates[$template_id] = $template;
            update_option('vipos_custom_receipt_templates', $custom_templates);
            
            wp_send_json_success(array(
                'message' => __('Template customization saved', 'vipos'),
                'template' => $template
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Delete template
     */
    public function delete_template() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_receipt_template_nonce') || 
            !current_user_can('vipos_manage_settings')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $template_id = sanitize_text_field($_POST['template_id']);
            
            // Remove from custom templates if exists
            $custom_templates = get_option('vipos_custom_receipt_templates', array());
            if (isset($custom_templates[$template_id])) {
                unset($custom_templates[$template_id]);
                update_option('vipos_custom_receipt_templates', $custom_templates);
            }
            
            wp_send_json_success(array(
                'message' => __('Template deleted', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Generate preview HTML
     */
    private function generate_preview_html($template) {
        ob_start();
        ?>
        <div class="vipos-receipt-preview" style="<?php echo esc_attr($this->get_preview_styles($template)); ?>">
            <style><?php echo $template['css']; ?></style>
            
            <div class="receipt-header">
                <div class="store-name"><?php echo get_bloginfo('name'); ?></div>
                <div class="store-address">123 Main Street<br>City, State 12345</div>
                <div class="custom-header">Chào mừng bạn đến cửa hàng!</div>
            </div>
            
            <div class="receipt-order-info">
                <div class="order-number"><strong>Đơn hàng #:</strong> 12345</div>
                <div class="order-date"><strong>Ngày:</strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></div>
                <div class="cashier-info"><strong>Thu ngân:</strong> Demo User</div>
            </div>
            
            <div class="receipt-separator"></div>
            
            <div class="receipt-items">
                <div class="items-header">
                    <div class="item-name">Sản phẩm</div>
                    <div class="item-qty">SL</div>
                    <div class="item-price">Giá</div>
                    <div class="item-total">Tổng</div>
                </div>
                
                <div class="receipt-item">
                    <div class="item-details">
                        <div class="item-name">Sản phẩm mẫu A</div>
                    </div>
                    <div class="item-line">
                        <span class="item-qty">2</span>
                        <span class="item-price">50,000₫</span>
                        <span class="item-total">100,000₫</span>
                    </div>
                </div>
                
                <div class="receipt-item">
                    <div class="item-details">
                        <div class="item-name">Sản phẩm mẫu B</div>
                    </div>
                    <div class="item-line">
                        <span class="item-qty">1</span>
                        <span class="item-price">75,000₫</span>
                        <span class="item-total">75,000₫</span>
                    </div>
                </div>
            </div>
            
            <div class="receipt-separator"></div>
            
            <div class="receipt-totals">
                <div class="total-line">
                    <span class="total-label">Tạm tính:</span>
                    <span class="total-value">175,000₫</span>
                </div>
                <div class="total-line">
                    <span class="total-label">Thuế:</span>
                    <span class="total-value">17,500₫</span>
                </div>
                <div class="total-line grand-total">
                    <span class="total-label"><strong>Tổng cộng:</strong></span>
                    <span class="total-value"><strong>192,500₫</strong></span>
                </div>
                <div class="total-line payment-method">
                    <span class="total-label">Thanh toán:</span>
                    <span class="total-value">Tiền mặt</span>
                </div>
            </div>
            
            <div class="receipt-separator"></div>
            
            <div class="receipt-footer">
                Cảm ơn bạn đã mua hàng!<br>
                Hẹn gặp lại!
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get preview styles
     */
    private function get_preview_styles($template) {
        $width = isset($template['settings']['paper_width']) ? $template['settings']['paper_width'] : '80';
        $font_size = isset($template['settings']['font_size']) ? $template['settings']['font_size'] : '12';
        
        return "width: {$width}mm; font-size: {$font_size}px; border: 1px solid #ddd; padding: 10px; background: white; font-family: 'Courier New', monospace;";
    }
    
    /**
     * Get classic template CSS
     */
    private function get_classic_template_css() {
        return '
        .vipos-receipt {
            font-family: "Courier New", monospace;
            line-height: 1.4;
            color: #000;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 10px;
        }
        .store-name {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .store-address {
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .receipt-separator {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .receipt-items {
            margin: 10px 0;
        }
        .items-header {
            display: flex;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            margin-bottom: 5px;
        }
        .items-header .item-name {
            flex: 2;
        }
        .items-header .item-qty,
        .items-header .item-price,
        .items-header .item-total {
            flex: 1;
            text-align: right;
        }
        .receipt-item {
            margin-bottom: 5px;
        }
        .item-line {
            display: flex;
        }
        .item-line .item-qty,
        .item-line .item-price,
        .item-line .item-total {
            flex: 1;
            text-align: right;
        }
        .receipt-totals .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .total-line.grand-total {
            border-top: 1px solid #000;
            padding-top: 3px;
            margin-top: 5px;
            font-size: 1.1em;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 10px;
        }
        ';
    }
    
    /**
     * Get modern template CSS
     */
    private function get_modern_template_css() {
        return '
        .vipos-receipt {
            font-family: Arial, sans-serif;
            line-height: 1.5;
            color: #333;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007cba;
        }
        .store-name {
            font-size: 1.4em;
            font-weight: bold;
            color: #007cba;
            margin-bottom: 5px;
        }
        .store-address {
            font-size: 0.9em;
            color: #666;
        }
        .receipt-separator {
            border-top: 1px solid #ddd;
            margin: 15px 0;
        }
        .receipt-items {
            margin: 15px 0;
        }
        .items-header {
            display: flex;
            font-weight: bold;
            background: #f5f5f5;
            padding: 8px 5px;
            border: 1px solid #ddd;
        }
        .items-header .item-name {
            flex: 2;
        }
        .items-header .item-qty,
        .items-header .item-price,
        .items-header .item-total {
            flex: 1;
            text-align: right;
        }
        .receipt-item {
            border-bottom: 1px solid #eee;
            padding: 5px;
        }
        .item-line {
            display: flex;
        }
        .item-line .item-qty,
        .item-line .item-price,
        .item-line .item-total {
            flex: 1;
            text-align: right;
        }
        .receipt-totals {
            background: #f9f9f9;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .receipt-totals .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .total-line.grand-total {
            border-top: 2px solid #007cba;
            padding-top: 8px;
            margin-top: 8px;
            font-size: 1.2em;
            font-weight: bold;
            color: #007cba;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 15px;
            font-style: italic;
            color: #666;
        }
        ';
    }
    
    /**
     * Get minimal template CSS
     */
    private function get_minimal_template_css() {
        return '
        .vipos-receipt {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            line-height: 1.3;
            color: #000;
        }
        .receipt-header {
            text-align: left;
            margin-bottom: 20px;
        }
        .store-name {
            font-size: 1.1em;
            font-weight: 300;
            margin-bottom: 3px;
        }
        .store-address {
            font-size: 0.8em;
            color: #666;
        }
        .receipt-separator {
            border-top: 1px solid #ccc;
            margin: 15px 0;
        }
        .receipt-items {
            margin: 15px 0;
        }
        .items-header {
            display: flex;
            font-weight: 500;
            padding-bottom: 5px;
            border-bottom: 1px solid #ccc;
        }
        .items-header .item-name {
            flex: 3;
        }
        .items-header .item-qty,
        .items-header .item-price,
        .items-header .item-total {
            flex: 1;
            text-align: right;
        }
        .receipt-item {
            padding: 3px 0;
        }
        .item-line {
            display: flex;
            font-size: 0.9em;
        }
        .item-line .item-qty,
        .item-line .item-price,
        .item-line .item-total {
            flex: 1;
            text-align: right;
        }
        .receipt-totals .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            font-size: 0.9em;
        }
        .total-line.grand-total {
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 8px;
            font-weight: bold;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8em;
            color: #999;
        }
        ';
    }
    
    /**
     * Get template structure placeholders
     */
    private function get_classic_template_structure() {
        return array(
            'header' => array('store_logo', 'store_name', 'store_address', 'custom_header'),
            'order_info' => array('order_number', 'date', 'customer', 'cashier'),
            'items' => array('item_list'),
            'totals' => array('subtotal', 'tax', 'discount', 'total', 'payment_method'),
            'footer' => array('custom_footer', 'thanks_message')
        );
    }
    
    private function get_modern_template_structure() {
        return array(
            'header' => array('store_logo', 'store_name', 'store_address', 'custom_header'),
            'order_info' => array('order_number', 'date', 'customer', 'cashier'),
            'items' => array('item_list'),
            'totals' => array('subtotal', 'tax', 'discount', 'total', 'payment_method'),
            'footer' => array('custom_footer', 'thanks_message')
        );
    }
    
    private function get_minimal_template_structure() {
        return array(
            'header' => array('store_name', 'store_address'),
            'order_info' => array('order_number', 'date'),
            'items' => array('item_list'),
            'totals' => array('subtotal', 'total', 'payment_method'),
            'footer' => array('thanks_message')
        );
    }
    
    /**
     * Render template manager interface
     */
    public function render_template_manager() {
        $templates = $this->get_all_templates();
        $active_template = get_option('vipos_active_receipt_template', 'classic');
        
        include VIPOS_PLUGIN_PATH . 'admin/templates/receipt-template-manager.php';
    }
}

// Initialize the template manager
add_action('init', function() {
    VIPOS_Receipt_Template_Manager::instance();
});
