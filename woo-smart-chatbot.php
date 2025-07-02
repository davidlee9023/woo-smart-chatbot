<?php
/**
 * Plugin Name: WooCommerce Smart AI Chatbot - Advanced Product Recommendations
 * Plugin URI: https://stylemz.com/smart-chatbot-recommendations
 * Description: AI-powered chatbot with intelligent product recommendations, visual cards, and personalized shopping assistant
 * Version: 4.0.0
 * Author: Lee Sangwoo
 * Text Domain: woo-smart-chatbot
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Smart AI Chatbot</strong> requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

define('WOO_SMART_CHATBOT_VERSION', '4.0.0');
define('WOO_SMART_CHATBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_SMART_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Smart Chatbot Class
 */
class WooSmartChatbotAdvanced {
    
    private $openai_api_key;
    private $admin_email;
    private $knowledge_base = array();
    private $conversation_context = array();
    private $product_recommendation_engine;
    
    public function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
        $this->openai_api_key = get_option('woo_smart_chatbot_api_key');
        $this->admin_email = get_option('woo_smart_chatbot_admin_email', get_option('admin_email'));
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_chatbot'));
        
        // AJAX handlers
        add_action('wp_ajax_smart_chatbot_message', array($this, 'handle_chatbot_message'));
        add_action('wp_ajax_nopriv_smart_chatbot_message', array($this, 'handle_chatbot_message'));
        add_action('wp_ajax_get_product_recommendations', array($this, 'handle_product_recommendations'));
        add_action('wp_ajax_nopriv_get_product_recommendations', array($this, 'handle_product_recommendations'));
        add_action('wp_ajax_save_user_preferences', array($this, 'save_user_preferences'));
        add_action('wp_ajax_nopriv_save_user_preferences', array($this, 'save_user_preferences'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Database setup
        register_activation_hook(__FILE__, array($this, 'create_tables'));
        register_deactivation_hook(__FILE__, array($this, 'cleanup_plugin'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once WOO_SMART_CHATBOT_PLUGIN_DIR . 'includes/class-product-recommendation-engine.php';
        require_once WOO_SMART_CHATBOT_PLUGIN_DIR . 'includes/class-intent-analyzer.php';
        require_once WOO_SMART_CHATBOT_PLUGIN_DIR . 'includes/class-user-profiler.php';
        require_once WOO_SMART_CHATBOT_PLUGIN_DIR . 'includes/class-product-formatter.php';
        
        $this->product_recommendation_engine = new SmartProductRecommendationEngine();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('woo-smart-chatbot', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Load knowledge base
        $this->load_complete_knowledge_base();
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Enhanced knowledge base table
        $knowledge_table = $wpdb->prefix . 'smart_chatbot_knowledge';
        $knowledge_sql = "CREATE TABLE $knowledge_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            category varchar(50) NOT NULL,
            subcategory varchar(100) DEFAULT NULL,
            question text NOT NULL,
            answer longtext NOT NULL,
            keywords text,
            context_tags text,
            confidence_score decimal(3,2) DEFAULT 1.00,
            source varchar(50) DEFAULT 'manual',
            language varchar(10) DEFAULT 'en',
            priority int(3) DEFAULT 5,
            last_used datetime DEFAULT NULL,
            usage_count int(11) DEFAULT 0,
            effectiveness_score decimal(3,2) DEFAULT 1.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY confidence_score (confidence_score),
            KEY usage_count (usage_count),
            KEY language (language),
            FULLTEXT(question, answer, keywords, context_tags)
        ) $charset_collate;";
        
        // Enhanced conversations table
        $conversations_table = $wpdb->prefix . 'smart_chatbot_conversations';
        $conversations_sql = "CREATE TABLE $conversations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(32) NOT NULL,
            user_id int(11) DEFAULT NULL,
            user_message text NOT NULL,
            bot_response longtext NOT NULL,
            intent varchar(100),
            entities longtext,
            context_data longtext,
            satisfaction_score int(1) DEFAULT NULL,
            response_time decimal(4,2) DEFAULT NULL,
            knowledge_source varchar(50),
            confidence_level decimal(3,2) DEFAULT NULL,
            user_ip varchar(45),
            user_agent text,
            language varchar(10) DEFAULT 'en',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY intent (intent),
            KEY created_at (created_at),
            KEY language (language),
            FULLTEXT(user_message, bot_response)
        ) $charset_collate;";
        
        // User profiles table
        $user_profiles_table = $wpdb->prefix . 'smart_chatbot_user_profiles';
        $user_profiles_sql = "CREATE TABLE $user_profiles_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(32) NOT NULL,
            user_id int(11) DEFAULT NULL,
            preferences longtext,
            purchase_history longtext,
            browsing_behavior longtext,
            interaction_patterns longtext,
            preferred_categories text,
            budget_range varchar(50),
            communication_style varchar(50) DEFAULT 'friendly',
            language_preference varchar(10) DEFAULT 'en',
            timezone varchar(50),
            last_interaction datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY last_interaction (last_interaction)
        ) $charset_collate;";
        
        // Product recommendations log
        $recommendations_table = $wpdb->prefix . 'smart_chatbot_recommendations';
        $recommendations_sql = "CREATE TABLE $recommendations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(32) NOT NULL,
            user_id int(11) DEFAULT NULL,
            original_query text NOT NULL,
            intent_analysis longtext,
            recommended_products longtext,
            user_feedback varchar(20),
            conversion_result tinyint(1) DEFAULT 0,
            recommendation_score decimal(3,2),
            algorithm_version varchar(10) DEFAULT '1.0',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY conversion_result (conversion_result),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Company information table
        $company_info_table = $wpdb->prefix . 'smart_chatbot_company_info';
        $company_info_sql = "CREATE TABLE $company_info_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            info_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            tags text,
            language varchar(10) DEFAULT 'en',
            priority int(3) DEFAULT 5,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY info_type (info_type),
            KEY priority (priority),
            KEY language (language),
            FULLTEXT(title, content, tags)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($knowledge_sql);
        dbDelta($conversations_sql);
        dbDelta($user_profiles_sql);
        dbDelta($recommendations_sql);
        dbDelta($company_info_sql);
        
        // Insert default data
        $this->insert_default_knowledge();
    }
    
    /**
     * Insert default knowledge and company information
     */
    private function insert_default_knowledge() {
        global $wpdb;
        
        // Default knowledge base
        $default_knowledge = array(
            array(
                'category' => 'product_inquiry',
                'subcategory' => 'general',
                'question' => 'Can you recommend products for me?',
                'answer' => 'I\'d love to help you find the perfect products! Let me ask a few questions to give you personalized recommendations.',
                'keywords' => 'recommend products suggestion help find',
                'context_tags' => 'product_recommendation,shopping_help'
            ),
            array(
                'category' => 'product_inquiry',
                'subcategory' => 'budget',
                'question' => 'What\'s your budget range?',
                'answer' => 'Great! Knowing your budget helps me recommend the best products for you. What price range are you comfortable with?',
                'keywords' => 'budget price range cost money',
                'context_tags' => 'budget_inquiry,price_range'
            ),
            array(
                'category' => 'shipping',
                'subcategory' => 'policy',
                'question' => 'What is your shipping policy?',
                'answer' => 'We offer free shipping on orders over $50. Standard shipping takes 2-3 business days, express shipping takes 1-2 business days.',
                'keywords' => 'shipping policy delivery free standard express',
                'context_tags' => 'policy,delivery,cost'
            ),
            array(
                'category' => 'returns',
                'subcategory' => 'policy', 
                'question' => 'What is your return policy?',
                'answer' => 'You can return items within 30 days of purchase for a full refund. Items must be in original condition with tags attached.',
                'keywords' => 'return policy refund 30 days original condition',
                'context_tags' => 'policy,returns,refund'
            )
        );
        
        foreach ($default_knowledge as $knowledge) {
            $wpdb->insert($wpdb->prefix . 'smart_chatbot_knowledge', $knowledge);
        }
        
        // Default company information
        $default_company_info = array(
            array(
                'info_type' => 'about_us',
                'title' => 'Our Story',
                'content' => 'We are a leading e-commerce company dedicated to providing high-quality products and exceptional customer service. Founded in 2020, we have grown to serve thousands of customers worldwide.',
                'tags' => 'company story history mission',
                'priority' => 10
            ),
            array(
                'info_type' => 'about_us',
                'title' => 'Our Mission',
                'content' => 'Our mission is to make online shopping easy, affordable, and enjoyable for everyone. We believe in quality products, fair prices, and outstanding customer service.',
                'tags' => 'mission values goals',
                'priority' => 9
            ),
            array(
                'info_type' => 'policies',
                'title' => 'Privacy Policy',
                'content' => 'We take your privacy seriously. We collect only necessary information to process your orders and improve your shopping experience. We never sell or share your personal information with third parties.',
                'tags' => 'privacy policy data protection',
                'priority' => 8
            )
        );
        
        foreach ($default_company_info as $info) {
            $wpdb->insert($wpdb->prefix . 'smart_chatbot_company_info', $info);
        }
    }
    
    /**
     * Load complete knowledge base
     */
    private function load_complete_knowledge_base() {
        global $wpdb;
        
        $knowledge = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}smart_chatbot_knowledge ORDER BY confidence_score DESC, usage_count DESC");
        $company_info = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}smart_chatbot_company_info WHERE active = 1 ORDER BY priority DESC");
        
        $this->knowledge_base = array(
            'knowledge' => $knowledge,
            'company_info' => $company_info
        );
    }
    
    /**
     * Get or create session ID
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        if (!isset($_SESSION['smart_chatbot_session_id'])) {
            $_SESSION['smart_chatbot_session_id'] = md5(uniqid(rand(), true));
        }
        return $_SESSION['smart_chatbot_session_id'];
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * Plugin cleanup on deactivation
     */
    public function cleanup_plugin() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('smart_chatbot_cleanup_old_data');
    }
}