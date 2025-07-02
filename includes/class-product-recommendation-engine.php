<?php
/**
 * Part 2: Smart Product Recommendation Engine
 * File: includes/class-product-recommendation-engine.php
 */

class SmartProductRecommendationEngine {
    
    private $intent_analyzer;
    private $user_profiler;
    private $product_formatter;
    
    public function __construct() {
        $this->intent_analyzer = new SmartIntentAnalyzer();
        $this->user_profiler = new SmartUserProfiler();
        $this->product_formatter = new SmartProductFormatter();
    }
    
    /**
     * Main product recommendation function
     */
    public function get_smart_recommendations($user_message, $session_id, $conversation_history = array()) {
        // Step 1: Analyze user intent
        $intent = $this->intent_analyzer->analyze_intent($user_message, $conversation_history);
        
        // Step 2: Get user profile
        $user_profile = $this->user_profiler->get_profile($session_id);
        
        // Step 3: Check if we need more information
        if ($this->needs_clarification($intent)) {
            return $this->generate_clarifying_questions($intent);
        }
        
        // Step 4: Find matching products
        $products = $this->find_matching_products($intent, $user_profile);
        
        // Step 5: Score and rank products
        $scored_products = $this->score_and_rank_products($products, $intent, $user_profile);
        
        // Step 6: Format as beautiful product cards
        $formatted_response = $this->product_formatter->format_product_cards($scored_products, $intent);
        
        // Step 7: Log recommendation for learning
        $this->log_recommendation($session_id, $user_message, $intent, $scored_products);
        
        return $formatted_response;
    }
    
    /**
     * Check if we need more information from user
     */
    private function needs_clarification($intent) {
        $required_fields = array('category', 'budget', 'purpose');
        $missing_fields = array();
        
        foreach ($required_fields as $field) {
            if (empty($intent[$field]) || $intent[$field] === 'unknown') {
                $missing_fields[] = $field;
            }
        }
        
        // If we're missing critical information, ask for clarification
        return !empty($missing_fields);
    }
    
    /**
     * Generate clarifying questions based on missing intent data
     */
    private function generate_clarifying_questions($intent) {
        $questions = array();
        
        if (empty($intent['category']) || $intent['category'] === 'unknown') {
            $questions[] = array(
                'type' => 'category_selection',
                'question' => 'What type of product are you looking for?',
                'options' => array(
                    array('value' => 'electronics', 'label' => '📱 Electronics', 'emoji' => '📱'),
                    array('value' => 'fashion', 'label' => '👕 Fashion & Clothing', 'emoji' => '👕'),
                    array('value' => 'home', 'label' => '🏠 Home & Garden', 'emoji' => '🏠'),
                    array('value' => 'sports', 'label' => '⚽ Sports & Outdoors', 'emoji' => '⚽'),
                    array('value' => 'beauty', 'label' => '💄 Beauty & Health', 'emoji' => '💄'),
                    array('value' => 'books', 'label' => '📚 Books & Media', 'emoji' => '📚')
                )
            );
        }
        
        if (empty($intent['budget']) || $intent['budget'] === 'unknown') {
            $questions[] = array(
                'type' => 'budget_selection',
                'question' => 'What\'s your budget range?',
                'options' => array(
                    array('value' => 'under_50', 'label' => '💰 Under $50', 'range' => array(0, 50)),
                    array('value' => '50_100', 'label' => '💰💰 $50 - $100', 'range' => array(50, 100)),
                    array('value' => '100_250', 'label' => '💰💰💰 $100 - $250', 'range' => array(100, 250)),
                    array('value' => '250_500', 'label' => '💰💰💰💰 $250 - $500', 'range' => array(250, 500)),
                    array('value' => 'over_500', 'label' => '💎 Over $500', 'range' => array(500, 999999))
                )
            );
        }
        
        if (empty($intent['purpose']) || $intent['purpose'] === 'unknown') {
            $questions[] = array(
                'type' => 'purpose_selection',
                'question' => 'What\'s this for?',
                'options' => array(
                    array('value' => 'personal', 'label' => '👤 For myself', 'emoji' => '👤'),
                    array('value' => 'gift', 'label' => '🎁 As a gift', 'emoji' => '🎁'),
                    array('value' => 'work', 'label' => '💼 For work/business', 'emoji' => '💼'),
                    array('value' => 'hobby', 'label' => '🎨 For hobby/fun', 'emoji' => '🎨')
                )
            );
        }
        
        return array(
            'type' => 'clarification_needed',
            'message' => 'I\'d love to help you find the perfect products! Let me ask a few quick questions to give you the best recommendations:',
            'questions' => $questions,
            'response_format' => 'interactive_questions'
        );
    }
    
    /**
     * Find products matching user intent and profile
     */
    private function find_matching_products($intent, $user_profile) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 12, // Get more products for better scoring
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock'
                ),
                array(
                    'key' => '_visibility',
                    'value' => array('visible', 'catalog'),
                    'compare' => 'IN'
                )
            )
        );
        
        // Add category filter
        if (!empty($intent['category']) && $intent['category'] !== 'unknown') {
            $category_map = array(
                'electronics' => array('electronics', 'computers', 'phones', 'tablets'),
                'fashion' => array('clothing', 'fashion', 'apparel', 'shoes'),
                'home' => array('home-garden', 'furniture', 'decor', 'kitchen'),
                'sports' => array('sports', 'fitness', 'outdoor', 'recreation'),
                'beauty' => array('beauty', 'health', 'cosmetics', 'skincare'),
                'books' => array('books', 'media', 'entertainment')
            );
            
            if (isset($category_map[$intent['category']])) {
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => $category_map[$intent['category']],
                        'operator' => 'IN'
                    )
                );
            }
        }
        
        // Add price range filter
        if (!empty($intent['budget']) && $intent['budget'] !== 'unknown') {
            $budget_ranges = array(
                'under_50' => array(0, 50),
                '50_100' => array(50, 100),
                '100_250' => array(100, 250),
                '250_500' => array(250, 500),
                'over_500' => array(500, 999999)
            );
            
            if (isset($budget_ranges[$intent['budget']])) {
                $range = $budget_ranges[$intent['budget']];
                $args['meta_query'][] = array(
                    'key' => '_price',
                    'value' => $range,
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                );
            }
        }
        
        // Add keyword search if present
        if (!empty($intent['keywords'])) {
            $args['s'] = implode(' ', $intent['keywords']);
        }
        
        // Add user preference filters
        if (!empty($user_profile['preferred_brands'])) {
            // Add brand filtering logic here
        }
        
        $products = get_posts($args);
        
        // If no products found, try broader search
        if (empty($products)) {
            unset($args['tax_query']);
            unset($args['s']);
            $args['orderby'] = 'popularity';
            $args['posts_per_page'] = 6;
            $products = get_posts($args);
        }
        
        return $products;
    }
    
    /**
     * Score and rank products based on multiple factors
     */
    private function score_and_rank_products($products, $intent, $user_profile) {
        $scored_products = array();
        
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) continue;
            
            $score = $this->calculate_product_score($product, $intent, $user_profile);
            
            $scored_products[] = array(
                'product' => $product,
                'post' => $product_post,
                'score' => $score,
                'reasons' => $this->get_recommendation_reasons($product, $intent, $user_profile)
            );
        }
        
        // Sort by score (highest first)
        usort($scored_products, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Return top products
        return array_slice($scored_products, 0, 6);
    }
    
    /**
     * Calculate product recommendation score
     */
    private function calculate_product_score($product, $intent, $user_profile) {
        $score = 0;
        
        // Base score factors
        $factors = array(
            'price_match' => 0,
            'category_match' => 0,
            'popularity' => 0,
            'rating' => 0,
            'availability' => 0,
            'user_preference' => 0,
            'sales_velocity' => 0
        );
        
        // Price matching (25% weight)
        if (!empty($intent['budget']) && $intent['budget'] !== 'unknown') {
            $price = (float) $product->get_price();
            $budget_ranges = array(
                'under_50' => array(0, 50),
                '50_100' => array(50, 100),
                '100_250' => array(100, 250),
                '250_500' => array(250, 500),
                'over_500' => array(500, 999999)
            );
            
            if (isset($budget_ranges[$intent['budget']])) {
                $range = $budget_ranges[$intent['budget']];
                if ($price >= $range[0] && $price <= $range[1]) {
                    $factors['price_match'] = 25;
                }
            }
        }
        
        // Category relevance (20% weight)
        if (!empty($intent['category']) && $intent['category'] !== 'unknown') {
            $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'));
            $category_map = array(
                'electronics' => array('electronics', 'computers', 'phones', 'tablets'),
                'fashion' => array('clothing', 'fashion', 'apparel', 'shoes'),
                'home' => array('home-garden', 'furniture', 'decor', 'kitchen'),
                'sports' => array('sports', 'fitness', 'outdoor', 'recreation'),
                'beauty' => array('beauty', 'health', 'cosmetics', 'skincare'),
                'books' => array('books', 'media', 'entertainment')
            );
            
            if (isset($category_map[$intent['category']])) {
                $relevant_categories = $category_map[$intent['category']];
                if (array_intersect($product_categories, $relevant_categories)) {
                    $factors['category_match'] = 20;
                }
            }
        }
        
        // Product rating (15% weight)
        $average_rating = $product->get_average_rating();
        $factors['rating'] = ($average_rating / 5) * 15;
        
        // Sales/popularity (15% weight)
        $total_sales = (int) get_post_meta($product->get_id(), 'total_sales', true);
        $factors['popularity'] = min(($total_sales / 100) * 15, 15); // Cap at 15
        
        // Stock availability (10% weight)
        if ($product->is_in_stock()) {
            $factors['availability'] = 10;
        }
        
        // User preference matching (10% weight)
        if (!empty($user_profile['preferred_categories'])) {
            if (array_intersect($product_categories, $user_profile['preferred_categories'])) {
                $factors['user_preference'] = 10;
            }
        }
        
        // Recent sales velocity (5% weight)
        $recent_sales = $this->get_recent_sales_count($product->get_id(), 30); // Last 30 days
        $factors['sales_velocity'] = min(($recent_sales / 10) * 5, 5); // Cap at 5
        
        // Calculate final score
        $score = array_sum($factors);
        
        return round($score, 2);
    }
    
    /**
     * Get recommendation reasons for a product
     */
    private function get_recommendation_reasons($product, $intent, $user_profile) {
        $reasons = array();
        
        // Price-based reasons
        $price = (float) $product->get_price();
        if (!empty($intent['budget'])) {
            $reasons[] = "💰 Perfect for your budget range";
        }
        
        // Rating-based reasons
        $rating = $product->get_average_rating();
        if ($rating >= 4.5) {
            $reasons[] = "⭐ Highly rated (" . number_format($rating, 1) . "/5 stars)";
        } elseif ($rating >= 4.0) {
            $reasons[] = "⭐ Great customer reviews (" . number_format($rating, 1) . "/5 stars)";
        }
        
        // Popularity reasons