<?php
/**
 * VIPOS Settings Page Template
 * 
 * @package VIPOS
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = VIPOS_Settings::instance();
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
$tabs = array(
    'general' => __('General', 'vipos'),
    'receipt' => __('Receipt', 'vipos'), 
    'tax'     => __('Tax', 'vipos'),
    'access'  => __('Access', 'vipos'),
);
?>

<div class="wrap vipos-settings">
    <h1><?php _e('VIPOS Settings', 'vipos'); ?></h1>
    
    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_id => $tab_name) : ?>
            <a href="<?php echo admin_url('admin.php?page=vipos-settings&tab=' . $tab_id); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_name); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="vipos-settings-content">
        <form id="vipos-settings-form" method="post">
            <?php wp_nonce_field('vipos_save_settings', 'vipos_settings_nonce'); ?>
            <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
            
            <?php if ($current_tab === 'general') : ?>
                <div class="vipos-settings-section">
                    <h2><?php _e('General Settings', 'vipos'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="store_name"><?php _e('Store Name', 'vipos'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="store_name" name="general[store_name]" 
                                       value="<?php echo esc_attr($settings->get_setting('general', 'store_name')); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Name displayed on receipts and POS interface', 'vipos'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="store_address"><?php _e('Store Address', 'vipos'); ?></label>
                            </th>
                            <td>
                                <textarea id="store_address" name="general[store_address]" 
                                          rows="3" class="large-text"><?php echo esc_textarea($settings->get_setting('general', 'store_address')); ?></textarea>
                                <p class="description"><?php _e('Address displayed on receipts', 'vipos'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="default_customer"><?php _e('Default Customer', 'vipos'); ?></label>
                            </th>
                            <td>
                                <select id="default_customer" name="general[default_customer]">
                                    <option value=""><?php _e('None', 'vipos'); ?></option>
                                    <?php
                                    $customers = get_users(array('role' => 'customer', 'number' => 50));
                                    $selected_customer = $settings->get_setting('general', 'default_customer');
                                    foreach ($customers as $customer) {
                                        echo '<option value="' . $customer->ID . '"' . selected($selected_customer, $customer->ID, false) . '>' . 
                                             esc_html($customer->display_name . ' (' . $customer->user_email . ')') . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Default customer for walk-in sales', 'vipos'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="auto_print_receipt"><?php _e('Auto Print Receipt', 'vipos'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="auto_print_receipt" name="general[auto_print_receipt]" 
                                           value="1" <?php checked($settings->get_setting('general', 'auto_print_receipt'), 1); ?> />
                                    <?php _e('Automatically print receipt after successful payment', 'vipos'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="sound_enabled"><?php _e('Sound Effects', 'vipos'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="sound_enabled" name="general[sound_enabled]" 
                                           value="1" <?php checked($settings->get_setting('general', 'sound_enabled'), 1); ?> />
                                    <?php _e('Enable sound effects for POS actions', 'vipos'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($current_tab === 'receipt') : ?>
                <div class="vipos-settings-section">
                    <h2><?php _e('Receipt Settings', 'vipos'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="receipt_template"><?php _e('Receipt Template', 'vipos'); ?></label>
                            </th>
                            <td>
                                <select id="receipt_template" name="receipt[template]">
                                    <?php
                                    if (class_exists('VIPOS_Receipt_Template_Manager')) {
                                        $template_manager = VIPOS_Receipt_Template_Manager::instance();
                                        $templates = $template_manager->get_all_templates();
                                        $active_template = get_option('vipos_active_receipt_template', 'classic');
                                        
                                        foreach ($templates as $template_id => $template) {
                                            echo '<option value="' . esc_attr($template_id) . '"' . selected($active_template, $template_id, false) . '>' . 
                                                 esc_html($template['name']) . '</option>';
                                        }
                                    } else {
                                        echo '<option value="classic">Classic</option>';
                                        echo '<option value="modern">Modern</option>';
                                        echo '<option value="minimal">Minimal</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Choose a receipt template design', 'vipos'); ?></p>
                                <div class="template-actions" style="margin-top: 10px;">
                                    <button type="button" class="button" id="preview-template"><?php _e('Preview Template', 'vipos'); ?></button>
                                    <button type="button" class="button" id="customize-template"><?php _e('Customize Template', 'vipos'); ?></button>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="receipt_header"><?php _e('Receipt Header', 'vipos'); ?></label>
                            </th>
                            <td>
                                <textarea id="receipt_header" name="receipt[header]" 
                                          rows="3" class="large-text"><?php echo esc_textarea($settings->get_setting('receipt', 'header')); ?></textarea>
                                <p class="description"><?php _e('Custom text displayed at the top of receipts', 'vipos'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="receipt_footer"><?php _e('Receipt Footer', 'vipos'); ?></label>
                            </th>
                            <td>
                                <textarea id="receipt_footer" name="receipt[footer]" 
                                          rows="3" class="large-text"><?php echo esc_textarea($settings->get_setting('receipt', 'footer')); ?></textarea>
                                <p class="description"><?php _e('Custom text displayed at the bottom of receipts', 'vipos'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="receipt_logo"><?php _e('Store Logo', 'vipos'); ?></label>
                            </th>
                            <td>
                                <div class="logo-upload-container">
                                    <input type="hidden" id="receipt_logo" name="receipt[logo]" value="<?php echo esc_attr($settings->get_setting('receipt', 'logo')); ?>" />
                                    <div class="logo-preview">
                                        <?php 
                                        $logo_id = $settings->get_setting('receipt', 'logo');
                                        if ($logo_id) {
                                            $logo_url = wp_get_attachment_image_url($logo_id, 'thumbnail');
                                            if ($logo_url) {
                                                echo '<img src="' . esc_url($logo_url) . '" style="max-width: 150px; max-height: 100px;" />';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <div class="logo-actions">
                                        <button type="button" class="button" id="upload-logo"><?php _e('Upload Logo', 'vipos'); ?></button>
                                        <button type="button" class="button" id="remove-logo" style="<?php echo $logo_id ? '' : 'display:none;'; ?>"><?php _e('Remove', 'vipos'); ?></button>
                                    </div>
                                </div>
                                <p class="description"><?php _e('Logo displayed on receipts (recommended size: 200x100px)', 'vipos'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="show_sku"><?php _e('Show SKU', 'vipos'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="show_sku" name="receipt[show_sku]" 
                                           value="1" <?php checked($settings->get_setting('receipt', 'show_sku'), 1); ?> />
                                    <?php _e('Display product SKU on receipts', 'vipos'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="show_barcode"><?php _e('Show Barcode', 'vipos'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="show_barcode" name="receipt[show_barcode]" 
                                           value="1" <?php checked($settings->get_setting('receipt', 'show_barcode'), 1); ?> />
                                    <?php _e('Display order barcode on receipts', 'vipos'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="receipt_width"><?php _e('Receipt Width', 'vipos'); ?></label>
                            </th>
                            <td>
                                <select id="receipt_width" name="receipt[width]">
                                    <option value="58" <?php selected($settings->get_setting('receipt', 'width'), '58'); ?>>58mm</option>
                                    <option value="80" <?php selected($settings->get_setting('receipt', 'width'), '80'); ?>>80mm</option>
                                </select>
                                <p class="description"><?php _e('Receipt paper width', 'vipos'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="receipt_font_size"><?php _e('Font Size', 'vipos'); ?></label>
                            </th>
                            <td>
                                <select id="receipt_font_size" name="receipt[font_size]">
                                    <option value="10" <?php selected($settings->get_setting('receipt', 'font_size'), '10'); ?>>10px</option>
                                    <option value="11" <?php selected($settings->get_setting('receipt', 'font_size'), '11'); ?>>11px</option>
                                    <option value="12" <?php selected($settings->get_setting('receipt', 'font_size'), '12'); ?>>12px</option>
                                    <option value="13" <?php selected($settings->get_setting('receipt', 'font_size'), '13'); ?>>13px</option>
                                    <option value="14" <?php selected($settings->get_setting('receipt', 'font_size'), '14'); ?>>14px</option>
                                </select>
                                <p class="description"><?php _e('Receipt font size', 'vipos'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Template Preview Section -->
                    <div class="template-preview-section" style="margin-top: 30px;">
                        <h3><?php _e('Template Preview', 'vipos'); ?></h3>
                        <div class="template-preview-container" style="border: 1px solid #ddd; padding: 20px; background: #f9f9f9; text-align: center;">
                            <div id="template-preview-content">
                                <p><?php _e('Select a template and click "Preview Template" to see how it looks.', 'vipos'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($current_tab === 'tax') : ?>
                <div class="vipos-settings-section">
                    <h2><?php _e('Tax Settings', 'vipos'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="tax_inclusive"><?php _e('Tax Inclusive Pricing', 'vipos'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="tax_inclusive" name="tax[inclusive]" 
                                           value="1" <?php checked($settings->get_setting('tax', 'inclusive'), 1); ?> />
                                    <?php _e('Product prices include tax', 'vipos'); ?>
                                </label>
                                <p class="description"><?php _e('When enabled, displayed prices include tax', 'vipos'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="tax_rounding"><?php _e('Tax Rounding', 'vipos'); ?></label>
                            </th>
                            <td>
                                <select id="tax_rounding" name="tax[rounding]">
                                    <option value="no" <?php selected($settings->get_setting('tax', 'rounding'), 'no'); ?>><?php _e('No rounding', 'vipos'); ?></option>
                                    <option value="round" <?php selected($settings->get_setting('tax', 'rounding'), 'round'); ?>><?php _e('Round to nearest cent', 'vipos'); ?></option>
                                    <option value="up" <?php selected($settings->get_setting('tax', 'rounding'), 'up'); ?>><?php _e('Round up', 'vipos'); ?></option>
                                    <option value="down" <?php selected($settings->get_setting('tax', 'rounding'), 'down'); ?>><?php _e('Round down', 'vipos'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($current_tab === 'access') : ?>
                <div class="vipos-settings-section">
                    <h2><?php _e('Access Control', 'vipos'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="pos_access_roles"><?php _e('POS Access Roles', 'vipos'); ?></label>
                            </th>
                            <td>                                <?php
                                $roles = wp_roles()->get_names();
                                $selected_roles = $settings->get_setting('access', 'pos_roles', array('administrator', 'shop_manager'));
                                // Ensure $selected_roles is always an array
                                if (!is_array($selected_roles)) {
                                    $selected_roles = !empty($selected_roles) ? array($selected_roles) : array();
                                }
                                foreach ($roles as $role_key => $role_name) :
                                ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="access[pos_roles][]" value="<?php echo esc_attr($role_key); ?>"
                                               <?php checked(in_array($role_key, $selected_roles)); ?> />
                                        <?php echo esc_html($role_name); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description"><?php _e('User roles that can access the POS interface', 'vipos'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="settings_access_roles"><?php _e('Settings Access Roles', 'vipos'); ?></label>
                            </th>
                            <td>                                <?php
                                $selected_settings_roles = $settings->get_setting('access', 'settings_roles', array('administrator'));
                                // Ensure $selected_settings_roles is always an array
                                if (!is_array($selected_settings_roles)) {
                                    $selected_settings_roles = !empty($selected_settings_roles) ? array($selected_settings_roles) : array();
                                }
                                foreach ($roles as $role_key => $role_name) :
                                ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="access[settings_roles][]" value="<?php echo esc_attr($role_key); ?>"
                                               <?php checked(in_array($role_key, $selected_settings_roles)); ?> />
                                        <?php echo esc_html($role_name); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description"><?php _e('User roles that can access POS settings', 'vipos'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php endif; ?>
            
            <div class="vipos-settings-actions">
                <button type="submit" class="button button-primary vipos-save-settings">
                    <?php _e('Save Settings', 'vipos'); ?>
                </button>
                <button type="button" class="button vipos-reset-settings" data-tab="<?php echo esc_attr($current_tab); ?>">
                    <?php _e('Reset to Defaults', 'vipos'); ?>
                </button>
                <span class="vipos-settings-status"></span>
            </div>
        </form>
    </div>
</div>

<style>
.vipos-settings {
    max-width: 1200px;
}

.vipos-settings-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
    margin-top: 0;
}

.vipos-settings-section {
    margin-bottom: 30px;
}

.vipos-settings-section h2 {
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.vipos-settings-actions {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 30px;
}

.vipos-settings-status {
    margin-left: 10px;
    font-weight: 600;
}

.vipos-settings-status.success {
    color: #46b450;
}

.vipos-settings-status.error {
    color: #dc3232;
}

.vipos-save-settings:disabled,
.vipos-reset-settings:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
