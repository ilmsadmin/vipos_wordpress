<?php
/**
 * VIPOS Customer Manager Class
 * 
 * Manages customers for POS operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIPOS_Customer_Manager {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_vipos_create_customer', array($this, 'handle_create_customer'));
        add_action('wp_ajax_vipos_update_customer', array($this, 'handle_update_customer'));
        add_action('wp_ajax_vipos_get_customer_details', array($this, 'handle_get_customer_details'));
        add_action('wp_ajax_vipos_get_customer_orders', array($this, 'handle_get_customer_orders'));
        add_action('wp_ajax_vipos_search_customers', array($this, 'handle_search_customers'));
        
        // Add debugging hook to test if hooks are registered
        add_action('admin_init', function() {
            error_log('VIPOS Customer Manager: Hooks initialized');
        });
    }
    
    /**
     * Get customers for POS interface
     */
    public function get_customers_for_pos($limit = 50) {
        $users = get_users(array(
            'role__in' => array('customer', 'subscriber'),
            'number' => $limit,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'user_email', 'user_registered')
        ));
        
        $customers = array();
        
        foreach ($users as $user) {
            $customers[] = $this->format_customer_for_pos($user);
        }
        
        return $customers;
    }    /**
     * Search customers - Simplified approach similar to WooCommerce
     *
     * @param string $term Search term
     * @param int $limit Number of results to return
     * @return array
     */
    public function search_customers($term, $limit = 20) {
        try {
            error_log('VIPOS Customer Search: Starting search for term: "' . $term . '" with limit: ' . $limit);
            
            if (empty($term)) {
                error_log('VIPOS Customer Search: Empty search term, returning empty array');
                return array();
            }

            $customers = array();
            
            // Method 1: Direct search by ID if term is numeric
            if (is_numeric($term)) {
                error_log('VIPOS Customer Search: Searching by numeric ID: ' . $term);
                $user = get_userdata(intval($term));
                if ($user && in_array('customer', $user->roles)) {
                    $formatted_customer = $this->format_customer_for_pos($user);
                    if ($formatted_customer) {
                        $customers[] = $formatted_customer;
                        error_log('VIPOS Customer Search: Found customer by ID');
                    }
                }
            }
              // Method 2: Search users by basic fields (similar to WooCommerce customers search)
            if (empty($customers)) {
                error_log('VIPOS Customer Search: Searching users with get_users');
                
                // Try broader role search first
                $users = get_users(array(
                    'role__in' => array('customer', 'subscriber', 'administrator', 'shop_manager'),
                    'search' => '*' . esc_attr($term) . '*',
                    'search_columns' => array(
                        'user_login',
                        'user_nicename',
                        'user_email',
                        'display_name'
                    ),
                    'number' => $limit,
                    'orderby' => 'display_name',
                    'order' => 'ASC'
                ));
                
                error_log('VIPOS Customer Search: Found ' . count($users) . ' users with basic search');
                
                foreach ($users as $user) {
                    error_log('VIPOS Customer Search: Processing user from basic search - ID=' . $user->ID . ', roles=' . implode(',', $user->roles));
                    $formatted_customer = $this->format_customer_for_pos($user);
                    if ($formatted_customer) {
                        $customers[] = $formatted_customer;
                        error_log('VIPOS Customer Search: Added customer from basic search - ID=' . $user->ID);
                    }
                }
            }
              // Method 3: Search by meta fields if still no results
            if (empty($customers)) {
                error_log('VIPOS Customer Search: Searching by meta fields');
                
                global $wpdb;
                
                // Search in user meta for first_name, last_name, billing fields
                $meta_keys = array(
                    'first_name',
                    'last_name', 
                    'billing_first_name',
                    'billing_last_name',
                    'billing_email',
                    'billing_phone',
                    'billing_company'
                );
                
                $user_ids = array();
                foreach ($meta_keys as $meta_key) {
                    $sql = $wpdb->prepare("
                        SELECT DISTINCT user_id 
                        FROM {$wpdb->usermeta} 
                        WHERE meta_key = %s 
                        AND meta_value LIKE %s
                        LIMIT %d
                    ", $meta_key, '%' . $wpdb->esc_like($term) . '%', $limit);
                    
                    $results = $wpdb->get_col($sql);
                    $user_ids = array_merge($user_ids, $results);
                }
                
                $user_ids = array_unique($user_ids);
                error_log('VIPOS Customer Search: Found ' . count($user_ids) . ' user IDs from meta search: ' . implode(', ', $user_ids));
                
                if (!empty($user_ids)) {
                    // Try without role restriction first to see what we get
                    $users = get_users(array(
                        'include' => array_slice($user_ids, 0, $limit),
                        'orderby' => 'display_name',
                        'order' => 'ASC'
                    ));
                    
                    error_log('VIPOS Customer Search: Retrieved ' . count($users) . ' users without role restriction');
                    
                    foreach ($users as $user) {
                        error_log('VIPOS Customer Search: Processing user ID=' . $user->ID . ', roles=' . implode(',', $user->roles));
                        
                        // Check if user has any relevant role (customer, subscriber, or even admin/shop_manager for testing)
                        $valid_roles = array('customer', 'subscriber', 'administrator', 'shop_manager', 'editor');
                        $user_roles = $user->roles;
                        $has_valid_role = !empty(array_intersect($user_roles, $valid_roles));
                        
                        if ($has_valid_role) {
                            $formatted_customer = $this->format_customer_for_pos($user);
                            if ($formatted_customer) {
                                $customers[] = $formatted_customer;
                                error_log('VIPOS Customer Search: Added formatted customer for user ID=' . $user->ID);
                            } else {
                                error_log('VIPOS Customer Search: Failed to format customer for user ID=' . $user->ID);
                            }
                        } else {
                            error_log('VIPOS Customer Search: User ID=' . $user->ID . ' does not have valid role. Roles: ' . implode(',', $user_roles));
                        }
                    }
                }
            }
            
            // Remove duplicates based on ID
            $unique_customers = array();
            $seen_ids = array();
            foreach ($customers as $customer) {
                if (!in_array($customer['id'], $seen_ids)) {
                    $unique_customers[] = $customer;
                    $seen_ids[] = $customer['id'];
                }
            }
            
            // Limit results
            $unique_customers = array_slice($unique_customers, 0, $limit);
            
            error_log('VIPOS Customer Search: Final result - returning ' . count($unique_customers) . ' customers');
            
            return $unique_customers;

        } catch (Exception $e) {
            error_log('VIPOS Customer Search: Exception: ' . $e->getMessage());
            return array(); // Return empty array instead of WP_Error for AJAX
        }
    }
    
    /**
     * Get customer details
     */
    public function get_customer_details($customer_id) {
        $user = get_userdata($customer_id);
        
        if (!$user) {
            throw new Exception(__('Customer not found', 'vipos'));
        }
        
        // Get customer's orders
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $recent_orders = array();
        foreach ($orders as $order) {
            $recent_orders[] = array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'date_created' => $order->get_date_created()->format('Y-m-d H:i:s')
            );
        }
        
        // Get customer statistics
        $total_orders = wc_get_customer_order_count($customer_id);
        $total_spent = wc_get_customer_total_spent($customer_id);
        $avg_order_value = $total_orders > 0 ? $total_spent / $total_orders : 0;
        
        return array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'date_registered' => $user->user_registered,
            'billing' => array(
                'first_name' => get_user_meta($customer_id, 'billing_first_name', true),
                'last_name' => get_user_meta($customer_id, 'billing_last_name', true),
                'company' => get_user_meta($customer_id, 'billing_company', true),
                'address_1' => get_user_meta($customer_id, 'billing_address_1', true),
                'address_2' => get_user_meta($customer_id, 'billing_address_2', true),
                'city' => get_user_meta($customer_id, 'billing_city', true),
                'state' => get_user_meta($customer_id, 'billing_state', true),
                'postcode' => get_user_meta($customer_id, 'billing_postcode', true),
                'country' => get_user_meta($customer_id, 'billing_country', true),
                'email' => get_user_meta($customer_id, 'billing_email', true),
                'phone' => get_user_meta($customer_id, 'billing_phone', true)
            ),
            'shipping' => array(
                'first_name' => get_user_meta($customer_id, 'shipping_first_name', true),
                'last_name' => get_user_meta($customer_id, 'shipping_last_name', true),
                'company' => get_user_meta($customer_id, 'shipping_company', true),
                'address_1' => get_user_meta($customer_id, 'shipping_address_1', true),
                'address_2' => get_user_meta($customer_id, 'shipping_address_2', true),
                'city' => get_user_meta($customer_id, 'shipping_city', true),
                'state' => get_user_meta($customer_id, 'shipping_state', true),
                'postcode' => get_user_meta($customer_id, 'shipping_postcode', true),
                'country' => get_user_meta($customer_id, 'shipping_country', true)
            ),
            'statistics' => array(
                'total_orders' => $total_orders,
                'total_spent' => $total_spent,
                'avg_order_value' => $avg_order_value
            ),
            'recent_orders' => $recent_orders
        );
    }
    
    /**
     * Create new customer
     */
    public function create_customer($customer_data) {
        // Validate required fields
        if (empty($customer_data['email'])) {
            throw new Exception(__('Email is required', 'vipos'));
        }
        
        if (email_exists($customer_data['email'])) {
            throw new Exception(__('Email already exists', 'vipos'));
        }
        
        // Generate username if not provided
        if (empty($customer_data['username'])) {
            $customer_data['username'] = sanitize_user($customer_data['email']);
        }
        
        if (username_exists($customer_data['username'])) {
            $customer_data['username'] = $customer_data['username'] . '_' . rand(100, 999);
        }
        
        // Generate password if not provided
        if (empty($customer_data['password'])) {
            $customer_data['password'] = wp_generate_password();
        }
        
        // Create user
        $user_id = wp_create_user(
            $customer_data['username'],
            $customer_data['password'],
            $customer_data['email']
        );
        
        if (is_wp_error($user_id)) {
            throw new Exception($user_id->get_error_message());
        }
        
        // Set user role to customer
        $user = new WP_User($user_id);
        $user->set_role('customer');
        
        // Update user meta
        if (!empty($customer_data['first_name'])) {
            update_user_meta($user_id, 'first_name', sanitize_text_field($customer_data['first_name']));
            update_user_meta($user_id, 'billing_first_name', sanitize_text_field($customer_data['first_name']));
        }
        
        if (!empty($customer_data['last_name'])) {
            update_user_meta($user_id, 'last_name', sanitize_text_field($customer_data['last_name']));
            update_user_meta($user_id, 'billing_last_name', sanitize_text_field($customer_data['last_name']));
        }
        
        if (!empty($customer_data['phone'])) {
            update_user_meta($user_id, 'billing_phone', sanitize_text_field($customer_data['phone']));
        }
        
        // Set display name
        $display_name = trim(($customer_data['first_name'] ?? '') . ' ' . ($customer_data['last_name'] ?? ''));
        if (empty($display_name)) {
            $display_name = $customer_data['username'];
        }
        
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name
        ));
        
        // Add billing address if provided
        $billing_fields = array('company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country');
        foreach ($billing_fields as $field) {
            if (!empty($customer_data['billing_' . $field])) {
                update_user_meta($user_id, 'billing_' . $field, sanitize_text_field($customer_data['billing_' . $field]));
            }
        }
        
        return $user_id;
    }
    
    /**
     * Update customer
     */
    public function update_customer($customer_id, $customer_data) {
        $user = get_userdata($customer_id);
        
        if (!$user) {
            throw new Exception(__('Customer not found', 'vipos'));
        }
        
        // Update basic user data
        $user_data = array('ID' => $customer_id);
        
        if (!empty($customer_data['email']) && $customer_data['email'] !== $user->user_email) {
            if (email_exists($customer_data['email'])) {
                throw new Exception(__('Email already exists', 'vipos'));
            }
            $user_data['user_email'] = sanitize_email($customer_data['email']);
        }
        
        if (!empty($customer_data['first_name'])) {
            $user_data['first_name'] = sanitize_text_field($customer_data['first_name']);
            update_user_meta($customer_id, 'billing_first_name', sanitize_text_field($customer_data['first_name']));
        }
        
        if (!empty($customer_data['last_name'])) {
            $user_data['last_name'] = sanitize_text_field($customer_data['last_name']);
            update_user_meta($customer_id, 'billing_last_name', sanitize_text_field($customer_data['last_name']));
        }
        
        // Update display name
        if (!empty($customer_data['first_name']) || !empty($customer_data['last_name'])) {
            $first_name = $customer_data['first_name'] ?? $user->first_name;
            $last_name = $customer_data['last_name'] ?? $user->last_name;
            $user_data['display_name'] = trim($first_name . ' ' . $last_name);
        }
        
        if (count($user_data) > 1) {
            $result = wp_update_user($user_data);
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
        }
        
        // Update meta fields
        $meta_fields = array(
            'billing_phone', 'billing_company', 'billing_address_1', 'billing_address_2',
            'billing_city', 'billing_state', 'billing_postcode', 'billing_country',
            'shipping_first_name', 'shipping_last_name', 'shipping_company',
            'shipping_address_1', 'shipping_address_2', 'shipping_city',
            'shipping_state', 'shipping_postcode', 'shipping_country'
        );
        
        foreach ($meta_fields as $field) {
            if (isset($customer_data[$field])) {
                update_user_meta($customer_id, $field, sanitize_text_field($customer_data[$field]));
            }
        }
        
        return true;
    }
    
    /**
     * Get customer orders
     */
    public function get_customer_orders($customer_id, $page = 1, $per_page = 20) {
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $formatted_orders = array();
        
        foreach ($orders as $order) {
            $formatted_orders[] = array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'date_created' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'items_count' => $order->get_item_count(),
                'payment_method' => $order->get_payment_method_title(),
                'is_pos_order' => $order->get_meta('_vipos_order') ? true : false
            );
        }
        
        // Get total orders count
        $total_orders = wc_get_customer_order_count($customer_id);
        
        return array(
            'orders' => $formatted_orders,
            'total_orders' => $total_orders,
            'total_pages' => ceil($total_orders / $per_page),
            'current_page' => $page
        );
    }
      /**
     * Format customer for POS
     */
    private function format_customer_for_pos($user) {
        // Make sure we have a valid user object
        if (!$user || !is_object($user) || !isset($user->ID)) {
            error_log('VIPOS Customer Manager: Invalid user object passed to format_customer_for_pos');
            return false;
        }
        
        // Get user meta properly
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        $billing_phone = get_user_meta($user->ID, 'billing_phone', true);
        
        $billing_address = array(
            get_user_meta($user->ID, 'billing_address_1', true),
            get_user_meta($user->ID, 'billing_city', true),
            get_user_meta($user->ID, 'billing_state', true)
        );
        $billing_address = array_filter($billing_address);
        
        // Construct display name if not available
        $display_name = $user->display_name;
        if (empty($display_name) && ($first_name || $last_name)) {
            $display_name = trim($first_name . ' ' . $last_name);
        }
        if (empty($display_name)) {
            $display_name = $user->user_email;
        }
        
        error_log('VIPOS Format Customer: ID=' . $user->ID . ', Name=' . $display_name . ', Email=' . $user->user_email);
        
        return array(
            'id' => $user->ID,
            'name' => $display_name,
            'email' => $user->user_email,
            'phone' => $billing_phone,
            'address' => implode(', ', $billing_address),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'date_registered' => $user->user_registered
        );
    }
    
    /**
     * Handle create customer AJAX
     */
    public function handle_create_customer() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $customer_data = array_map('sanitize_text_field', $_POST['customer_data']);
            $customer_id = $this->create_customer($customer_data);
            
            $customer = $this->format_customer_for_pos(get_userdata($customer_id));
            
            wp_send_json_success(array(
                'customer' => $customer,
                'message' => __('Customer created successfully', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle update customer AJAX
     */
    public function handle_update_customer() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $customer_id = intval($_POST['customer_id']);
            $customer_data = array_map('sanitize_text_field', $_POST['customer_data']);
            
            $this->update_customer($customer_id, $customer_data);
            
            $customer = $this->format_customer_for_pos(get_userdata($customer_id));
            
            wp_send_json_success(array(
                'customer' => $customer,
                'message' => __('Customer updated successfully', 'vipos')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle get customer details AJAX
     */
    public function handle_get_customer_details() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $customer_id = intval($_POST['customer_id']);
            $customer_details = $this->get_customer_details($customer_id);
            
            wp_send_json_success($customer_details);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle get customer orders AJAX
     */
    public function handle_get_customer_orders() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $customer_id = intval($_POST['customer_id']);
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
            
            $orders = $this->get_customer_orders($customer_id, $page, $per_page);
            
            wp_send_json_success($orders);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }    /**
     * Handle search customers AJAX
     */
    public function handle_search_customers() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'vipos_nonce') || !current_user_can('vipos_access')) {
            wp_die(__('Security check failed', 'vipos'));
        }
        
        try {
            $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
            
            // Debug info
            error_log('VIPOS Customer Manager: Handling search request for term: "' . $search_term . '"');
            
            $customers = $this->search_customers($search_term, $limit);
            
            // Debug info
            error_log('VIPOS Customer Manager: Found and returning ' . count($customers) . ' customers');
            
            // Return successful response with customers data
            wp_send_json_success($customers);
            
        } catch (Exception $e) {
            error_log('VIPOS Customer Manager: Customer search error: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}
