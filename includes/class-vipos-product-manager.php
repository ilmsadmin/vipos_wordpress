<?php
/**
 * VIPOS Product Manager Class
 * 
 * Manages products for POS operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_Product_Manager {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Products per page
     */
    private $products_per_page = 20;
    
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
        $this->products_per_page = get_option('vipos_products_per_page', 20);
    }
    
    /**
     * Get products for POS interface
     */
    public function get_products_for_pos($page = 1, $per_page = null) {
        if ($per_page === null) {
            $per_page = $this->products_per_page;
        }
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => array(
                array(
                    'key' => '_manage_stock',
                    'value' => 'yes',
                    'compare' => '!=',
                ),
                array(
                    'key' => '_stock_status',
                    'value' => 'outofstock',
                    'compare' => '!=',
                )
            ),
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product && $this->is_product_available_for_pos($product)) {
                    $products[] = $this->format_product_for_pos($product);
                }
            }
            wp_reset_postdata();
        }
        
        return array(
            'products' => $products,
            'total_pages' => $query->max_num_pages,
            'total_products' => $query->found_posts,
            'current_page' => $page
        );
    }
    
    /**
     * Search products
     */
    public function search_products($search_term, $category_id = 0, $page = 1) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $this->products_per_page,
            'paged' => $page,
            's' => $search_term,
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'outofstock',
                    'compare' => '!=',
                )
            )
        );
        
        // Add category filter if specified
        if ($category_id > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id
                )
            );
        }
        
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product && $this->is_product_available_for_pos($product)) {
                    $products[] = $this->format_product_for_pos($product);
                }
            }
            wp_reset_postdata();
        }
        
        return array(
            'products' => $products,
            'total_pages' => $query->max_num_pages,
            'total_products' => $query->found_posts,
            'current_page' => $page,
            'search_term' => $search_term,
            'category_id' => $category_id
        );
    }
      /**
     * Get product categories
     */
    public function get_categories() {
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        $categories = array();
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                // Check if this category has at least one in-stock product
                $args = array(
                    'post_type' => 'product',
                    'posts_per_page' => 1, // We only need to know if at least one exists
                    'post_status' => 'publish',
                    'meta_query' => array(
                        array(
                            'key' => '_stock_status',
                            'value' => 'instock',
                            'compare' => '=',
                        )
                    ),
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $term->term_id,
                        )
                    )
                );
                
                $query = new WP_Query($args);
                
                // If this category has at least one in-stock product, add it to our list
                if ($query->have_posts()) {
                    $categories[] = array(
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'count' => $term->count
                    );
                }
                
                wp_reset_postdata();
            }
        }
        
        return $categories;
    }
    
    /**
     * Get product by ID
     */
    public function get_product_by_id($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product || !$this->is_product_available_for_pos($product)) {
            return false;
        }
        
        return $this->format_product_for_pos($product);
    }
    
    /**
     * Get product by SKU
     */
    public function get_product_by_sku($sku) {
        $product_id = wc_get_product_id_by_sku($sku);
        
        if (!$product_id) {
            return false;
        }
        
        return $this->get_product_by_id($product_id);
    }
    
    /**
     * Check if product is available for POS
     */
    private function is_product_available_for_pos($product) {
        // Check if product is published
        if ($product->get_status() !== 'publish') {
            return false;
        }
        
        // Check stock status
        if (!$product->is_in_stock()) {
            return false;
        }
        
        // Check if product type is supported
        $supported_types = array('simple', 'variable', 'variation');
        if (!in_array($product->get_type(), $supported_types)) {
            return false;
        }
        
        // Check if product is visible
        if (!$product->is_visible()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Format product data for POS
     */
    private function format_product_for_pos($product) {
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();
        
        $formatted = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'type' => $product->get_type(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'price_html' => $product->get_price_html(),
            'image_url' => $image_url,
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'manage_stock' => $product->get_manage_stock(),
            'in_stock' => $product->is_in_stock(),
            'weight' => $product->get_weight(),
            'dimensions' => array(
                'length' => $product->get_length(),
                'width' => $product->get_width(),
                'height' => $product->get_height()
            ),
            'categories' => $this->get_product_categories($product),
            'attributes' => $this->get_product_attributes($product),
            'tax_status' => $product->get_tax_status(),
            'tax_class' => $product->get_tax_class()
        );
        
        // Handle variable products
        if ($product->is_type('variable')) {
            $formatted['variations'] = $this->get_product_variations($product);
            $formatted['variation_attributes'] = $product->get_variation_attributes();
        }
        
        return $formatted;
    }
    
    /**
     * Get product categories
     */
    private function get_product_categories($product) {
        $categories = array();
        $terms = wp_get_post_terms($product->get_id(), 'product_cat');
        
        foreach ($terms as $term) {
            $categories[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug
            );
        }
        
        return $categories;
    }
    
    /**
     * Get product attributes
     */
    private function get_product_attributes($product) {
        $attributes = array();
        $product_attributes = $product->get_attributes();
        
        foreach ($product_attributes as $attribute) {
            $attributes[] = array(
                'name' => $attribute->get_name(),
                'options' => $attribute->get_options(),
                'visible' => $attribute->get_visible(),
                'variation' => $attribute->get_variation()
            );
        }
        
        return $attributes;
    }
    
    /**
     * Get product variations
     */
    private function get_product_variations($product) {
        $variations = array();
        $variation_ids = $product->get_children();
        
        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            
            if ($variation && $variation->is_in_stock()) {
                $variations[] = array(
                    'id' => $variation->get_id(),
                    'sku' => $variation->get_sku(),
                    'price' => $variation->get_price(),
                    'regular_price' => $variation->get_regular_price(),
                    'sale_price' => $variation->get_sale_price(),
                    'stock_quantity' => $variation->get_stock_quantity(),
                    'attributes' => $variation->get_variation_attributes(),
                    'image_url' => wp_get_attachment_image_url($variation->get_image_id(), 'thumbnail')
                );
            }
        }
        
        return $variations;
    }
    
    /**
     * Update product stock
     */
    public function update_product_stock($product_id, $quantity, $operation = 'set') {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return false;
        }
        
        if (!$product->get_manage_stock()) {
            return true; // Stock not managed, no update needed
        }
        
        $current_stock = $product->get_stock_quantity();
        
        switch ($operation) {
            case 'reduce':
                $new_stock = $current_stock - $quantity;
                break;
            case 'increase':
                $new_stock = $current_stock + $quantity;
                break;
            case 'set':
            default:
                $new_stock = $quantity;
                break;
        }
        
        $product->set_stock_quantity($new_stock);
        $product->save();
        
        return true;
    }
}
