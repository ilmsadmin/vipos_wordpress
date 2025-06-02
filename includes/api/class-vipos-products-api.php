<?php
/**
 * VIPOS Products REST API
 *
 * @package VIPOS
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * VIPOS Products API class
 */
class VIPOS_Products_API extends VIPOS_REST_API {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->namespace = 'vipos/v1';
        $this->rest_base = 'products';
    }

    /**
     * Register routes
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_products'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => $this->get_collection_params(),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_product'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Product ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/search', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'search_products'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'term' => array(
                        'description' => __('Search term', 'vipos'),
                        'type'        => 'string',
                        'required'    => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'limit' => array(
                        'description' => __('Number of products to return', 'vipos'),
                        'type'        => 'integer',
                        'default'     => 20,
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/categories', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_categories'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/variations/(?P<product_id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_product_variations'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'product_id' => array(
                        'description' => __('Product ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            ),
        ));
    }

    /**
     * Get products
     */
    public function get_products($request) {
        try {
            $params = $request->get_params();
            $product_manager = VIPOS_Product_Manager::get_instance();
            
            $args = array(
                'limit'    => isset($params['per_page']) ? intval($params['per_page']) : 20,
                'page'     => isset($params['page']) ? intval($params['page']) : 1,
                'category' => isset($params['category']) ? sanitize_text_field($params['category']) : '',
                'search'   => isset($params['search']) ? sanitize_text_field($params['search']) : '',
                'status'   => 'publish',
            );

            $products = $product_manager->get_products($args);
            
            if (is_wp_error($products)) {
                return $this->error_response($products->get_error_message(), 'get_products_failed', 400);
            }

            $formatted_products = array();
            foreach ($products['products'] as $product) {
                $formatted_products[] = $this->format_product_data($product);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => array(
                    'products'    => $formatted_products,
                    'total'       => $products['total'],
                    'total_pages' => $products['total_pages'],
                    'current_page' => $args['page'],
                ),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_products_error', 500);
        }
    }

    /**
     * Get single product
     */
    public function get_product($request) {
        try {
            $product_id = intval($request['id']);
            $product_manager = VIPOS_Product_Manager::get_instance();
            
            $product = $product_manager->get_product($product_id);
            
            if (!$product) {
                return $this->error_response(__('Product not found', 'vipos'), 'product_not_found', 404);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $this->format_product_data($product),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_product_error', 500);
        }
    }

    /**
     * Search products
     */
    public function search_products($request) {
        try {
            $term = sanitize_text_field($request['term']);
            $limit = intval($request['limit']);
            
            $product_manager = VIPOS_Product_Manager::get_instance();
            $products = $product_manager->search_products($term, $limit);
            
            if (is_wp_error($products)) {
                return $this->error_response($products->get_error_message(), 'search_failed', 400);
            }

            $formatted_products = array();
            foreach ($products as $product) {
                $formatted_products[] = $this->format_product_data($product);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $formatted_products,
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'search_error', 500);
        }
    }

    /**
     * Get product categories
     */
    public function get_categories($request) {
        try {
            $product_manager = VIPOS_Product_Manager::get_instance();
            $categories = $product_manager->get_categories();
            
            return rest_ensure_response(array(
                'success' => true,
                'data'    => $categories,
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_categories_error', 500);
        }
    }

    /**
     * Get product variations
     */
    public function get_product_variations($request) {
        try {
            $product_id = intval($request['product_id']);
            $product_manager = VIPOS_Product_Manager::get_instance();
            
            $variations = $product_manager->get_product_variations($product_id);
            
            if (is_wp_error($variations)) {
                return $this->error_response($variations->get_error_message(), 'get_variations_failed', 400);
            }

            $formatted_variations = array();
            foreach ($variations as $variation) {
                $formatted_variations[] = $this->format_product_data($variation);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $formatted_variations,
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_variations_error', 500);
        }
    }

    /**
     * Format product data for API response
     */
    private function format_product_data($product) {
        if (!$product instanceof WC_Product) {
            return null;
        }

        $image_id = $product->get_image_id();
        $image_url = '';
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');
        }

        $data = array(
            'id'            => $product->get_id(),
            'name'          => $product->get_name(),
            'slug'          => $product->get_slug(),
            'type'          => $product->get_type(),
            'status'        => $product->get_status(),
            'featured'      => $product->is_featured(),
            'description'   => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'sku'           => $product->get_sku(),
            'price'         => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price'    => $product->get_sale_price(),
            'price_html'    => $product->get_price_html(),
            'on_sale'       => $product->is_on_sale(),
            'stock_status'  => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'manage_stock'  => $product->get_manage_stock(),
            'in_stock'      => $product->is_in_stock(),
            'weight'        => $product->get_weight(),
            'dimensions'    => array(
                'length' => $product->get_length(),
                'width'  => $product->get_width(),
                'height' => $product->get_height(),
            ),
            'categories'    => $this->get_product_categories($product),
            'tags'          => $this->get_product_tags($product),
            'images'        => array(
                'main' => $image_url,
                'gallery' => $this->get_product_gallery_images($product),
            ),
            'attributes'    => $this->get_product_attributes($product),
            'variations'    => $product->is_type('variable') ? $product->get_children() : array(),
            'meta_data'     => $this->get_product_meta_data($product),
        );

        // Add variation specific data
        if ($product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            $data['parent_id'] = $product->get_parent_id();
            $data['parent_name'] = $parent ? $parent->get_name() : '';
            $data['variation_attributes'] = $product->get_variation_attributes();
        }

        return $data;
    }

    /**
     * Get product categories
     */
    private function get_product_categories($product) {
        $categories = array();
        $terms = get_the_terms($product->get_id(), 'product_cat');
        
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = array(
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }
        
        return $categories;
    }

    /**
     * Get product tags
     */
    private function get_product_tags($product) {
        $tags = array();
        $terms = get_the_terms($product->get_id(), 'product_tag');
        
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $tags[] = array(
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }
        
        return $tags;
    }

    /**
     * Get product gallery images
     */
    private function get_product_gallery_images($product) {
        $gallery_images = array();
        $attachment_ids = $product->get_gallery_image_ids();
        
        foreach ($attachment_ids as $attachment_id) {
            $image_url = wp_get_attachment_image_url($attachment_id, 'woocommerce_thumbnail');
            if ($image_url) {
                $gallery_images[] = $image_url;
            }
        }
        
        return $gallery_images;
    }

    /**
     * Get product attributes
     */
    private function get_product_attributes($product) {
        $attributes = array();
        $product_attributes = $product->get_attributes();
        
        foreach ($product_attributes as $attribute_name => $attribute) {
            $attributes[$attribute_name] = array(
                'name'      => wc_attribute_label($attribute_name),
                'options'   => $attribute->get_options(),
                'visible'   => $attribute->get_visible(),
                'variation' => $attribute->get_variation(),
            );
        }
        
        return $attributes;
    }

    /**
     * Get product meta data
     */
    private function get_product_meta_data($product) {
        $meta_data = array();
        
        // Add custom meta fields that might be useful for POS
        $meta_data['barcode'] = get_post_meta($product->get_id(), '_barcode', true);
        $meta_data['pos_visibility'] = get_post_meta($product->get_id(), '_pos_visibility', true);
        
        return $meta_data;
    }

    /**
     * Get collection parameters
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();
        
        $params['category'] = array(
            'description' => __('Filter by category slug', 'vipos'),
            'type'        => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        );
        
        $params['search'] = array(
            'description' => __('Search products', 'vipos'),
            'type'        => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        );
        
        $params['status'] = array(
            'description' => __('Product status', 'vipos'),
            'type'        => 'string',
            'default'     => 'publish',
            'enum'        => array('publish', 'draft', 'private'),
        );
        
        return $params;
    }
}
