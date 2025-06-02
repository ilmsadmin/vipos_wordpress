<?php
/**
 * VIPOS Orders REST API
 *
 * @package VIPOS
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * VIPOS Orders API class
 */
class VIPOS_Orders_API extends VIPOS_REST_API {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->namespace = 'vipos/v1';
        $this->rest_base = 'orders';
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Get orders
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_orders'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => $this->get_collection_params(),
            ),
        ));

        // Get single order
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_order'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Order ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            ),
        ));

        // Create order (checkout)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/checkout', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'create_order'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'payment_method' => array(
                        'description' => __('Payment method', 'vipos'),
                        'type'        => 'string',
                        'required'    => true,
                    ),
                    'customer_id' => array(
                        'description' => __('Customer ID', 'vipos'),
                        'type'        => 'integer',
                    ),
                    'billing_address' => array(
                        'description' => __('Billing address', 'vipos'),
                        'type'        => 'object',
                    ),
                    'shipping_address' => array(
                        'description' => __('Shipping address', 'vipos'),
                        'type'        => 'object',
                    ),
                    'order_notes' => array(
                        'description' => __('Order notes', 'vipos'),
                        'type'        => 'string',
                    ),
                ),
            ),
        ));

        // Update order status
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/status', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_order_status'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Order ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                    'status' => array(
                        'description' => __('Order status', 'vipos'),
                        'type'        => 'string',
                        'required'    => true,
                        'enum'        => array('pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'),
                    ),
                ),
            ),
        ));

        // Refund order
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/refund', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'refund_order'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Order ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                    'amount' => array(
                        'description' => __('Refund amount', 'vipos'),
                        'type'        => 'number',
                    ),
                    'reason' => array(
                        'description' => __('Refund reason', 'vipos'),
                        'type'        => 'string',
                    ),
                    'items' => array(
                        'description' => __('Items to refund', 'vipos'),
                        'type'        => 'array',
                    ),
                ),
            ),
        ));

        // Print receipt
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/receipt', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_receipt'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Order ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            ),
        ));

        // Search orders
        register_rest_route($this->namespace, '/' . $this->rest_base . '/search', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'search_orders'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'term' => array(
                        'description' => __('Search term', 'vipos'),
                        'type'        => 'string',
                        'required'    => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'limit' => array(
                        'description' => __('Number of orders to return', 'vipos'),
                        'type'        => 'integer',
                        'default'     => 20,
                    ),
                ),
            ),
        ));
    }

    /**
     * Get orders
     */
    public function get_orders($request) {
        try {
            $params = $request->get_params();
            $order_manager = VIPOS_Order_Manager::get_instance();
            
            $args = array(
                'limit'    => isset($params['per_page']) ? intval($params['per_page']) : 20,
                'page'     => isset($params['page']) ? intval($params['page']) : 1,
                'status'   => isset($params['status']) ? sanitize_text_field($params['status']) : 'any',
                'customer' => isset($params['customer']) ? intval($params['customer']) : 0,
                'date_created' => isset($params['date_created']) ? sanitize_text_field($params['date_created']) : '',
            );

            $orders = $order_manager->get_orders($args);
            
            if (is_wp_error($orders)) {
                return $this->error_response($orders->get_error_message(), 'get_orders_failed', 400);
            }

            $formatted_orders = array();
            foreach ($orders['orders'] as $order) {
                $formatted_orders[] = $this->format_order_data($order);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => array(
                    'orders'      => $formatted_orders,
                    'total'       => $orders['total'],
                    'total_pages' => $orders['total_pages'],
                    'current_page' => $args['page'],
                ),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_orders_error', 500);
        }
    }

    /**
     * Get single order
     */
    public function get_order($request) {
        try {
            $order_id = intval($request['id']);
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return $this->error_response(__('Order not found', 'vipos'), 'order_not_found', 404);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $this->format_order_data($order),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_order_error', 500);
        }
    }

    /**
     * Create order (checkout)
     */
    public function create_order($request) {
        try {
            $payment_method = sanitize_text_field($request['payment_method']);
            $customer_id = isset($request['customer_id']) ? intval($request['customer_id']) : 0;
            $billing_address = isset($request['billing_address']) ? $request['billing_address'] : array();
            $shipping_address = isset($request['shipping_address']) ? $request['shipping_address'] : array();
            $order_notes = isset($request['order_notes']) ? sanitize_textarea_field($request['order_notes']) : '';
            
            $order_manager = VIPOS_Order_Manager::get_instance();
            
            $order_data = array(
                'payment_method'   => $payment_method,
                'customer_id'      => $customer_id,
                'billing_address'  => $billing_address,
                'shipping_address' => $shipping_address,
                'order_notes'      => $order_notes,
            );
            
            $order = $order_manager->create_order($order_data);
            
            if (is_wp_error($order)) {
                return $this->error_response($order->get_error_message(), 'create_order_failed', 400);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $this->format_order_data($order),
                'message' => __('Order created successfully', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'create_order_error', 500);
        }
    }

    /**
     * Update order status
     */
    public function update_order_status($request) {
        try {
            $order_id = intval($request['id']);
            $status = sanitize_text_field($request['status']);
            
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return $this->error_response(__('Order not found', 'vipos'), 'order_not_found', 404);
            }

            $order->update_status($status);

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $this->format_order_data($order),
                'message' => __('Order status updated', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'update_order_status_error', 500);
        }
    }

    /**
     * Refund order
     */
    public function refund_order($request) {
        try {
            $order_id = intval($request['id']);
            $amount = isset($request['amount']) ? floatval($request['amount']) : null;
            $reason = isset($request['reason']) ? sanitize_text_field($request['reason']) : '';
            $items = isset($request['items']) ? $request['items'] : array();
            
            $order_manager = VIPOS_Order_Manager::get_instance();
            
            $refund_data = array(
                'amount' => $amount,
                'reason' => $reason,
                'items'  => $items,
            );
            
            $refund = $order_manager->refund_order($order_id, $refund_data);
            
            if (is_wp_error($refund)) {
                return $this->error_response($refund->get_error_message(), 'refund_order_failed', 400);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => array(
                    'refund_id' => $refund->get_id(),
                    'amount'    => $refund->get_amount(),
                    'reason'    => $refund->get_reason(),
                ),
                'message' => __('Order refunded successfully', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'refund_order_error', 500);
        }
    }

    /**
     * Get receipt
     */
    public function get_receipt($request) {
        try {
            $order_id = intval($request['id']);
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return $this->error_response(__('Order not found', 'vipos'), 'order_not_found', 404);
            }

            $order_manager = VIPOS_Order_Manager::get_instance();
            $receipt_html = $order_manager->generate_receipt($order);

            return rest_ensure_response(array(
                'success' => true,
                'data'    => array(
                    'order_id'     => $order_id,
                    'receipt_html' => $receipt_html,
                ),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_receipt_error', 500);
        }
    }

    /**
     * Search orders
     */
    public function search_orders($request) {
        try {
            $term = sanitize_text_field($request['term']);
            $limit = intval($request['limit']);
            
            $order_manager = VIPOS_Order_Manager::get_instance();
            $orders = $order_manager->search_orders($term, $limit);
            
            if (is_wp_error($orders)) {
                return $this->error_response($orders->get_error_message(), 'search_failed', 400);
            }

            $formatted_orders = array();
            foreach ($orders as $order) {
                $formatted_orders[] = $this->format_order_data($order);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $formatted_orders,
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'search_error', 500);
        }
    }

    /**
     * Format order data for API response
     */
    private function format_order_data($order) {
        if (!$order instanceof WC_Order) {
            return null;
        }

        $data = array(
            'id'               => $order->get_id(),
            'order_number'     => $order->get_order_number(),
            'status'           => $order->get_status(),
            'currency'         => $order->get_currency(),
            'date_created'     => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '',
            'date_modified'    => $order->get_date_modified() ? $order->get_date_modified()->format('Y-m-d H:i:s') : '',
            'total'            => $order->get_total(),
            'subtotal'         => $order->get_subtotal(),
            'tax_total'        => $order->get_total_tax(),
            'shipping_total'   => $order->get_shipping_total(),
            'discount_total'   => $order->get_discount_total(),
            'customer_id'      => $order->get_customer_id(),
            'customer_note'    => $order->get_customer_note(),
            'payment_method'   => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'transaction_id'   => $order->get_transaction_id(),
            'billing_address'  => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'company'    => $order->get_billing_company(),
                'address_1'  => $order->get_billing_address_1(),
                'address_2'  => $order->get_billing_address_2(),
                'city'       => $order->get_billing_city(),
                'state'      => $order->get_billing_state(),
                'postcode'   => $order->get_billing_postcode(),
                'country'    => $order->get_billing_country(),
                'email'      => $order->get_billing_email(),
                'phone'      => $order->get_billing_phone(),
            ),
            'shipping_address' => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name'  => $order->get_shipping_last_name(),
                'company'    => $order->get_shipping_company(),
                'address_1'  => $order->get_shipping_address_1(),
                'address_2'  => $order->get_shipping_address_2(),
                'city'       => $order->get_shipping_city(),
                'state'      => $order->get_shipping_state(),
                'postcode'   => $order->get_shipping_postcode(),
                'country'    => $order->get_shipping_country(),
            ),
            'line_items'       => $this->get_order_items($order),
            'tax_lines'        => $this->get_order_taxes($order),
            'shipping_lines'   => $this->get_order_shipping($order),
            'fee_lines'        => $this->get_order_fees($order),
            'coupon_lines'     => $this->get_order_coupons($order),
            'refunds'          => $this->get_order_refunds($order),
            'meta_data'        => $this->get_order_meta_data($order),
        );

        return $data;
    }

    /**
     * Get order items
     */
    private function get_order_items($order) {
        $items = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            
            $items[] = array(
                'id'           => $item_id,
                'name'         => $item->get_name(),
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity'     => $item->get_quantity(),
                'tax_class'    => $item->get_tax_class(),
                'subtotal'     => $item->get_subtotal(),
                'subtotal_tax' => $item->get_subtotal_tax(),
                'total'        => $item->get_total(),
                'total_tax'    => $item->get_total_tax(),
                'sku'          => $product ? $product->get_sku() : '',
                'price'        => $product ? $product->get_price() : '',
                'meta_data'    => $this->get_item_meta_data($item),
            );
        }
        
        return $items;
    }

    /**
     * Get order taxes
     */
    private function get_order_taxes($order) {
        $taxes = array();
        
        foreach ($order->get_tax_totals() as $tax) {
            $taxes[] = array(
                'label'  => $tax->label,
                'amount' => $tax->amount,
            );
        }
        
        return $taxes;
    }

    /**
     * Get order shipping
     */
    private function get_order_shipping($order) {
        $shipping = array();
        
        foreach ($order->get_shipping_methods() as $shipping_item_id => $shipping_item) {
            $shipping[] = array(
                'id'           => $shipping_item_id,
                'method_title' => $shipping_item->get_method_title(),
                'method_id'    => $shipping_item->get_method_id(),
                'total'        => $shipping_item->get_total(),
                'total_tax'    => $shipping_item->get_total_tax(),
                'meta_data'    => $this->get_item_meta_data($shipping_item),
            );
        }
        
        return $shipping;
    }

    /**
     * Get order fees
     */
    private function get_order_fees($order) {
        $fees = array();
        
        foreach ($order->get_fees() as $fee_item_id => $fee_item) {
            $fees[] = array(
                'id'        => $fee_item_id,
                'name'      => $fee_item->get_name(),
                'tax_class' => $fee_item->get_tax_class(),
                'total'     => $fee_item->get_total(),
                'total_tax' => $fee_item->get_total_tax(),
                'meta_data' => $this->get_item_meta_data($fee_item),
            );
        }
        
        return $fees;
    }

    /**
     * Get order coupons
     */
    private function get_order_coupons($order) {
        $coupons = array();
        
        foreach ($order->get_coupon_codes() as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            $coupons[] = array(
                'code'        => $coupon_code,
                'discount'    => $order->get_discount_to_display_in_cents(),
                'description' => $coupon->get_description(),
            );
        }
        
        return $coupons;
    }

    /**
     * Get order refunds
     */
    private function get_order_refunds($order) {
        $refunds = array();
        
        foreach ($order->get_refunds() as $refund) {
            $refunds[] = array(
                'id'     => $refund->get_id(),
                'reason' => $refund->get_reason(),
                'amount' => $refund->get_amount(),
                'date'   => $refund->get_date_created() ? $refund->get_date_created()->format('Y-m-d H:i:s') : '',
            );
        }
        
        return $refunds;
    }

    /**
     * Get order meta data
     */
    private function get_order_meta_data($order) {
        $meta_data = array();
        
        // Add POS specific meta
        $meta_data['pos_order'] = get_post_meta($order->get_id(), '_pos_order', true);
        $meta_data['pos_cashier'] = get_post_meta($order->get_id(), '_pos_cashier', true);
        $meta_data['pos_register'] = get_post_meta($order->get_id(), '_pos_register', true);
        
        return $meta_data;
    }

    /**
     * Get item meta data
     */
    private function get_item_meta_data($item) {
        $meta_data = array();
        
        foreach ($item->get_meta_data() as $meta) {
            $meta_data[$meta->key] = $meta->value;
        }
        
        return $meta_data;
    }

    /**
     * Get collection parameters
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();
        
        $params['status'] = array(
            'description' => __('Order status', 'vipos'),
            'type'        => 'string',
            'enum'        => array('any', 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'),
            'default'     => 'any',
        );
        
        $params['customer'] = array(
            'description' => __('Customer ID', 'vipos'),
            'type'        => 'integer',
        );
        
        $params['date_created'] = array(
            'description' => __('Date created', 'vipos'),
            'type'        => 'string',
        );
        
        return $params;
    }
}
