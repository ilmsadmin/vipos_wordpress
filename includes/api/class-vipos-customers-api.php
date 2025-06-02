<?php
/**
 * VIPOS Customers REST API
 *
 * @package VIPOS
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * VIPOS Customers API class
 */
class VIPOS_Customers_API extends VIPOS_REST_API {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->namespace = 'vipos/v1';
        $this->rest_base = 'customers';
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Get customers
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_customers'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => $this->get_collection_params(),
            ),
        ));

        // Create customer
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'create_customer'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'first_name' => array(
                        'description' => __('First name', 'vipos'),
                        'type'        => 'string',
                        'required'    => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'last_name' => array(
                        'description' => __('Last name', 'vipos'),
                        'type'        => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'email' => array(
                        'description' => __('Email address', 'vipos'),
                        'type'        => 'string',
                        'format'      => 'email',
                        'sanitize_callback' => 'sanitize_email',
                    ),
                    'phone' => array(
                        'description' => __('Phone number', 'vipos'),
                        'type'        => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'company' => array(
                        'description' => __('Company', 'vipos'),
                        'type'        => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'billing_address' => array(
                        'description' => __('Billing address', 'vipos'),
                        'type'        => 'object',
                    ),
                    'shipping_address' => array(
                        'description' => __('Shipping address', 'vipos'),
                        'type'        => 'object',
                    ),
                ),
            ),
        ));

        // Get single customer
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_customer'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Customer ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            ),
        ));

        // Update customer
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_customer'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Customer ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                    'first_name' => array(
                        'description' => __('First name', 'vipos'),
                        'type'        => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'last_name' => array(
                        'description' => __('Last name', 'vipos'),
                        'type'        => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'email' => array(
                        'description' => __('Email address', 'vipos'),
                        'type'        => 'string',
                        'format'      => 'email',
                        'sanitize_callback' => 'sanitize_email',
                    ),
                    'phone' => array(
                        'description' => __('Phone number', 'vipos'),
                        'type'        => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'company' => array(
                        'description' => __('Company', 'vipos'),
                        'type'        => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'billing_address' => array(
                        'description' => __('Billing address', 'vipos'),
                        'type'        => 'object',
                    ),
                    'shipping_address' => array(
                        'description' => __('Shipping address', 'vipos'),
                        'type'        => 'object',
                    ),
                ),
            ),
        ));

        // Search customers
        register_rest_route($this->namespace, '/' . $this->rest_base . '/search', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'search_customers'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'term' => array(
                        'description' => __('Search term', 'vipos'),
                        'type'        => 'string',
                        'required'    => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'limit' => array(
                        'description' => __('Number of customers to return', 'vipos'),
                        'type'        => 'integer',
                        'default'     => 20,
                    ),
                ),
            ),
        ));

        // Get customer orders
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/orders', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_customer_orders'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Customer ID', 'vipos'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                    'limit' => array(
                        'description' => __('Number of orders to return', 'vipos'),
                        'type'        => 'integer',
                        'default'     => 10,
                    ),
                ),
            ),
        ));
    }

    /**
     * Get customers
     */
    public function get_customers($request) {
        try {
            $params = $request->get_params();
            $customer_manager = VIPOS_Customer_Manager::get_instance();
            
            $args = array(
                'limit'  => isset($params['per_page']) ? intval($params['per_page']) : 20,
                'page'   => isset($params['page']) ? intval($params['page']) : 1,
                'search' => isset($params['search']) ? sanitize_text_field($params['search']) : '',
                'role'   => 'customer',
            );

            $customers = $customer_manager->get_customers($args);
            
            if (is_wp_error($customers)) {
                return $this->error_response($customers->get_error_message(), 'get_customers_failed', 400);
            }

            $formatted_customers = array();
            foreach ($customers['customers'] as $customer) {
                $formatted_customers[] = $this->format_customer_data($customer);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => array(
                    'customers'    => $formatted_customers,
                    'total'        => $customers['total'],
                    'total_pages'  => $customers['total_pages'],
                    'current_page' => $args['page'],
                ),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_customers_error', 500);
        }
    }

    /**
     * Create customer
     */
    public function create_customer($request) {
        try {
            $customer_data = array(
                'first_name'       => sanitize_text_field($request['first_name']),
                'last_name'        => isset($request['last_name']) ? sanitize_text_field($request['last_name']) : '',
                'email'            => isset($request['email']) ? sanitize_email($request['email']) : '',
                'phone'            => isset($request['phone']) ? sanitize_text_field($request['phone']) : '',
                'company'          => isset($request['company']) ? sanitize_text_field($request['company']) : '',
                'billing_address'  => isset($request['billing_address']) ? $request['billing_address'] : array(),
                'shipping_address' => isset($request['shipping_address']) ? $request['shipping_address'] : array(),
            );
            
            $customer_manager = VIPOS_Customer_Manager::get_instance();
            $customer = $customer_manager->create_customer($customer_data);
            
            if (is_wp_error($customer)) {
                return $this->error_response($customer->get_error_message(), 'create_customer_failed', 400);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $this->format_customer_data($customer),
                'message' => __('Customer created successfully', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'create_customer_error', 500);
        }
    }

    /**
     * Get single customer
     */
    public function get_customer($request) {
        try {
            $customer_id = intval($request['id']);
            $customer = new WC_Customer($customer_id);
            
            if (!$customer->get_id()) {
                return $this->error_response(__('Customer not found', 'vipos'), 'customer_not_found', 404);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $this->format_customer_data($customer),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_customer_error', 500);
        }
    }

    /**
     * Update customer
     */
    public function update_customer($request) {
        try {
            $customer_id = intval($request['id']);
            $customer = new WC_Customer($customer_id);
            
            if (!$customer->get_id()) {
                return $this->error_response(__('Customer not found', 'vipos'), 'customer_not_found', 404);
            }

            $customer_data = array();
            
            if (isset($request['first_name'])) {
                $customer_data['first_name'] = sanitize_text_field($request['first_name']);
            }
            
            if (isset($request['last_name'])) {
                $customer_data['last_name'] = sanitize_text_field($request['last_name']);
            }
            
            if (isset($request['email'])) {
                $customer_data['email'] = sanitize_email($request['email']);
            }
            
            if (isset($request['phone'])) {
                $customer_data['phone'] = sanitize_text_field($request['phone']);
            }
            
            if (isset($request['company'])) {
                $customer_data['company'] = sanitize_text_field($request['company']);
            }
            
            if (isset($request['billing_address'])) {
                $customer_data['billing_address'] = $request['billing_address'];
            }
            
            if (isset($request['shipping_address'])) {
                $customer_data['shipping_address'] = $request['shipping_address'];
            }
            
            $customer_manager = VIPOS_Customer_Manager::get_instance();
            $updated_customer = $customer_manager->update_customer($customer_id, $customer_data);
            
            if (is_wp_error($updated_customer)) {
                return $this->error_response($updated_customer->get_error_message(), 'update_customer_failed', 400);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $this->format_customer_data($updated_customer),
                'message' => __('Customer updated successfully', 'vipos'),
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'update_customer_error', 500);
        }
    }

    /**
     * Search customers
     */
    public function search_customers($request) {
        try {
            $term = sanitize_text_field($request['term']);
            $limit = intval($request['limit']);
            
            $customer_manager = VIPOS_Customer_Manager::get_instance();
            $customers = $customer_manager->search_customers($term, $limit);
            
            if (is_wp_error($customers)) {
                return $this->error_response($customers->get_error_message(), 'search_failed', 400);
            }

            $formatted_customers = array();
            foreach ($customers as $customer) {
                $formatted_customers[] = $this->format_customer_data($customer);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $formatted_customers,
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'search_error', 500);
        }
    }

    /**
     * Get customer orders
     */
    public function get_customer_orders($request) {
        try {
            $customer_id = intval($request['id']);
            $limit = intval($request['limit']);
            
            $customer_manager = VIPOS_Customer_Manager::get_instance();
            $orders = $customer_manager->get_customer_orders($customer_id, $limit);
            
            if (is_wp_error($orders)) {
                return $this->error_response($orders->get_error_message(), 'get_customer_orders_failed', 400);
            }

            $formatted_orders = array();
            foreach ($orders as $order) {
                $formatted_orders[] = $this->format_order_summary($order);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $formatted_orders,
            ));

        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 'get_customer_orders_error', 500);
        }
    }

    /**
     * Format customer data for API response
     */
    private function format_customer_data($customer) {
        if (!$customer instanceof WC_Customer) {
            return null;
        }

        $data = array(
            'id'               => $customer->get_id(),
            'first_name'       => $customer->get_first_name(),
            'last_name'        => $customer->get_last_name(),
            'display_name'     => $customer->get_display_name(),
            'email'            => $customer->get_email(),
            'username'         => $customer->get_username(),
            'role'             => $customer->get_role(),
            'date_created'     => $customer->get_date_created() ? $customer->get_date_created()->format('Y-m-d H:i:s') : '',
            'date_modified'    => $customer->get_date_modified() ? $customer->get_date_modified()->format('Y-m-d H:i:s') : '',
            'orders_count'     => $customer->get_order_count(),
            'total_spent'      => $customer->get_total_spent(),
            'avatar_url'       => $customer->get_avatar_url(),
            'billing_address'  => array(
                'first_name' => $customer->get_billing_first_name(),
                'last_name'  => $customer->get_billing_last_name(),
                'company'    => $customer->get_billing_company(),
                'address_1'  => $customer->get_billing_address_1(),
                'address_2'  => $customer->get_billing_address_2(),
                'city'       => $customer->get_billing_city(),
                'state'      => $customer->get_billing_state(),
                'postcode'   => $customer->get_billing_postcode(),
                'country'    => $customer->get_billing_country(),
                'email'      => $customer->get_billing_email(),
                'phone'      => $customer->get_billing_phone(),
            ),
            'shipping_address' => array(
                'first_name' => $customer->get_shipping_first_name(),
                'last_name'  => $customer->get_shipping_last_name(),
                'company'    => $customer->get_shipping_company(),
                'address_1'  => $customer->get_shipping_address_1(),
                'address_2'  => $customer->get_shipping_address_2(),
                'city'       => $customer->get_shipping_city(),
                'state'      => $customer->get_shipping_state(),
                'postcode'   => $customer->get_shipping_postcode(),
                'country'    => $customer->get_shipping_country(),
            ),
            'meta_data'        => $this->get_customer_meta_data($customer),
        );

        return $data;
    }

    /**
     * Format order summary for customer orders
     */
    private function format_order_summary($order) {
        if (!$order instanceof WC_Order) {
            return null;
        }

        return array(
            'id'           => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status'       => $order->get_status(),
            'date_created' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '',
            'total'        => $order->get_total(),
            'currency'     => $order->get_currency(),
            'item_count'   => $order->get_item_count(),
        );
    }

    /**
     * Get customer meta data
     */
    private function get_customer_meta_data($customer) {
        $meta_data = array();
        
        // Add customer specific meta
        $meta_data['customer_notes'] = get_user_meta($customer->get_id(), '_customer_notes', true);
        $meta_data['loyalty_points'] = get_user_meta($customer->get_id(), '_loyalty_points', true);
        $meta_data['customer_group'] = get_user_meta($customer->get_id(), '_customer_group', true);
        
        return $meta_data;
    }

    /**
     * Get collection parameters
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();
        
        $params['search'] = array(
            'description' => __('Search customers', 'vipos'),
            'type'        => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        );
        
        $params['role'] = array(
            'description' => __('Customer role', 'vipos'),
            'type'        => 'string',
            'default'     => 'customer',
        );
        
        $params['orderby'] = array(
            'description' => __('Sort collection by object attribute', 'vipos'),
            'type'        => 'string',
            'default'     => 'registered_date',
            'enum'        => array(
                'id',
                'include',
                'name',
                'registered_date',
            ),
        );
        
        return $params;
    }
}
