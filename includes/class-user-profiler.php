
/**
 * Smart User Profiler Class
 * File: includes/class-user-profiler.php
 */
class SmartUserProfiler {
    
    /**
     * Get user profile for session
     */
    public function get_profile($session_id) {
        global $wpdb;
        
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}smart_chatbot_user_profiles WHERE session_id = %s",
            $session_id
        ));
        
        if ($profile) {
            return array(
                'session_id' => $profile->session_id,
                'user_id' => $profile->user_id,
                'preferences' => json_decode($profile->preferences, true) ?: array(),
                'purchase_history' => json_decode($profile->purchase_history, true) ?: array(),
                'browsing_behavior' => json_decode($profile->browsing_behavior, true) ?: array(),
                'interaction_patterns' => json_decode($profile->interaction_patterns, true) ?: array(),
                'preferred_categories' => explode(',', $profile->preferred_categories ?: ''),
                'budget_range' => $profile->budget_range,
                'communication_style' => $profile->communication_style,
                'language_preference' => $profile->language_preference,
                'last_interaction' => $profile->last_interaction
            );
        }
        
        // Create new profile
        return $this->create_new_profile($session_id);
    }
    
    /**
     * Create new user profile
     */
    private function create_new_profile($session_id) {
        global $wpdb;
        
        $default_profile = array(
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'preferences' => json_encode(array()),
            'purchase_history' => json_encode(array()),
            'browsing_behavior' => json_encode(array()),
            'interaction_patterns' => json_encode(array()),
            'preferred_categories' => '',
            'budget_range' => 'unknown',
            'communication_style' => 'friendly',
            'language_preference' => 'en'
        );
        
        $wpdb->insert($wpdb->prefix . 'smart_chatbot_user_profiles', $default_profile);
        
        return $this->get_profile($session_id);
    }
    
    /**
     * Update user profile with new interaction data
     */
    public function update_profile($session_id, $interaction_data) {
        global $wpdb;
        
        $current_profile = $this->get_profile($session_id);
        
        // Update interaction patterns
        $patterns = $current_profile['interaction_patterns'];
        $patterns[] = array(
            'timestamp' => current_time('mysql'),
            'intent' => $interaction_data['intent'] ?? 'unknown',
            'satisfaction' => $interaction_data['satisfaction'] ?? null
        );
        
        // Keep only last 50 interactions
        if (count($patterns) > 50) {
            $patterns = array_slice($patterns, -50);
        }
        
        // Update preferences based on interactions
        $preferences = $current_profile['preferences'];
        if (!empty($interaction_data['category_preference'])) {
            $preferences['categories'] = $preferences['categories'] ?? array();
            $preferences['categories'][] = $interaction_data['category_preference'];
            $preferences['categories'] = array_unique($preferences['categories']);
        }
        
        // Update budget range if detected
        if (!empty($interaction_data['budget_range'])) {
            $budget_range = $interaction_data['budget_range'];
        } else {
            $budget_range = $current_profile['budget_range'];
        }
        
        $update_data = array(
            'preferences' => json_encode($preferences),
            'interaction_patterns' => json_encode($patterns),
            'budget_range' => $budget_range,
            'last_interaction' => current_time('mysql')
        );
        
        $wpdb->update(
            $wpdb->prefix . 'smart_chatbot_user_profiles',
            $update_data,
            array('session_id' => $session_id)
        );
    }
    
    /**
     * Get user purchase history from WooCommerce
     */
    public function get_woocommerce_history($user_id = null) {
        if (!$user_id) {
            return array();
        }
        
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => array('completed', 'processing'),
            'limit' => 10
        ));
        
        $purchase_data = array();
        foreach ($orders as $order) {
            $items = $order->get_items();
            foreach ($items as $item) {
                $product = $item->get_product();
                if ($product) {
                    $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'));
                    $purchase_data[] = array(
                        'product_id' => $product->get_id(),
                        'product_name' => $product->get_name(),
                        'categories' => $categories,
                        'price' => $product->get_price(),
                        'purchase_date' => $order->get_date_created()->date('Y-m-d')
                    );
                }
            }
        }
        
        return $purchase_data;
    }
}