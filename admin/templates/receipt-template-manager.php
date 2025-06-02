<?php
/**
 * VIPOS Receipt Template Manager Interface
 * 
 * @package VIPOS
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="vipos-template-manager">
    <div class="template-manager-header">
        <h3><?php _e('Receipt Template Manager', 'vipos'); ?></h3>
        <button type="button" class="button button-primary" id="create-new-template">
            <?php _e('Create New Template', 'vipos'); ?>
        </button>
    </div>

    <div class="template-grid">
        <?php foreach ($templates as $template_id => $template) : ?>
            <div class="template-card <?php echo $active_template === $template_id ? 'active' : ''; ?>" data-template-id="<?php echo esc_attr($template_id); ?>">
                <div class="template-preview">
                    <div class="template-preview-content">
                        <div class="receipt-sample">
                            <div class="store-name"><?php echo get_bloginfo('name'); ?></div>
                            <div class="receipt-divider">- - - - - - - - - - - - -</div>
                            <div class="receipt-item">Product Sample</div>
                            <div class="receipt-total">Total: $12.99</div>
                        </div>
                    </div>
                </div>
                
                <div class="template-info">
                    <h4><?php echo esc_html($template['name']); ?></h4>
                    <p><?php echo esc_html($template['description']); ?></p>
                    
                    <div class="template-actions">
                        <button type="button" class="button button-small template-preview-btn" data-template-id="<?php echo esc_attr($template_id); ?>">
                            <?php _e('Preview', 'vipos'); ?>
                        </button>
                        
                        <?php if ($active_template !== $template_id) : ?>
                            <button type="button" class="button button-primary button-small template-activate-btn" data-template-id="<?php echo esc_attr($template_id); ?>">
                                <?php _e('Activate', 'vipos'); ?>
                            </button>
                        <?php else : ?>
                            <span class="template-active-badge"><?php _e('Active', 'vipos'); ?></span>
                        <?php endif; ?>
                        
                        <button type="button" class="button button-small template-customize-btn" data-template-id="<?php echo esc_attr($template_id); ?>">
                            <?php _e('Customize', 'vipos'); ?>
                        </button>
                        
                        <?php if (!in_array($template_id, array('classic', 'modern', 'minimal'))) : ?>
                            <button type="button" class="button button-small button-link-delete template-delete-btn" data-template-id="<?php echo esc_attr($template_id); ?>">
                                <?php _e('Delete', 'vipos'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Template Preview Modal -->
<div id="template-preview-modal" class="vipos-modal" style="display: none;">
    <div class="vipos-modal-content">
        <div class="vipos-modal-header">
            <h3><?php _e('Template Preview', 'vipos'); ?></h3>
            <span class="vipos-modal-close">&times;</span>
        </div>
        <div class="vipos-modal-body">
            <div id="template-preview-content"></div>
        </div>
        <div class="vipos-modal-footer">
            <button type="button" class="button" data-modal-close><?php _e('Close', 'vipos'); ?></button>
        </div>
    </div>
</div>

<style>
.vipos-template-manager {
    max-width: 1200px;
    margin: 0 auto;
}

.template-manager-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.template-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}

.template-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.template-card.active {
    border-color: #0073aa;
    box-shadow: 0 0 0 2px rgba(0,115,170,0.2);
}

.template-preview {
    height: 200px;
    background: #f9f9f9;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.template-preview-content {
    transform: scale(0.8);
    pointer-events: none;
}

.receipt-sample {
    width: 200px;
    padding: 15px;
    background: white;
    border: 1px solid #ddd;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.4;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.store-name {
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 8px;
}

.receipt-divider {
    margin: 8px 0;
    color: #666;
}

.receipt-item {
    margin: 5px 0;
    text-align: left;
}

.receipt-total {
    margin-top: 8px;
    font-weight: bold;
    border-top: 1px dashed #999;
    padding-top: 5px;
}

.template-info {
    padding: 20px;
}

.template-info h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #333;
}

.template-info p {
    margin: 0 0 15px 0;
    color: #666;
    font-size: 14px;
    line-height: 1.4;
}

.template-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.template-active-badge {
    background: #46b450;
    color: white;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.button-small {
    padding: 4px 8px !important;
    font-size: 12px !important;
    height: auto !important;
    line-height: 1.4 !important;
}

/* Modal Styles */
.vipos-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vipos-modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.vipos-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vipos-modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.vipos-modal-close {
    font-size: 24px;
    cursor: pointer;
    color: #666;
    line-height: 1;
}

.vipos-modal-close:hover {
    color: #000;
}

.vipos-modal-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.vipos-modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

/* Responsive */
@media (max-width: 768px) {
    .template-grid {
        grid-template-columns: 1fr;
    }
    
    .template-manager-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .template-actions {
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Template card click to activate
    $('.template-card').on('click', function() {
        const templateId = $(this).data('template-id');
        if (!$(this).hasClass('active')) {
            activateTemplate(templateId);
        }
    });
    
    // Preview template
    $('.template-preview-btn').on('click', function(e) {
        e.stopPropagation();
        const templateId = $(this).data('template-id');
        previewTemplate(templateId);
    });
    
    // Activate template
    $('.template-activate-btn').on('click', function(e) {
        e.stopPropagation();
        const templateId = $(this).data('template-id');
        activateTemplate(templateId);
    });
    
    // Customize template
    $('.template-customize-btn').on('click', function(e) {
        e.stopPropagation();
        const templateId = $(this).data('template-id');
        customizeTemplate(templateId);
    });
    
    // Delete template
    $('.template-delete-btn').on('click', function(e) {
        e.stopPropagation();
        const templateId = $(this).data('template-id');
        if (confirm('<?php _e("Are you sure you want to delete this template?", "vipos"); ?>')) {
            deleteTemplate(templateId);
        }
    });
    
    // Close modal
    $('.vipos-modal-close, [data-modal-close]').on('click', function() {
        $('.vipos-modal').fadeOut();
    });
    
    // Close modal on backdrop click
    $('.vipos-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut();
        }
    });
    
    function activateTemplate(templateId) {
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
                    // Update UI
                    $('.template-card').removeClass('active');
                    $('.template-card[data-template-id="' + templateId + '"]').addClass('active');
                    
                    // Update buttons
                    $('.template-activate-btn').show().text('<?php _e("Activate", "vipos"); ?>');
                    $('.template-active-badge').remove();
                    
                    const $activeCard = $('.template-card[data-template-id="' + templateId + '"]');
                    $activeCard.find('.template-activate-btn').hide();
                    $activeCard.find('.template-actions').append('<span class="template-active-badge"><?php _e("Active", "vipos"); ?></span>');
                    
                    // Show success message
                    if (typeof viposShowNotice === 'function') {
                        viposShowNotice(response.data.message, 'success');
                    }
                } else {
                    alert(response.data.message || '<?php _e("Failed to activate template", "vipos"); ?>');
                }
            },
            error: function() {
                alert('<?php _e("Error activating template", "vipos"); ?>');
            }
        });
    }
    
    function previewTemplate(templateId) {
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
                    $('#template-preview-modal').fadeIn();
                } else {
                    alert(response.data.message || '<?php _e("Failed to preview template", "vipos"); ?>');
                }
            },
            error: function() {
                alert('<?php _e("Error loading template preview", "vipos"); ?>');
            }
        });
    }
    
    function customizeTemplate(templateId) {
        // Redirect to settings page with template customization
        window.location.href = '<?php echo admin_url("admin.php?page=vipos-settings&tab=receipt"); ?>&customize=' + templateId;
    }
    
    function deleteTemplate(templateId) {
        $.ajax({
            url: viposSettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'vipos_delete_template',
                template_id: templateId,
                nonce: viposSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.template-card[data-template-id="' + templateId + '"]').fadeOut(function() {
                        $(this).remove();
                    });
                    
                    if (typeof viposShowNotice === 'function') {
                        viposShowNotice(response.data.message, 'success');
                    }
                } else {
                    alert(response.data.message || '<?php _e("Failed to delete template", "vipos"); ?>');
                }
            },
            error: function() {
                alert('<?php _e("Error deleting template", "vipos"); ?>');
            }
        });
    }
});
</script>
