<?php
/**
 * Part 4: Product Formatter & Visual Cards
 * File: includes/class-product-formatter.php
 */

class SmartProductFormatter {
    
    /**
     * Format products as beautiful visual cards
     */
    public function format_product_cards($scored_products, $intent) {
        if (empty($scored_products)) {
            return $this->format_no_products_response($intent);
        }
        
        $response = array(
            'type' => 'product_recommendations',
            'message' => $this->generate_intro_message($intent, count($scored_products)),
            'products' => array(),
            'additional_info' => $this->generate_additional_info($intent),
            'follow_up_suggestions' => $this->generate_follow_up_suggestions($intent)
        );
        
        // Format each product as a card
        foreach ($scored_products as $scored_product) {
            $response['products'][] = $this->format_single_product_card($scored_product, $intent);
        }
        
        return $response;
    }
    
    /**
     * Generate intro message for recommendations
     */
    private function generate_intro_message($intent, $product_count) {
        $messages = array();
        
        if ($intent['category'] !== 'unknown') {
            $category_names = array(
                'electronics' => 'electronics',
                'fashion' => 'fashion items',
                'home' => 'home products',
                'sports' => 'sports equipment',
                'beauty' => 'beauty products',
                'books' => 'books'
            );
            $category_name = $category_names[$intent['category']] ?? 'products';
            $messages[] = "🎯 I found {$product_count} perfect {$category_name} for you!";
        } else {
            $messages[] = "✨ Here are {$product_count} amazing products I think you'll love!";
        }
        
        if ($intent['budget'] !== 'unknown') {
            $budget_messages = array(
                'under_50' => "All within your budget-friendly range! 💰",
                '50_100' => "Great value in your $50-$100 range! 💰💰",
                '100_250' => "Quality picks in your $100-$250 range! 💰💰💰",
                '250_500' => "Premium options in your $250-$500 range! 💰💰💰💰",
                'over_500' => "Luxury choices for your premium budget! 💎"
            );
            $messages[] = $budget_messages[$intent['budget']] ?? '';
        }
        
        return implode(' ', array_filter($messages));
    }
    
    /**
     * Format single product card
     */
    private function format_single_product_card($scored_product, $intent) {
        $product = $scored_product['product'];
        $post = $scored_product['post'];
        $score = $scored_product['score'];
        $reasons = $scored_product['reasons'];
        
        // Get product image
        $image_id = get_post_thumbnail_id($product->get_id());
        $image_url = $image_id ? wp_get_attachment_image_src($image_id, 'medium')[0] : wc_placeholder_img_src('medium');
        
        // Get product details
        $price = $product->get_price();
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $rating = $product->get_average_rating();
        $review_count = $product->get_review_count();
        $stock_status = $product->get_stock_status();
        
        // Format pricing
        $price_html = $this->format_price_display($price, $regular_price, $sale_price);
        
        // Get shipping info
        $shipping_info = $this->get_shipping_info($product);
        
        // Get product badges
        $badges = $this->get_product_badges($product, $score);
        
        // Get product categories
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'description' => $this->generate_smart_description($product, $intent),
            'image_url' => $image_url,
            'price' => array(
                'current' => $price,
                'regular' => $regular_price,
                'sale' => $sale_price,
                'formatted' => $price_html,
                'currency' => get_woocommerce_currency_symbol()
            ),
            'rating' => array(
                'average' => $rating,
                'count' => $review_count,
                'stars' => $this->generate_star_rating($rating),
                'formatted' => $this->format_rating_display($rating, $review_count)
            ),
            'availability' => array(
                'status' => $stock_status,
                'text' => $this->format_stock_status($stock_status),
                'icon' => $this->get_stock_icon($stock_status)
            ),
            'shipping' => $shipping_info,
            'badges' => $badges,
            'categories' => $categories,
            'permalink' => get_permalink($product->get_id()),
            'add_to_cart_url' => $product->add_to_cart_url(),
            'recommendation' => array(
                'score' => $score,
                'reasons' => $reasons,
                'why_recommended' => $this->generate_recommendation_explanation($product, $intent, $reasons)
            ),
            'key_features' => $this->extract_key_features($product),
            'comparison_points' => $this->generate_comparison_points($product)
        );
    }
    
    /**
     * Generate smart product description
     */
    private function generate_smart_description($product, $intent) {
        $description = $product->get_short_description();
        
        if (empty($description)) {
            $description = wp_trim_words($product->get_description(), 25);
        }
        
        if (empty($description)) {
            // Generate description based on product name and category
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
            $category = !empty($categories) ? $categories[0] : 'product';
            $description = "High-quality {$category} - {$product->get_name()}";
        }
        
        return $description;
    }
    
    /**
     * Format price display with savings
     */
    private function format_price_display($price, $regular_price, $sale_price) {
        $currency = get_woocommerce_currency_symbol();
        
        if ($sale_price && $sale_price < $regular_price) {
            $savings = $regular_price - $sale_price;
            $savings_percent = round(($savings / $regular_price) * 100);
            
            return array(
                'current' => $currency . number_format($sale_price, 2),
                'original' => $currency . number_format($regular_price, 2),
                'savings' => $currency . number_format($savings, 2),
                'savings_percent' => $savings_percent,
                'is_on_sale' => true,
                'display' => "<span class='sale-price'>{$currency}" . number_format($sale_price, 2) . "</span> <span class='original-price'>{$currency}" . number_format($regular_price, 2) . "</span> <span class='savings'>Save {$savings_percent}%!</span>"
            );
        }
        
        return array(
            'current' => $currency . number_format($price, 2),
            'original' => null,
            'savings' => null,
            'savings_percent' => 0,
            'is_on_sale' => false,
            'display' => $currency . number_format($price, 2)
        );
    }
    
    /**
     * Generate star rating display
     */
    private function generate_star_rating($rating) {
        $stars = '';
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full_stars) {
                $stars .= '⭐';
            } elseif ($i == $full_stars + 1 && $half_star) {
                $stars .= '⭐'; // Use full star for simplicity
            } else {
                $stars .= '☆';
            }
        }
        
        return $stars;
    }
    
    /**
     * Format rating display
     */
    private function format_rating_display($rating, $review_count) {
        if ($rating == 0) {
            return 'No reviews yet';
        }
        
        $stars = $this->generate_star_rating($rating);
        return $stars . ' ' . number_format($rating, 1) . '/5 (' . $review_count . ' reviews)';
    }
    
    /**
     * Get product badges
     */
    private function get_product_badges($product, $score) {
        $badges = array();
        
        // High score badge
        if ($score >= 80) {
            $badges[] = array('text' => '🔥 Hot Pick', 'class' => 'badge-hot', 'color' => '#ff4757');
        } elseif ($score >= 60) {
            $badges[] = array('text' => '⭐ Great Choice', 'class' => 'badge-great', 'color' => '#ffa502');
        }
        
        // Sale badge
        if ($product->is_on_sale()) {
            $badges[] = array('text' => '💰 On Sale', 'class' => 'badge-sale', 'color' => '#26de81');
        }
        
        // Best seller badge
        $total_sales = get_post_meta($product->get_id(), 'total_sales', true);
        if ($total_sales > 100) {
            $badges[] = array('text' => '🏆 Best Seller', 'class' => 'badge-bestseller', 'color' => '#fd79a8');
        }
        
        // New product badge
        $created_date = get_the_date('U', $product->get_id());
        $days_old = (time() - $created_date) / (60 * 60 * 24);
        if ($days_old <= 30) {
            $badges[] = array('text' => '✨ New', 'class' => 'badge-new', 'color' => '#a29bfe');
        }
        
        // High rating badge
        if ($product->get_average_rating() >= 4.5) {
            $badges[] = array('text' => '⭐ Top Rated', 'class' => 'badge-rated', 'color' => '#fdcb6e');
        }
        
        return $badges;
    }
    
    /**
     * Get shipping information
     */
    private function get_shipping_info($product) {
        $shipping_class = $product->get_shipping_class();
        $weight = $product->get_weight();
        
        // Default shipping info - customize based on your store
        $shipping_info = array(
            'free_shipping' => false,
            'delivery_time' => '3-5 business days',
            'cost' => 'Calculated at checkout',
            'icon' => '📦'
        );
        
        // Check for free shipping (if product price > threshold)
        $free_shipping_threshold = 50; // Adjust as needed
        if ($product->get_price() >= $free_shipping_threshold) {
            $shipping_info['free_shipping'] = true;
            $shipping_info['cost'] = 'FREE';
            $shipping_info['icon'] = '🚚';
        }
        
        // Express shipping for electronics
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'));
        if (in_array('electronics', $categories)) {
            $shipping_info['express_available'] = true;
            $shipping_info['express_time'] = '1-2 business days';
        }
        
        return $shipping_info;
    }
    
    /**
     * Format stock status
     */
    private function format_stock_status($status) {
        $status_map = array(
            'instock' => '✅ In Stock',
            'outofstock' => '❌ Out of Stock',
            'onbackorder' => '⏳ Backordered'
        );
        
        return $status_map[$status] ?? '❓ Unknown';
    }
    
    /**
     * Get stock icon
     */
    private function get_stock_icon($status) {
        $icons = array(
            'instock' => '✅',
            'outofstock' => '❌',
            'onbackorder' => '⏳'
        );
        
        return $icons[$status] ?? '❓';
    }
    
	
		
    /**
     * Generate recommendation explanation (continued from Part 4)
     */
    private function generate_recommendation_explanation($product, $intent, $reasons) {
        $explanations = array();
        
        if (!empty($intent['category']) && $intent['category'] !== 'unknown') {
            $explanations[] = "Perfect match for {$intent['category']} category";
        }
        
        if (!empty($intent['budget']) && $intent['budget'] !== 'unknown') {
            $explanations[] = "Fits your budget range perfectly";
        }
        
        if ($product->get_average_rating() >= 4.0) {
            $explanations[] = "Highly rated by customers";
        }
        
        if (count($explanations) === 0) {
            $explanations[] = "Great quality product with excellent value";
        }
        
        return implode(' • ', $explanations);
    }
    
    /**
     * Extract key features from product
     */
    private function extract_key_features($product) {
        $features = array();
        
        // Get product attributes
        $attributes = $product->get_attributes();
        $feature_count = 0;
        
        foreach ($attributes as $attribute) {
            if ($feature_count >= 3) break; // Limit to 3 key features
            
            if ($attribute->get_visible()) {
                $attribute_name = wc_attribute_label($attribute->get_name());
                $attribute_values = wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names'));
                
                if (!empty($attribute_values)) {
                    $features[] = $attribute_name . ': ' . implode(', ', $attribute_values);
                    $feature_count++;
                }
            }
        }
        
        // If no attributes, extract from description
        if (empty($features)) {
            $description = $product->get_description();
            if (!empty($description)) {
                // Simple feature extraction (you can enhance this with AI)
                if (preg_match_all('/•\s*([^•\n]+)/i', $description, $matches)) {
                    $features = array_slice($matches[1], 0, 3);
                }
            }
        }
        
        return $features;
    }
    
    /**
     * Generate comparison points
     */
    private function generate_comparison_points($product) {
        $points = array();
        
        // Price comparison
        $price = (float) $product->get_price();
        if ($price < 50) {
            $points[] = "💰 Budget-friendly option";
        } elseif ($price > 200) {
            $points[] = "💎 Premium quality";
        } else {
            $points[] = "⚖️ Great value for money";
        }
        
        // Rating comparison
        $rating = $product->get_average_rating();
        if ($rating >= 4.5) {
            $points[] = "⭐ Customer favorite";
        } elseif ($rating >= 4.0) {
            $points[] = "👍 Well-reviewed";
        }
        
        // Sales comparison
        $sales = (int) get_post_meta($product->get_id(), 'total_sales', true);
        if ($sales > 100) {
            $points[] = "🔥 Popular choice";
        }
        
        return $points;
    }
    
    /**
     * Generate additional info for recommendations
     */
    private function generate_additional_info($intent) {
        $info = array();
        
        if ($intent['purpose'] === 'gift') {
            $info[] = "🎁 All items come with beautiful gift wrapping options";
        }
        
        if ($intent['urgency'] === 'urgent') {
            $info[] = "⚡ Express shipping available for faster delivery";
        }
        
        $info[] = "🔄 30-day return policy on all items";
        $info[] = "💬 Need help deciding? Just ask me more questions!";
        
        return $info;
    }
    
    /**
     * Generate follow-up suggestions
     */
    private function generate_follow_up_suggestions($intent) {
        $suggestions = array();
        
        $suggestions[] = array(
            'text' => '🔍 Show me more details',
            'action' => 'show_more_details'
        );
        
        $suggestions[] = array(
            'text' => '⚖️ Compare these products',
            'action' => 'compare_products'
        );
        
        if ($intent['category'] !== 'unknown') {
            $suggestions[] = array(
                'text' => '🎯 Find similar products',
                'action' => 'find_similar',
                'category' => $intent['category']
            );
        }
        
        $suggestions[] = array(
            'text' => '💰 Show different price range',
            'action' => 'change_budget'
        );
        
        $suggestions[] = array(
            'text' => '🎁 Gift wrapping options',
            'action' => 'gift_options'
        );
        
        return $suggestions;
    }
    
    /**
     * Handle no products found
     */
    private function format_no_products_response($intent) {
        return array(
            'type' => 'no_products_found',
            'message' => "😔 I couldn't find products matching your exact criteria, but let me help you find something great!",
            'suggestions' => array(
                array('text' => '🔍 Broaden search criteria', 'action' => 'broaden_search'),
                array('text' => '💰 Try different price range', 'action' => 'change_budget'),
                array('text' => '📂 Browse popular categories', 'action' => 'browse_categories'),
                array('text' => '🆘 Get personal assistance', 'action' => 'human_help')
            ),
            'alternative_message' => "Here are some popular products you might like instead:",
            'fallback_products' => $this->get_fallback_products()
        );
    }
    
    /**
     * Get fallback products for when no matches found
     */
    private function get_fallback_products() {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'meta_key' => 'total_sales',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock'
                )
            )
        );
        
        return get_posts($args);
    }
}

/**
 * AJAX Handlers for the Main Plugin Class
 * Add these methods to the WooSmartChatbotAdvanced class
 */

    /**
     * Handle main chatbot message AJAX
     */
    public function handle_chatbot_message() {
        check_ajax_referer('smart_chatbot_nonce', 'nonce');
        
        $message = sanitize_text_field($_POST['message']);
        $session_id = sanitize_text_field($_POST['session_id']);
        $conversation_history = isset($_POST['conversation_history']) ? json_decode(stripslashes($_POST['conversation_history']), true) : array();
        
        if (empty($message)) {
            wp_send_json_error('Invalid message');
        }
        
        $start_time = microtime(true);
        
        // Check if this is a product recommendation request
        if ($this->is_product_request($message)) {
            $response_data = $this->product_recommendation_engine->get_smart_recommendations($message, $session_id, $conversation_history);
            
            $response_data['response_type'] = 'product_recommendation';
            $response_data['processing_time'] = round(microtime(true) - $start_time, 2);
            
        } else {
            // Handle regular chatbot conversation
            $response_data = $this->process_regular_message($message, $conversation_history, $session_id);
            $response_data['response_type'] = 'regular_chat';
            $response_data['processing_time'] = round(microtime(true) - $start_time, 2);
        }
        
        // Log conversation
        $this->log_conversation($session_id, $message, $response_data);
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Check if message is a product recommendation request
     */
    private function is_product_request($message) {
        $product_keywords = array(
            'recommend', 'suggestion', 'find', 'looking for', 'need', 'want', 'buy', 'purchase', 
            'show me', 'product', 'item', 'shopping', 'gift', 'present'
        );
        
        $message_lower = strtolower($message);
        
        foreach ($product_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Process regular non-product messages
     */
    private function process_regular_message($message, $conversation_history, $session_id) {
        // Check knowledge base first
        $knowledge_response = $this->search_knowledge_base($message);
        if ($knowledge_response) {
            return array(
                'response' => $knowledge_response['answer'],
                'knowledge_used' => true,
                'source' => $knowledge_response['source'],
                'confidence' => $knowledge_response['confidence']
            );
        }
        
        // Check for special requests (order inquiry, etc.)
        $special_response = $this->handle_special_requests($message);
        if ($special_response) {
            return $special_response;
        }
        
        // Use OpenAI for general conversation
        $ai_response = $this->get_ai_response($message, $conversation_history, $session_id);
        if ($ai_response) {
            return array(
                'response' => $ai_response,
                'knowledge_used' => false,
                'source' => 'openai',
                'confidence' => 0.8
            );
        }
        
        // Fallback response
        return array(
            'response' => "I'd be happy to help! I can assist you with:\n\n🛍️ Product recommendations\n📦 Order status\n📋 Store policies\n🎫 Discount codes\n\nWhat would you like to know more about?",
            'knowledge_used' => false,
            'source' => 'fallback',
            'confidence' => 0.5
        );
    }
    
    /**
     * Handle product recommendations AJAX
     */
    public function handle_product_recommendations() {
        check_ajax_referer('smart_chatbot_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query']);
        $session_id = sanitize_text_field($_POST['session_id']);
        $filters = isset($_POST['filters']) ? json_decode(stripslashes($_POST['filters']), true) : array();
        
        $response = $this->product_recommendation_engine->get_smart_recommendations($query, $session_id);
        
        wp_send_json_success($response);
    }
    
    /**
     * Save user preferences AJAX
     */
    public function save_user_preferences() {
        check_ajax_referer('smart_chatbot_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id']);
        $preferences = isset($_POST['preferences']) ? json_decode(stripslashes($_POST['preferences']), true) : array();
        
        // Update user profile
        $user_profiler = new SmartUserProfiler();
        $user_profiler->update_profile($session_id, array(
            'category_preference' => $preferences['category'] ?? null,
            'budget_range' => $preferences['budget'] ?? null,
            'satisfaction' => $preferences['satisfaction'] ?? null
        ));
        
        wp_send_json_success('Preferences saved successfully');
    }
    
    /**
     * Log conversation for analytics
     */
    private function log_conversation($session_id, $user_message, $response_data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'smart_chatbot_conversations',
            array(
                'session_id' => $session_id,
                'user_id' => get_current_user_id() ?: null,
                'user_message' => $user_message,
                'bot_response' => is_array($response_data['response']) ? json_encode($response_data['response']) : $response_data['response'],
                'intent' => $response_data['intent'] ?? 'unknown',
                'entities' => json_encode($response_data['entities'] ?? array()),
                'context_data' => json_encode($response_data),
                'response_time' => $response_data['processing_time'] ?? null,
                'knowledge_source' => $response_data['source'] ?? 'unknown',
                'confidence_level' => $response_data['confidence'] ?? null,
                'user_ip' => $this->get_user_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'language' => 'en'
            )
        );
    }
    
    /**
     * Search knowledge base
     */
    private function search_knowledge_base($message) {
        global $wpdb;
        
        $keywords = $this->extract_keywords($message);
        $search_terms = implode(' ', $keywords);
        
        if (empty($search_terms)) {
            return false;
        }
        
        $query = "SELECT *, 
                    MATCH(question, answer, keywords, context_tags) AGAINST(%s IN NATURAL LANGUAGE MODE) as relevance
                  FROM {$wpdb->prefix}smart_chatbot_knowledge 
                  WHERE MATCH(question, answer, keywords, context_tags) AGAINST(%s IN NATURAL LANGUAGE MODE)
                  ORDER BY relevance DESC, confidence_score DESC, usage_count DESC 
                  LIMIT 1";
        
        $result = $wpdb->get_row($wpdb->prepare($query, $search_terms, $search_terms));
        
        if ($result && $result->relevance > 0.5) {
            // Update usage count
            $wpdb->update(
                $wpdb->prefix . 'smart_chatbot_knowledge',
                array(
                    'usage_count' => $result->usage_count + 1,
                    'last_used' => current_time('mysql')
                ),
                array('id' => $result->id)
            );
            
            return array(
                'answer' => $result->answer,
                'source' => $result->source,
                'confidence' => min($result->relevance, 1.0)
            );
        }
        
        return false;
    }
    
    /**
     * Extract keywords from message
     */
    private function extract_keywords($message) {
        $stop_words = array('the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'what', 'where', 'when', 'how', 'why', 'who', 'i', 'you', 'we', 'they', 'my', 'your', 'our', 'their');
        
        $text = strtolower($message);
        $text = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text);
        
        $keywords = array();
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && !in_array($word, $stop_words)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Handle special requests (orders, discounts, etc.)
     */
    private function handle_special_requests($message) {
        // Order inquiry
        if (preg_match('/\b(order|track|shipping|delivery)\b/i', $message)) {
            return $this->handle_order_inquiry($message);
        }
        
        // Discount codes
        if (preg_match('/\b(discount|coupon|promo|code|sale)\b/i', $message)) {
            return array(
                'response' => "🎉 Here are our current discount codes:\n\n💰 **WELCOME10** - 10% off your first order\n🎁 **SAVE20** - 20% off orders over $100\n✨ **NEWCUSTOMER** - 15% off for new customers\n\nJust enter the code at checkout!",
                'knowledge_used' => true,
                'source' => 'discount_system',
                'confidence' => 1.0
            );
        }
        
        return false;
    }
    
    /**
     * Handle order inquiry
     */
    private function handle_order_inquiry($message) {
        // Extract order number if present
        if (preg_match('/\b(\d{4,8})\b/', $message, $matches)) {
            $order_number = $matches[1];
            $order = wc_get_order($order_number);
            
            if ($order) {
                $status = $order->get_status();
                $total = $order->get_formatted_order_total();
                $date = $order->get_date_created()->format('Y-m-d');
                
                return array(
                    'response' => "📦 **Order #{$order_number}**\n\n📅 **Date:** {$date}\n💰 **Total:** {$total}\n📊 **Status:** " . ucfirst($status) . "\n\n" . $this->get_order_status_message($status),
                    'knowledge_used' => true,
                    'source' => 'order_system',
                    'confidence' => 1.0
                );
            } else {
                return array(
                    'response' => "❌ I couldn't find order #{$order_number}. Please check the order number and try again, or contact support if you need assistance.",
                    'knowledge_used' => true,
                    'source' => 'order_system',
                    'confidence' => 1.0
                );
            }
        }
        
        // General order help
        return array(
            'response' => "📦 I can help you check your order status! Please provide your order number (usually 4-8 digits) and I'll look it up for you.\n\n💡 You can find your order number in:\n• Your email confirmation\n• Your account order history\n• Your receipt",
            'knowledge_used' => true,
            'source' => 'order_system',
            'confidence' => 1.0
        );
    }
    
    /**
     * Get order status message
     */
    private function get_order_status_message($status) {
        $messages = array(
            'pending' => '⏳ Your order is pending payment. Please complete your payment to proceed.',
            'processing' => '⚙️ Great! We\'re preparing your order for shipment.',
            'shipped' => '🚚 Your order is on its way! You should receive it soon.',
            'completed' => '✅ Your order has been delivered! Thank you for your purchase.',
            'cancelled' => '❌ This order has been cancelled. Contact us if you have questions.',
            'refunded' => '💰 This order has been refunded.'
        );
        
        return $messages[$status] ?? '📋 Order status: ' . ucfirst($status);
    }
    
    /**
     * Get AI response using OpenAI
     */
    private function get_ai_response($message, $conversation_history, $session_id) {
        if (empty($this->openai_api_key)) {
            return false;
        }
        
        $context = $this->build_conversation_context($session_id);
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => "You are a helpful customer service AI for an e-commerce store. You specialize in product recommendations, order help, and general store assistance. Be friendly, helpful, and concise. Store context: {$context}"
            )
        );
        
        // Add conversation history
        foreach (array_slice($conversation_history, -10) as $msg) {
            $messages[] = array(
                'role' => $msg['role'],
                'content' => $msg['content']
            );
        }
        
        // Add current message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        $data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7
        );
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->openai_api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return trim($result['choices'][0]['message']['content']);
        }
        
        return false;
    }
    
    /**
     * Build conversation context
     */
    private function build_conversation_context($session_id) {
        $context = "Store: " . get_bloginfo('name') . "\n";
        $context .= "Description: " . get_bloginfo('description') . "\n";
        
        // Add company info
        if (!empty($this->knowledge_base['company_info'])) {
            $context .= "Company Information:\n";
            foreach ($this->knowledge_base['company_info'] as $info) {
                $context .= "- {$info->title}: {$info->content}\n";
            }
        }
        
        return $context;
    }