/**
 * VIPOS Settings Page JavaScript
 * 
 * @package VIPOS
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        const SettingsPage = {
            
            /**
             * Initialize settings page
             */
            init: function() {
                this.bindEvents();
                this.initTooltips();
                this.initializeComponents();
            },
            
            /**
             * Bind event handlers
             */
            bindEvents: function() {
                // Save settings
                $('#vipos-settings-form').on('submit', this.saveSettings.bind(this));
                
                // Reset settings
                $('.vipos-reset-settings').on('click', this.resetSettings.bind(this));
                
                // Tab switching (handled by WordPress core, but we can add custom logic)
                $('.nav-tab').on('click', function() {
                    const url = $(this).attr('href');
                    if (url && url.indexOf('#') === -1) {
                        window.location.href = url;
                        return false;
                    }
                });
                
                // Dynamic customer search
                $('#default_customer').select2({
                    placeholder: viposSettings.i18n.selectCustomer,
                    allowClear: true,
                    ajax: {
                        url: viposSettings.ajaxUrl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'vipos_search_customers',
                                term: params.term,
                                nonce: viposSettings.nonce
                            };
                        },
                        processResults: function(data) {
                            if (data.success) {
                                return {
                                    results: data.data.map(function(customer) {
                                        return {
                                            id: customer.id,
                                            text: customer.display_name + ' (' + customer.email + ')'
                                        };
                                    })
                                };
                            }
                            return { results: [] };
                        },
                        cache: true
                    }
                });
                
                // Role checkbox dependencies
                this.handleRoleDependencies();
                
                // Template management
                $(document).on('click', '#preview-template', this.previewTemplate.bind(this));
                $(document).on('click', '#customize-template', this.customizeTemplate.bind(this));
                $(document).on('change', '#receipt_template', this.onTemplateChange.bind(this));
                
                // Logo upload
                $(document).on('click', '#upload-logo', this.uploadLogo.bind(this));
                $(document).on('click', '#remove-logo', this.removeLogo.bind(this));
            },
            
            /**
             * Handle role checkbox dependencies
             */
            handleRoleDependencies: function() {
                const $posRoles = $('input[name="access[pos_roles][]"]');
                const $settingsRoles = $('input[name="access[settings_roles][]"]');
                
                // If administrator is unchecked for settings, warn user
                $settingsRoles.filter('[value="administrator"]').on('change', function() {
                    if (!$(this).is(':checked')) {
                        if (!confirm(viposSettings.i18n.adminRoleWarning)) {
                            $(this).prop('checked', true);
                        }
                    }
                });
                
                // Auto-check POS roles when settings roles are checked
                $settingsRoles.on('change', function() {
                    if ($(this).is(':checked')) {
                        $posRoles.filter('[value="' + $(this).val() + '"]').prop('checked', true);
                    }
                });
            },
            
            /**
             * Save settings via AJAX
             */
            saveSettings: function(e) {
                e.preventDefault();
                
                const $form = $(e.target);
                const $submitBtn = $form.find('.vipos-save-settings');
                const $status = $('.vipos-settings-status');
                
                // Disable form during submission
                $submitBtn.prop('disabled', true);
                $status.removeClass('success error').text(viposSettings.i18n.saving);
                
                // Prepare form data
                const formData = new FormData($form[0]);
                formData.append('action', 'vipos_save_settings');
                formData.append('nonce', viposSettings.nonce);
                
                // Send AJAX request
                $.ajax({
                    url: viposSettings.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $status.addClass('success').text(viposSettings.i18n.saved);
                            
                            // Show success message briefly
                            setTimeout(function() {
                                $status.fadeOut();
                            }, 3000);
                        } else {
                            $status.addClass('error').text(response.data || viposSettings.i18n.saveError);
                        }
                    },
                    error: function() {
                        $status.addClass('error').text(viposSettings.i18n.saveError);
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false);
                    }
                });
            },
            
            /**
             * Reset settings to defaults
             */
            resetSettings: function(e) {
                e.preventDefault();
                
                if (!confirm(viposSettings.i18n.resetConfirm)) {
                    return;
                }
                
                const $btn = $(e.target);
                const tab = $btn.data('tab');
                const $status = $('.vipos-settings-status');
                
                $btn.prop('disabled', true);
                $status.removeClass('success error').text(viposSettings.i18n.resetting);
                
                $.ajax({
                    url: viposSettings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vipos_reset_settings',
                        tab: tab,
                        nonce: viposSettings.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.addClass('success').text(viposSettings.i18n.resetSuccess);
                            
                            // Reload page after brief delay
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            $status.addClass('error').text(response.data || viposSettings.i18n.resetError);
                        }
                    },
                    error: function() {
                        $status.addClass('error').text(viposSettings.i18n.resetError);
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            },
            
            /**
             * Initialize tooltips
             */
            initTooltips: function() {
                if ($.fn.tooltip) {
                    $('[data-tooltip]').tooltip({
                        position: {
                            my: 'center bottom-20',
                            at: 'center top'
                        }
                    });
                }
            },
            
            /**
             * Test printer connection
             */
            testPrinter: function() {
                const $status = $('.printer-test-status');
                
                $status.text(viposSettings.i18n.testingPrinter);
                
                $.ajax({
                    url: viposSettings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vipos_test_printer',
                        nonce: viposSettings.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.addClass('success').text(viposSettings.i18n.printerTestSuccess);
                        } else {
                            $status.addClass('error').text(response.data || viposSettings.i18n.printerTestError);
                        }
                    },
                    error: function() {
                        $status.addClass('error').text(viposSettings.i18n.printerTestError);
                    }
                });
            },
            
            /**
             * Import settings from file
             */
            importSettings: function(file) {
                const formData = new FormData();
                formData.append('action', 'vipos_import_settings');
                formData.append('settings_file', file);
                formData.append('nonce', viposSettings.nonce);
                
                $.ajax({
                    url: viposSettings.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert(viposSettings.i18n.importSuccess);
                            window.location.reload();
                        } else {
                            alert(response.data || viposSettings.i18n.importError);
                        }
                    },
                    error: function() {
                        alert(viposSettings.i18n.importError);
                    }
                });
            },
            
            /**
             * Export settings to file
             */
            exportSettings: function() {
                window.location.href = viposSettings.ajaxUrl + '?action=vipos_export_settings&nonce=' + viposSettings.nonce;
            },
            
            /**
             * Preview template
             */
            previewTemplate: function(e) {
                e.preventDefault();
                
                const templateId = $('#receipt_template').val();
                if (!templateId) {
                    alert('Vui lòng chọn một template để xem trước.');
                    return;
                }
                
                this.showLoader('#template-preview-content');
                
                $.ajax({
                    url: viposSettings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vipos_preview_receipt_template',
                        template_id: templateId,
                        nonce: viposSettings.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#template-preview-content').html(response.data.html);
                        } else {
                            $('#template-preview-content').html('<p class="error">Lỗi: ' + (response.data.message || 'Không thể xem trước template') + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#template-preview-content').html('<p class="error">Lỗi kết nối: ' + error + '</p>');
                    }
                });
            },
            
            /**
             * Customize template
             */
            customizeTemplate: function(e) {
                e.preventDefault();
                
                const templateId = $('#receipt_template').val();
                if (!templateId) {
                    alert('Vui lòng chọn một template để tùy chỉnh.');
                    return;
                }
                
                // Open template customization modal
                this.openTemplateCustomizationModal(templateId);
            },
            
            /**
             * Handle template change
             */
            onTemplateChange: function(e) {
                const templateId = $(e.target).val();
                
                // Update active template option
                $.ajax({
                    url: viposSettings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vipos_set_active_template',
                        template_id: templateId,
                        nonce: viposSettings.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Clear preview
                            $('#template-preview-content').html('<p>Template đã được cập nhật. Nhấp "Xem trước Template" để xem.</p>');
                        }
                    }
                });
            },
            
            /**
             * Upload logo
             */
            uploadLogo: function(e) {
                e.preventDefault();
                
                // Create WordPress media frame
                const frame = wp.media({
                    title: 'Chọn Logo cho Hóa đơn',
                    button: {
                        text: 'Sử dụng Logo này'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                // Open frame
                frame.open();
                
                // Handle selection
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    
                    // Update hidden input
                    $('#receipt_logo').val(attachment.id);
                    
                    // Update preview
                    $('.logo-preview').html('<img src="' + attachment.url + '" style="max-width: 150px; max-height: 100px;" />');
                    
                    // Show remove button
                    $('#remove-logo').show();
                });
            },
            
            /**
             * Remove logo
             */
            removeLogo: function(e) {
                e.preventDefault();
                
                // Clear hidden input
                $('#receipt_logo').val('');
                
                // Clear preview
                $('.logo-preview').empty();
                
                // Hide remove button
                $('#remove-logo').hide();
            },
            
            /**
             * Initialize components
             */
            initializeComponents: function() {
                // Initialize Select2 for customer select
                if ($('#default_customer').length) {
                    $('#default_customer').select2({
                        placeholder: viposSettings.i18n.selectCustomer,
                        allowClear: true,
                        ajax: {
                            url: viposSettings.ajaxUrl,
                            dataType: 'json',
                            delay: 250,
                            data: function(params) {
                                return {
                                    action: 'vipos_search_customers',
                                    term: params.term,
                                    nonce: viposSettings.nonce
                                };
                            },
                            processResults: function(data) {
                                if (data.success) {
                                    return {
                                        results: data.data.map(function(customer) {
                                            return {
                                                id: customer.id,
                                                text: customer.display_name + ' (' + customer.email + ')'
                                            };
                                        })
                                    };
                                }
                                return { results: [] };
                            },
                            cache: true
                        }
                    });
                }
            },
            
            /**
             * Open template customization modal
             */
            openTemplateCustomizationModal: function(templateId) {
                // Create modal HTML
                const modalHtml = `
                    <div id="template-customization-modal" class="vipos-modal" style="display: none;">
                        <div class="vipos-modal-content" style="width: 90%; max-width: 1200px;">
                            <div class="vipos-modal-header">
                                <h3>Tùy chỉnh Template Hóa đơn</h3>
                                <span class="vipos-modal-close">&times;</span>
                            </div>
                            <div class="vipos-modal-body">
                                <div class="template-customization-container">
                                    <div class="customization-sidebar">
                                        <h4>Tùy chọn Template</h4>
                                        <div class="template-options">
                                            <label>
                                                <input type="checkbox" name="show_logo" checked> Hiển thị Logo
                                            </label>
                                            <label>
                                                <input type="checkbox" name="show_header" checked> Hiển thị Header
                                            </label>
                                            <label>
                                                <input type="checkbox" name="show_footer" checked> Hiển thị Footer
                                            </label>
                                            <label>
                                                <input type="checkbox" name="show_sku"> Hiển thị SKU sản phẩm
                                            </label>
                                            <label>
                                                <input type="checkbox" name="show_barcode"> Hiển thị Barcode
                                            </label>
                                        </div>
                                        
                                        <h4>Tùy chỉnh CSS</h4>
                                        <textarea id="template-css" rows="20" style="width: 100%; font-family: monospace; font-size: 12px;"
                                                  placeholder="/* Nhập CSS tùy chỉnh ở đây */"></textarea>
                                    </div>
                                    <div class="customization-preview">
                                        <h4>Xem trước</h4>
                                        <div class="template-preview-container" style="border: 1px solid #ddd; padding: 20px; background: #f9f9f9; height: 500px; overflow-y: auto;">
                                            <div id="live-preview">Đang tải...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="vipos-modal-footer">
                                <button type="button" class="button" id="cancel-customization">Hủy</button>
                                <button type="button" class="button button-primary" id="save-template-customization">Lưu Template</button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Add modal to body if not exists
                if ($('#template-customization-modal').length === 0) {
                    $('body').append(modalHtml);
                }
                
                // Show modal
                $('#template-customization-modal').fadeIn();
                
                // Load template data
                this.loadTemplateForCustomization(templateId);
                
                // Bind modal events
                this.bindModalEvents();
            },
            
            /**
             * Load template for customization
             */
            loadTemplateForCustomization: function(templateId) {
                $.ajax({
                    url: viposSettings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vipos_get_template_data',
                        template_id: templateId,
                        nonce: viposSettings.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            const template = response.data.template;
                            
                            // Load CSS
                            $('#template-css').val(template.css || '');
                            
                            // Load settings
                            if (template.settings) {
                                $('input[name="show_logo"]').prop('checked', template.settings.show_logo == 1);
                                $('input[name="show_header"]').prop('checked', template.settings.show_header == 1);
                                $('input[name="show_footer"]').prop('checked', template.settings.show_footer == 1);
                                $('input[name="show_sku"]').prop('checked', template.settings.show_sku == 1);
                                $('input[name="show_barcode"]').prop('checked', template.settings.show_barcode == 1);
                            }
                            
                            // Generate preview
                            VIPOSSettings.updateLivePreview();
                        }
                    }
                });
            },
            
            /**
             * Bind modal events
             */
            bindModalEvents: function() {
                // Close modal
                $(document).on('click', '.vipos-modal-close, #cancel-customization', function() {
                    $('#template-customization-modal').fadeOut();
                });
                
                // Live preview updates
                $(document).on('change keyup', '#template-css, .template-options input', this.updateLivePreview.bind(this));
                
                // Save customization
                $(document).on('click', '#save-template-customization', this.saveTemplateCustomization.bind(this));
            },
            
            /**
             * Update live preview
             */
            updateLivePreview: function() {
                const css = $('#template-css').val();
                const showLogo = $('input[name="show_logo"]').is(':checked');
                const showHeader = $('input[name="show_header"]').is(':checked');
                const showFooter = $('input[name="show_footer"]').is(':checked');
                const showSku = $('input[name="show_sku"]').is(':checked');
                const showBarcode = $('input[name="show_barcode"]').is(':checked');
                
                // Generate preview HTML with custom CSS
                let previewHtml = `
                    <style>${css}</style>
                    <div class="vipos-receipt vipos-receipt-80mm">
                        ${showHeader ? '<div class="receipt-header"><div class="store-name">Tên Cửa Hàng</div><div class="store-address">Địa chỉ cửa hàng</div></div>' : ''}
                        
                        <div class="receipt-order-info">
                            <div class="order-number"><strong>Đơn hàng #:</strong> 12345</div>
                            <div class="order-date"><strong>Ngày:</strong> ${new Date().toLocaleDateString('vi-VN')}</div>
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
                                    <div class="item-name">Sản phẩm mẫu A${showSku ? '<br><small class="item-sku">SKU: SP001</small>' : ''}</div>
                                </div>
                                <div class="item-line">
                                    <span class="item-qty">2</span>
                                    <span class="item-price">50,000₫</span>
                                    <span class="item-total">100,000₫</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="receipt-separator"></div>
                        
                        <div class="receipt-totals">
                            <div class="total-line">
                                <span class="total-label">Tạm tính:</span>
                                <span class="total-value">100,000₫</span>
                            </div>
                            <div class="total-line grand-total">
                                <span class="total-label"><strong>Tổng cộng:</strong></span>
                                <span class="total-value"><strong>100,000₫</strong></span>
                            </div>
                        </div>
                        
                        ${showFooter ? '<div class="receipt-separator"></div><div class="receipt-footer">Cảm ơn bạn đã mua hàng!</div>' : ''}
                        ${showBarcode ? '<div class="receipt-barcode">||||| |||| ||||| ||||</div>' : ''}
                    </div>
                `;
                
                $('#live-preview').html(previewHtml);
            },
            
            /**
             * Save template customization
             */
            saveTemplateCustomization: function() {
                const templateId = $('#receipt_template').val();
                const css = $('#template-css').val();
                const settings = {
                    show_logo: $('input[name="show_logo"]').is(':checked') ? 1 : 0,
                    show_header: $('input[name="show_header"]').is(':checked') ? 1 : 0,
                    show_footer: $('input[name="show_footer"]').is(':checked') ? 1 : 0,
                    show_sku: $('input[name="show_sku"]').is(':checked') ? 1 : 0,
                    show_barcode: $('input[name="show_barcode"]').is(':checked') ? 1 : 0
                };
                
                $.ajax({
                    url: viposSettings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vipos_save_template_customization',
                        template_id: templateId,
                        css: css,
                        settings: settings,
                        nonce: viposSettings.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Template đã được lưu thành công!');
                            $('#template-customization-modal').fadeOut();
                            
                            // Update main preview
                            VIPOSSettings.previewTemplate({ preventDefault: function() {} });
                        } else {
                            alert('Có lỗi xảy ra: ' + (response.data.message || 'Không thể lưu template'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Lỗi kết nối: ' + error);
                    }
                });
            },
            
            /**
             * Show loader
             */
            showLoader: function(selector) {
                $(selector).html('<div class="vipos-loading"><div class="spinner"></div><p>Đang tải...</p></div>');
            }
        };
        
        // Initialize settings page
        SettingsPage.init();
        
        // Make methods available globally for inline event handlers
        window.viposSettings = window.viposSettings || {};
        window.viposSettings.testPrinter = SettingsPage.testPrinter.bind(SettingsPage);
        window.viposSettings.exportSettings = SettingsPage.exportSettings.bind(SettingsPage);
        window.viposSettings.importSettings = SettingsPage.importSettings.bind(SettingsPage);
    });
    
    /**
     * Handle file input for settings import
     */
    $(document).on('change', '#vipos-import-file', function() {
        const file = this.files[0];
        if (file && file.type === 'application/json') {
            if (confirm(viposSettings.i18n.importConfirm)) {
                window.viposSettings.importSettings(file);
            }
        } else {
            alert(viposSettings.i18n.invalidFileType);
        }
        // Reset file input
        $(this).val('');
    });
    
    /**
     * Color picker initialization
     */
    $(document).ready(function() {
        if ($.fn.wpColorPicker) {
            $('.vipos-color-picker').wpColorPicker();
        }
    });
    
    /**
     * Media uploader for logo/images
     */
    $(document).on('click', '.vipos-upload-button', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $input = $button.siblings('.vipos-upload-input');
        const $preview = $button.siblings('.vipos-upload-preview');
        
        const frame = wp.media({
            title: viposSettings.i18n.selectImage,
            button: {
                text: viposSettings.i18n.useImage
            },
            multiple: false
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            $input.val(attachment.url);
            $preview.html('<img src="' + attachment.url + '" style="max-width: 150px; height: auto;">');
        });
        
        frame.open();
    });
    
    /**
     * Remove uploaded image
     */
    $(document).on('click', '.vipos-remove-upload', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $input = $button.siblings('.vipos-upload-input');
        const $preview = $button.siblings('.vipos-upload-preview');
        
        $input.val('');
        $preview.empty();
    });

})(jQuery);
