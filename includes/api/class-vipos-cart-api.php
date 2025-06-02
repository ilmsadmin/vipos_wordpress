<?php
/**
 * VIPOS Cart REST API
 *
 * @package VIPOS
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * VIPOS Cart API class
 */
class VIPOS_Cart_API extends VIPOS_REST_API {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->namespace = 'vipos/v1';
        $this->rest_base = 'cart';
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Get cart contents
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_cart'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));

        // Add item to cart
        register_rest_route($this->namespace, '/' . $this->rest_base . '/add', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'add_to_cart'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'product_id' => array(
                        'description' => __('Product ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                    'quantity' => array(
                        'description' => __('Quantity', 'vipos'),
                        'type'        => 'integer',
                        'default'     => 1,
                        'minimum'     => 1,
                    ),
                    'variation_id' => array(
                        'description' => __('Variation ID', 'vipos'),
                        'type'        => 'integer',
                    ),
                    'variation' => array(
                        'description' => __('Variation attributes', 'vipos'),
                        'type'        => 'object',
                    ),
                ),
            ),
        ));

        // Update cart item
        register_rest_route($this->namespace, '/' . $this->rest_base . '/update', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_cart_item'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'cart_item_key' => array(
                        'description' => __('Cart item key', 'vipos'),
                        'type'        => 'string',
                        'required'    => true,
                    ),
                    'quantity' => array(
                        'description' => __('New quantity', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                        'minimum'     => 0,
                    ),
                ),
            ),
        ));

        // Remove item from cart
        register_rest_route($this->namespace, '/' . $this->rest_base . '/remove', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'remove_from_cart'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'cart_item_key' => array(
                        'description' => __('Cart item key', 'vipos'),
                        'type'        => 'string',
                        'required'    => true,
                    ),
                ),
            ),
        ));

        // Clear cart
        register_rest_route($this->namespace, '/' . $this->rest_base . '/clear', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'clear_cart'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));

        // Apply discount
        register_rest_route($this->namespace, '/' . $this->rest_base . '/discount', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'apply_discount'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'type' => array(
                        'description' => __('Discount type', 'vipos'),
                        'type'        => 'string',
                        'required'    => true,
                        'enum'        => array('percentage', 'fixed', 'coupon'),
                    ),
                    'value' => array(
                        'description' => __('Discount value', 'vipos'),
                        'type'        => 'number',
                    ),
                    'coupon_code' => array(
                        'description' => __('Coupon code', 'vipos'),
                        'type'        => 'string',
                    ),
                ),
            ),
        ));

        // Remove discount
        register_rest_route($this->namespace, '/' . $this->rest_base . '/discount', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'remove_discount'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));

        // Set customer
        register_rest_route($this->namespace, '/' . $this->rest_base . '/customer', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'set_customer'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'customer_id' => array(
                        'description' => __('Customer ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            ),
        ));

        // Calculate totals
        register_rest_route($this->namespace, '/' . $this->rest_base . '/calculate', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'calculate_totals'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
        ));
    }

    /**
     * Get cart contents
     */
    public function get_cart($request) {
        try {
            $cart_manager = VIPOS_Cart_Manager::get_instance();
            $cart_data = $cart_manager->get_cart_contents();
            
            return rest_ensure_response(array(
                'success' => true,
                'data'    => $cart_data,
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_cart_error', 500);
        }
    }

    /**
     * Add item to cart
     */
    public function add_to_cart($request) {
        try {
            $product_id = intval($request['product_id']);
            $quantity = intval($request['quantity']);
            $variation_id = isset($request['variation_id']) ? intval($request['variation_id']) : 0;
            $variation = isset($request['variation']) ? $request['variation'] : array();
            
            $cart_manager = VIPOS_Cart_Manager::get_instance();
            $result = $cart_manager->add_to_cart($product_id, $quantity, $variation_id, $variation);
            
            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 'add_to_cart_failed', 400);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => array(
                    'cart_item_key' => $result,
                    'cart_contents' => $cart_manager->get_cart_contents(),
                ),
                'message' => __('Product added to cart', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'add_to_cart_error', 500);
        }
    }

    /**
     * Update cart item
     */
    public function update_cart_item($request) {
        try {
            $cart_item_key = sanitize_text_field($request['cart_item_key']);
            $quantity = intval($request['quantity']);
            
            $cart_manager = VIPOS_Cart_Manager::get_instance();
            $result = $cart_manager->update_cart_item($cart_item_key, $quantity);
            
            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 'update_cart_failed', 400);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $cart_manager->get_cart_contents(),
                'message' => __('Cart item updated', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'update_cart_error', 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function remove_from_cart($request) {
        try {
            $cart_item_key = sanitize_text_field($request['cart_item_key']);
            
            $cart_manager = VIPOS_Cart_Manager::get_instance();
            $result = $cart_manager->remove_cart_item($cart_item_key);
            
            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 'remove_cart_failed', 400);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $cart_manager->get_cart_contents(),
                'message' => __('Item removed from cart', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'remove_cart_error', 500);
        }
    }

    /**
     * Clear cart
     */
    public function clear_cart($request) {
        try {
            $cart_manager = VIPOS_Cart_Manager::get_instance();
            $cart_manager->clear_cart();

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $cart_manager->get_cart_contents(),
                'message' => __('Cart cleared', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'clear_cart_error', 500);
        }
    }

    /**
     * Apply discount
     */
    public function apply_discount($request) {
        try {
            $type = sanitize_text_field($request['type']);
            $value = isset($request['value']) ? floatval($request['value']) : 0;
            $coupon_code = isset($request['coupon_code']) ? sanitize_text_field($request['coupon_code']) : '';
            
            $cart_manager = VIPOS_Cart_Manager::get_instance();
            
            if ($type === 'coupon') {
                $result = $cart_manager->apply_coupon($coupon_code);
            } else {
                $result = $cart_manager->apply_discount($type, $value);
            }
            
            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 'apply_discount_failed', 400);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $cart_manager->get_cart_contents(),
                'message' => __('Discount applied', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'apply_discount_error', 500);
        }
    }

    /**
     * Remove discount
     */
    public function remove_discount($request) {
        try {
            $cart_manager = VIPOS_Cart_Manager::get_instance();
            $cart_manager->remove_discount();

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $cart_manager->get_cart_contents(),
                'message' => __('Discount removed', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'remove_discount_error', 500);
        }
    }

    /**
     * Set customer
     */
    public function set_customer($request) {
        try {
            $customer_id = intval($request['customer_id']);
            
            $cart_manager = VIPOS_Cart_Manager::get_instance();
            $result = $cart_manager->set_customer($customer_id);
            
            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 'set_customer_failed', 400);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $cart_manager->get_cart_contents(),
                'message' => __('Customer set', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'set_customer_error', 500);
        }
    }

    /**
     * Calculate totals
     */
    public function calculate_totals($request) {
        try {
            $cart_manager = VIPOS_Cart_Manager::get_instance();
            $cart_manager->calculate_totals();

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $cart_manager->get_cart_contents(),
                'message' => __('Totals calculated', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'calculate_totals_error', 500);
        }
    }
}
