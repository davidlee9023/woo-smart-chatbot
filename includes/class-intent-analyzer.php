<?php
/**
 * Part 3: Intent Analyzer & User Profiler
 * File: includes/class-intent-analyzer.php
 */

class SmartIntentAnalyzer {
    
    private $intent_patterns = array();
    
    public function __construct() {
        $this->init_intent_patterns();
    }
    
    /**
     * Initialize intent recognition patterns
     */
    private function init_intent_patterns() {
        $this->intent_patterns = array(
            'category' => array(
                'electronics' => array('phone', 'computer', 'laptop', 'tablet', 'electronics', 'tech', 'gadget', 'smartphone', 'pc', 'mac', 'iphone', 'android'),
                'fashion' => array('clothes', 'clothing', 'shirt', 'dress', 'pants', 'shoes', 'fashion', 'style', 'wear', 'outfit', 'jacket', 'jeans'),
                'home' => array('home', 'house', 'furniture', 'decor', 'kitchen', 'bedroom', 'living room', 'garden', 'appliance'),
                'sports' => array('sports', 'fitness', 'exercise', 'gym', 'outdoor', 'bike', 'run', 'workout', 'athletic'),
                'beauty' => array('beauty', 'makeup', 'skincare', 'cosmetics', 'health', 'wellness', 'care', 'lotion', 'cream'),
                'books' => array('book', 'read', 'novel', 'study', 'education', 'learning', 'magazine', 'ebook')
            ),
            'budget' => array(
                'under_50' => array('cheap', 'budget', 'affordable', 'under 50', 'less than 50', 'inexpensive'),
                '50_100' => array('mid-range', 'moderate', '50 to 100', 'around 75', 'between 50 and 100'),
                '100_250' => array('good quality', 'decent price', '100 to 250', 'around 150', 'mid-high'),
                '250_500' => array('premium', 'high quality', '250 to 500', 'expensive', 'luxury'),
                'over_500' => array('top tier', 'best', 'luxury', 'premium', 'over 500', 'high-end')
            ),
            'purpose' => array(
                'gift' => array('gift', 'present', 'birthday', 'anniversary', 'surprise', 'someone else'),
                'personal' => array('myself', 'for me', 'personal', 'own use'),
                'work' => array('work', 'office', 'business', 'professional', 'job'),
                'hobby' => array('hobby', 'fun', 'entertainment', 'leisure', 'passion')
            ),
            'urgency' => array(
                'urgent' => array('urgent', 'asap', 'immediately', 'rush', 'quick', 'fast', 'today'),
                'normal' => array('normal', 'regular', 'when possible', 'no rush'),
                'flexible' => array('flexible', 'anytime', 'no hurry', 'whenever')
            ),
            'quality' => array(
                'premium' => array('best', 'top quality', 'premium', 'luxury', 'high-end', 'professional'),
                'good' => array('good quality', 'decent', 'reliable', 'solid'),
                'budget' => array('basic', 'simple', 'budget', 'cheap', 'affordable')
            )
        );
    }
    
    /**
     * Analyze user intent from message
     */
    public function analyze_intent($message, $conversation_history = array()) {
        $intent = array(
            'category' => 'unknown',
            'budget' => 'unknown',
            'purpose' => 'unknown',
            'urgency' => 'normal',
            'quality' => 'good',
            'keywords' => array(),
            'specific_items' => array(),
            'brand_preference' => array(),
            'color_preference' => array(),
            'size_preference' => array(),
            'confidence_score' => 0
        );
        
        $message_lower = strtolower($message);
        $words = preg_split('/\s+/', $message_lower);
        
        // Extract keywords
        $intent['keywords'] = $this->extract_meaningful_keywords($words);
        
        // Analyze each intent category
        foreach ($this->intent_patterns as $type => $patterns) {
            $intent[$type] = $this->match_intent_pattern($message_lower, $patterns, $intent[$type]);
        }
        
        // Extract specific product mentions
        $intent['specific_items'] = $this->extract_specific_products($message_lower);
        
        // Extract brand preferences
        $intent['brand_preference'] = $this->extract_brands($message_lower);
        
        // Extract color preferences
        $intent['color_preference'] = $this->extract_colors($message_lower);
        
        // Extract size information
        $intent['size_preference'] = $this->extract_sizes($message_lower);
        
        // Calculate confidence score
        $intent['confidence_score'] = $this->calculate_intent_confidence($intent);
        
        // Consider conversation history for context
        if (!empty($conversation_history)) {
            $intent = $this->enhance_with_conversation_context($intent, $conversation_history);
        }
        
        return $intent;
    }
    
    /**
     * Match intent patterns
     */
    private function match_intent_pattern($message, $patterns, $default) {
        foreach ($patterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    return $category;
                }
            }
        }
        return $default;
    }
    
    /**
     * Extract meaningful keywords
     */
    private function extract_meaningful_keywords($words) {
        $stop_words = array('i', 'need', 'want', 'looking', 'for', 'can', 'you', 'help', 'me', 'find', 'the', 'a', 'an', 'and', 'or');
        $keywords = array();
        
        foreach ($words as $word) {
            $word = trim($word, '.,!?');
            if (strlen($word) > 2 && !in_array($word, $stop_words)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Extract specific product mentions
     */
    private function extract_specific_products($message) {
        $specific_products = array();
        
        // Common product patterns
        $product_patterns = array(
            'iphone' => array('iphone', 'iphone 15', 'iphone 14', 'iphone 13'),
            'laptop' => array('macbook', 'thinkpad', 'dell laptop', 'hp laptop'),
            'shoes' => array('nike', 'adidas', 'sneakers', 'running shoes'),
            'watch' => array('apple watch', 'smartwatch', 'rolex')
        );
        
        foreach ($product_patterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($message, $pattern) !== false) {
                    $specific_products[] = $pattern;
                }
            }
        }
        
        return $specific_products;
    }
    
    /**
     * Extract brand preferences
     */
    private function extract_brands($message) {
        $brands = array();
        $brand_list = array('apple', 'samsung', 'nike', 'adidas', 'sony', 'dell', 'hp', 'microsoft', 'google', 'amazon');
        
        foreach ($brand_list as $brand) {
            if (strpos($message, $brand) !== false) {
                $brands[] = $brand;
            }
        }
        
        return $brands;
    }
    
    /**
     * Extract color preferences
     */
    private function extract_colors($message) {
        $colors = array();
        $color_list = array('black', 'white', 'red', 'blue', 'green', 'yellow', 'pink', 'purple', 'orange', 'brown', 'gray', 'silver', 'gold');
        
        foreach ($color_list as $color) {
            if (strpos($message, $color) !== false) {
                $colors[] = $color;
            }
        }
        
        return $colors;
    }
    
    /**
     * Extract size information
     */
    private function extract_sizes($message) {
        $sizes = array();
        
        // Clothing sizes
        if (preg_match('/\b(xs|s|m|l|xl|xxl|xxxl)\b/i', $message, $matches)) {
            $sizes['clothing'] = strtoupper($matches[1]);
        }
        
        // Shoe sizes
        if (preg_match('/\bsize (\d+(?:\.\d+)?)\b/i', $message, $matches)) {
            $sizes['shoe'] = $matches[1];
        }
        
        // Screen sizes
        if (preg_match('/(\d+)[\s-]?inch/i', $message, $matches)) {
            $sizes['screen'] = $matches[1] . ' inch';
        }
        
        return $sizes;
    }
    
    /**
     * Calculate intent confidence score
     */
    private function calculate_intent_confidence($intent) {
        $score = 0;
        $max_score = 100;
        
        // Category confidence
        if ($intent['category'] !== 'unknown') $score += 25;
        
        // Budget confidence
        if ($intent['budget'] !== 'unknown') $score += 20;
        
        // Purpose confidence
        if ($intent['purpose'] !== 'unknown') $score += 15;
        
        // Keywords presence
        if (count($intent['keywords']) > 0) $score += 15;
        
        // Specific items mentioned
        if (count($intent['specific_items']) > 0) $score += 15;
        
        // Brand or color preferences
        if (count($intent['brand_preference']) > 0 || count($intent['color_preference']) > 0) $score += 10;
        
        return min($score, $max_score);
    }
    
    /**
     * Enhance intent with conversation context
     */
    private function enhance_with_conversation_context($intent, $conversation_history) {
        // Look for context clues in previous messages
        foreach ($conversation_history as $msg) {
            if ($msg['role'] === 'user') {
                $prev_intent = $this->analyze_intent($msg['content']);
                
                // If current intent is missing category but previous had one
                if ($intent['category'] === 'unknown' && $prev_intent['category'] !== 'unknown') {
                    $intent['category'] = $prev_intent['category'];
                }
                
                // Same for budget
                if ($intent['budget'] === 'unknown' && $prev_intent['budget'] !== 'unknown') {
                    $intent['budget'] = $prev_intent['budget'];
                }
            }
        }
        
        return $intent;
    }
}
