<?php
/**
 * VIPOS Helper Functions
 * 
 * @package VIPOS
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load template with error handling
 * 
 * @param string $template_path Path to template file
 * @param array $args Arguments to pass to template
 * @return string Template content
 */
function vipos_load_template($template_path, $args = array()) {
    if (!file_exists($template_path)) {
        return sprintf(__('Error: Template file not found at %s', 'vipos'), $template_path);
    }
    
    // Extract variables for the template
    if (!empty($args)) {
        extract($args);
    }
    
    // Buffer output
    ob_start();
    try {
        include $template_path;
        return ob_get_clean();
    } catch (Exception $e) {
        ob_end_clean();
        return sprintf(__('Error in template: %s', 'vipos'), $e->getMessage());
    }
}
