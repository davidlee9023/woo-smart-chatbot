<?php
/**
 * Part 9: JavaScript Completion - Product Card Rendering & Interactions
 * Continuation of JavaScript code for the chatbot
 */

                // Open chat (continued)
                openChat: function() {
                    $("#chatbot-container").show();
                    $("#chatbot-toggle").addClass("active");
                    $("#notification-dot").hide();
                    this.isOpen = true;
                    
                    // Show welcome message if no conversation
                    if ($("#chat-messages .chat-message").length === 0) {
                        setTimeout(() => {
                            this.addMessage("Hello! 👋 I'm your smart shopping assistant powered by AI. I can help you find the perfect products based on your needs and preferences. What are you looking for today?", "bot");
                            this.showQuickSuggestions();
                        }, 300);
                    }
                    
                    // Focus input
                    setTimeout(() => $("#chat-input").focus(), 300);
                },
                
                // Close chat
                closeChat: function() {
                    $("#chatbot-container").hide();
                    $("#chatbot-toggle").removeClass("active");
                    this.isOpen = false;
                    this.isMinimized = false;
                },
                
                // Minimize chat
                minimizeChat: function() {
                    $("#chatbot-container").hide();
                    $("#chatbot-toggle").removeClass("active").addClass("minimized");
                    this.isMinimized = true;
                    this.isOpen = false;
                },
                
                // Restore chat
                restoreChat: function() {
                    $("#chatbot-container").show();
                    $("#chatbot-toggle").removeClass("minimized").addClass("active");
                    this.isMinimized = false;
                    this.isOpen = true;
                },
                
                // Send message
                sendMessage: function() {
                    const message = $("#chat-input").val().trim();
                    if (message === "") return;
                    
                    this.addMessage(message, "user");
                    this.conversationHistory.push({role: "user", content: message});
                    $("#chat-input").val("");
                    
                    // Hide suggestions after first message
                    $("#quick-suggestions").slideUp(300);
                    
                    this.showTypingIndicator();
                    
                    // Send to backend
                    $.ajax({
                        url: smart_chatbot_ajax.ajax_url,
                        type: "POST",
                        data: {
                            action: "smart_chatbot_message",
                            message: message,
                            session_id: smart_chatbot_ajax.session_id,
                            conversation_history: JSON.stringify(this.conversationHistory),
                            nonce: smart_chatbot_ajax.nonce
                        },
                        success: (response) => this.handleMessageResponse(response),
                        error: () => this.handleMessageError()
                    });
                },
                
                // Handle message response
                handleMessageResponse: function(response) {
                    this.hideTypingIndicator();
                    
                    if (response.success) {
                        const data = response.data;
                        
                        if (data.response_type === "product_recommendation") {
                            this.renderProductRecommendations(data);
                        } else if (data.type === "clarification_needed") {
                            this.renderClarificationQuestions(data);
                        } else {
                            this.addMessage(data.response, "bot", data.knowledge_used, data.source);
                        }
                        
                        this.conversationHistory.push({
                            role: "assistant", 
                            content: typeof data.response === "string" ? data.response : "Product recommendations provided"
                        });
                        
                        // Show notification if chat is closed
                        if (!this.isOpen) {
                            this.showNotification();
                        }
                        
                        // Trim conversation history
                        if (this.conversationHistory.length > 20) {
                            this.conversationHistory = this.conversationHistory.slice(-20);
                        }
                    } else {
                        this.addMessage("Sorry, I encountered an error. Please try again! 😅", "bot");
                    }
                },
                
                // Handle message error
                handleMessageError: function() {
                    this.hideTypingIndicator();
                    this.addMessage("I'm having trouble connecting. Please check your internet and try again! 🔄", "bot");
                },
                
                // Render product recommendations
                renderProductRecommendations: function(data) {
                    let html = "";
                    
                    if (data.message) {
                        html += `<div class="product-intro">${data.message}</div>`;
                    }
                    
                    if (data.products && data.products.length > 0) {
                        html += `<div class="products-grid">`;
                        
                        data.products.forEach(product => {
                            html += this.renderProductCard(product);
                        });
                        
                        html += `</div>`;
                        
                        // Add follow-up suggestions
                        if (data.follow_up_suggestions && data.follow_up_suggestions.length > 0) {
                            html += `<div class="follow-up-suggestions" style="margin-top: 15px;">`;
                            data.follow_up_suggestions.forEach(suggestion => {
                                html += `<span class="suggestion-item" data-message="${suggestion.text}" data-action="${suggestion.action}">${suggestion.text}</span>`;
                            });
                            html += `</div>`;
                        }
                    }
                    
                    this.addMessage(html, "bot", true, "product_recommendation");
                },
                
                // Render single product card
                renderProductCard: function(product) {
                    const badges = product.badges.map(badge => 
                        `<span class="product-badge" style="background-color: ${badge.color}">${badge.text}</span>`
                    ).join("");
                    
                    const features = product.key_features.slice(0, 3).map(feature => 
                        `<div class="feature-item">${feature}</div>`
                    ).join("");
                    
                    const reasons = product.recommendation.reasons.slice(0, 3).map(reason => 
                        `<div class="reason-item">${reason}</div>`
                    ).join("");
                    
                    return `
                    <div class="product-card" data-product-id="${product.id}">
                        <div class="product-card-header">
                            <img src="${product.image_url}" alt="${product.name}" class="product-image" loading="lazy">
                            <div class="product-info">
                                <h4 class="product-name">${product.name}</h4>
                                <div class="product-price">
                                    <span class="current-price">${product.price.formatted.current || product.price.formatted}</span>
                                    ${product.price.formatted.original ? `<span class="original-price">${product.price.formatted.original}</span>` : ''}
                                    ${product.price.formatted.savings_percent ? `<span class="savings">Save ${product.price.formatted.savings_percent}%!</span>` : ''}
                                </div>
                                <div class="product-rating">
                                    <span class="rating-stars">${product.rating.stars}</span>
                                    <span>${product.rating.formatted}</span>
                                </div>
                            </div>
                        </div>
                        
                        ${badges ? `<div class="product-badges">${badges}</div>` : ''}
                        
                        <div class="product-description">${product.description}</div>
                        
                        ${features ? `<div class="product-features">${features}</div>` : ''}
                        
                        <div class="product-actions">
                            <a href="${product.permalink}" class="action-btn btn-primary" target="_blank">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 12l2 2 4-4"/>
                                    <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/>
                                    <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/>
                                </svg>
                                View Details
                            </a>
                            <button class="action-btn btn-secondary" onclick="SmartChatbot.addToCart('${product.id}')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="9" cy="21" r="1"/>
                                    <circle cx="20" cy="21" r="1"/>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                </svg>
                                Add to Cart
                            </button>
                        </div>
                        
                        ${reasons ? `
                        <div class="recommendation-reasons">
                            <div class="reason-title">Why I recommend this:</div>
                            ${reasons}
                        </div>` : ''}
                    </div>`;
                },
                
                // Render clarification questions
                renderClarificationQuestions: function(data) {
                    let html = `<div class="clarification-questions">`;
                    html += `<div class="product-intro">${data.message}</div>`;
                    
                    data.questions.forEach((question, questionIndex) => {
                        html += `<div class="question-section" data-question-type="${question.type}">`;
                        html += `<div class="question-title">${question.question}</div>`;
                        html += `<div class="question-options">`;
                        
                        question.options.forEach((option, optionIndex) => {
                            html += `<div class="question-option" data-value="${option.value}" data-question="${questionIndex}">
                                ${option.emoji ? option.emoji + ' ' : ''}${option.label}
                            </div>`;
                        });
                        
                        html += `</div></div>`;
                    });
                    
                    html += `</div>`;
                    
                    this.addMessage(html, "bot", true, "clarification");
                },
                
                // Handle question option click
                handleQuestionOptionClick: function(e) {
                    const $option = $(e.currentTarget);
                    const $section = $option.closest('.question-section');
                    
                    // Remove selection from siblings
                    $section.find('.question-option').removeClass('selected');
                    
                    // Select current option
                    $option.addClass('selected');
                    
                    // Store preference
                    const questionType = $section.data('question-type');
                    const value = $option.data('value');
                    this.userPreferences[questionType] = value;
                    
                    // Check if all questions are answered
                    const totalQuestions = $('.question-section').length;
                    const answeredQuestions = $('.question-section .question-option.selected').length;
                    
                    if (answeredQuestions === totalQuestions) {
                        // All questions answered, send preferences and request recommendations
                        setTimeout(() => {
                            this.sendPreferencesAndGetRecommendations();
                        }, 500);
                    }
                },
                
                // Send preferences and get recommendations
                sendPreferencesAndGetRecommendations: function() {
                    // Build query from preferences
                    let query = "I'm looking for ";
                    
                    if (this.userPreferences.category_selection) {
                        query += this.userPreferences.category_selection + " ";
                    }
                    
                    if (this.userPreferences.budget_selection) {
                        const budgetMap = {
                            'under_50': 'under $50',
                            '50_100': 'between $50-$100',
                            '100_250': 'between $100-$250',
                            '250_500': 'between $250-$500',
                            'over_500': 'over $500'
                        };
                        query += "in the " + budgetMap[this.userPreferences.budget_selection] + " range ";
                    }
                    
                    if (this.userPreferences.purpose_selection) {
                        if (this.userPreferences.purpose_selection === 'gift') {
                            query += "as a gift ";
                        } else if (this.userPreferences.purpose_selection === 'work') {
                            query += "for work ";
                        }
                    }
                    
                    // Send the constructed query
                    $("#chat-input").val(query);
                    this.sendMessage();
                },
                
                // Handle product card click
                handleProductCardClick: function(e) {
                    const $card = $(e.currentTarget);
                    const productId = $card.data('product-id');
                    
                    // Add visual feedback
                    $card.addClass('clicked');
                    setTimeout(() => $card.removeClass('clicked'), 200);
                    
                    // Optional: Ask for more details about this product
                    this.addMessage(`Tell me more about this product`, "user");
                    this.addMessage(`Great choice! This product has excellent reviews and great value for money. Would you like me to find similar products or help you with anything else?`, "bot");
                },
                
                // Handle action button clicks
                handleActionButtonClick: function(e) {
                    const $button = $(e.currentTarget);
                    const action = $button.attr('onclick');
                    
                    // Add loading state
                    $button.addClass('loading').prop('disabled', true);
                    
                    setTimeout(() => {
                        $button.removeClass('loading').prop('disabled', false);
                    }, 1000);
                },
                
                // Add to cart function
                addToCart: function(productId) {
                    // Show feedback message
                    this.addMessage(`Great choice! I'm adding that item to your cart. You can continue shopping or check out when you're ready. Need help finding anything else?`, "bot");
                    
                    // Optional: Trigger actual add to cart via AJAX
                    $.ajax({
                        url: smart_chatbot_ajax.ajax_url,
                        type: "POST",
                        data: {
                            action: "woocommerce_add_to_cart",
                            product_id: productId,
                            nonce: smart_chatbot_ajax.nonce
                        },
                        success: function(response) {
                            console.log('Product added to cart:', response);
                        }
                    });
                },
                
                // Add message to chat
                addMessage: function(content, sender, knowledgeUsed = false, source = null) {
                    const timestamp = new Date().toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"});
                    const avatar = sender === "bot" ? "🤖" : "👤";
                    const knowledgeIndicator = knowledgeUsed ? `<span class="knowledge-indicator" title="From Knowledge Base">${this.getSourceIcon(source)}</span>` : "";
                    
                    const messageHtml = `
                        <div class="chat-message ${sender}" data-timestamp="${timestamp}">
                            <div class="message-avatar">${avatar}</div>
                            <div class="message-content">
                                ${content}
                                ${knowledgeIndicator}
                            </div>
                        </div>
                    `;
                    
                    $("#chat-messages").append(messageHtml);
                    this.scrollToBottom();
                },
                
                // Get source icon
                getSourceIcon: function(source) {
                    const icons = {
                        "knowledge": "📚",
                        "company_info": "🏢", 
                        "order_system": "📦",
                        "product_recommendation": "🛍️",
                        "discount_system": "🎫",
                        "openai": "🤖"
                    };
                    return icons[source] || "💡";
                },
                
                // Show typing indicator
                showTypingIndicator: function() {
                    $("#typing-indicator").slideDown(200);
                    this.scrollToBottom();
                },
                
                // Hide typing indicator
                hideTypingIndicator: function() {
                    $("#typing-indicator").slideUp(200);
                },
                
                // Scroll to bottom
                scrollToBottom: function() {
                    const messages = $("#chat-messages");
                    messages.scrollTop(messages[0].scrollHeight);
                },
                
                // Show notification
                showNotification: function() {
                    $("#notification-dot").show();
                    
                    // Browser notification if permission granted
                    if (Notification.permission === "granted") {
                        new Notification("New message from Smart Assistant", {
                            body: "I've found some great product recommendations for you!",
                            icon: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='0.9em' font-size='90'%3E🤖%3C/text%3E%3C/svg%3E"
                        });
                    }
                },
                
                // Show quick suggestions
                showQuickSuggestions: function() {
                    $("#quick-suggestions").slideDown(300);
                },
                
                // Load user preferences
                loadUserPreferences: function() {
                    const saved = localStorage.getItem('smart_chatbot_preferences');
                    if (saved) {
                        this.userPreferences = JSON.parse(saved);
                    }
                },
                
                // Save user preferences
                saveUserPreferences: function() {
                    localStorage.setItem('smart_chatbot_preferences', JSON.stringify(this.userPreferences));
                    
                    // Also save to backend
                    $.ajax({
                        url: smart_chatbot_ajax.ajax_url,
                        type: "POST",
                        data: {
                            action: "save_user_preferences",
                            session_id: smart_chatbot_ajax.session_id,
                            preferences: JSON.stringify(this.userPreferences),
                            nonce: smart_chatbot_ajax.nonce
                        }
                    });
                }
            };
            
            // Initialize the chatbot
            SmartChatbot.init();
            
            // Make it globally accessible
            window.SmartChatbot = SmartChatbot;
            
            // Request notification permission
            if ("Notification" in window && Notification.permission === "default") {
                setTimeout(() => {
                    Notification.requestPermission();
                }, 5000);
            }
            
            // Auto-hide suggestions after interaction
            let interactionCount = 0;
            $(document).on("click", ".suggestion-item", function() {
                interactionCount++;
                if (interactionCount >= 3) {
                    $("#quick-suggestions").slideUp(300);
                }
            });
            
            // Keyboard shortcuts
            $(document).on("keydown", function(e) {
                // Ctrl/Cmd + K to open chat
                if ((e.ctrlKey || e.metaKey) && e.key === "k") {
                    e.preventDefault();
                    if (!SmartChatbot.isOpen) SmartChatbot.openChat();
                }
                
                // Escape to close chat
                if (e.key === "Escape" && SmartChatbot.isOpen) {
                    SmartChatbot.closeChat();
                }
            });
        });
        </script>';
    }
    
    /**
     * Admin menu and settings
     */
    public function add_admin_menu() {
        add_menu_page(
            'Smart AI Chatbot',
            'Smart Chatbot',
            'manage_options',
            'woo-smart-chatbot',
            array($this, 'admin_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    public function admin_init() {
        register_setting('woo_smart_chatbot_settings', 'woo_smart_chatbot_api_key');
        register_setting('woo_smart_chatbot_settings', 'woo_smart_chatbot_admin_email');
        register_setting('woo_smart_chatbot_settings', 'woo_smart_chatbot_enabled');
        register_setting('woo_smart_chatbot_settings', 'woo_smart_chatbot_position');
        register_setting('woo_smart_chatbot_settings', 'woo_smart_chatbot_theme');
    }
    
    public function admin_page() {
        global $wpdb;
        
        // Get statistics
        $total_conversations = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM {$wpdb->prefix}smart_chatbot_conversations");
        $total_recommendations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smart_chatbot_recommendations");
        $avg_confidence = $wpdb->get_var("SELECT AVG(confidence_level) FROM {$wpdb->prefix}smart_chatbot_conversations WHERE confidence_level IS NOT NULL");
        $total_knowledge = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smart_chatbot_knowledge");
        
        ?>
        <div class="wrap">
            <h1>🤖 Smart AI Chatbot - Advanced Product Recommendations</h1>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #0073aa; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold;"><?php echo $total_conversations; ?></div>
                    <div>Total Conversations</div>
                </div>
                <div style="background: #00a32a; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold;"><?php echo $total_recommendations; ?></div>
                    <div>Product Recommendations</div>
                </div>
                <div style="background: #ff6900; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold;"><?php echo round($avg_confidence * 100); ?>%</div>
                    <div>Average Confidence</div>
                </div>
                <div style="background: #9c27b0; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold;"><?php echo $total_knowledge; ?></div>
                    <div>Knowledge Items</div>
                </div>
            </div>
            
            <form method="post" action="options.php" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <?php settings_fields("woo_smart_chatbot_settings"); ?>
                <h3>⚙️ Settings</h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="password" name="woo_smart_chatbot_api_key" 
                                   value="<?php echo esc_attr(get_option("woo_smart_chatbot_api_key")); ?>" 
                                   style="width: 400px;" placeholder="sk-...">
                            <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Admin Email</th>
                        <td>
                            <input type="email" name="woo_smart_chatbot_admin_email" 
                                   value="<?php echo esc_attr(get_option("woo_smart_chatbot_admin_email", get_option("admin_email"))); ?>" 
                                   style="width: 300px;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Chatbot</th>
                        <td>
                            <select name="woo_smart_chatbot_enabled">
                                <option value="yes" <?php selected(get_option("woo_smart_chatbot_enabled", "yes"), "yes"); ?>>Yes</option>
                                <option value="no" <?php selected(get_option("woo_smart_chatbot_enabled"), "no"); ?>>No</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Widget Position</th>
                        <td>
                            <select name="woo_smart_chatbot_position">
                                <option value="bottom-right" <?php selected(get_option("woo_smart_chatbot_position", "bottom-right"), "bottom-right"); ?>>Bottom Right</option>
                                <option value="bottom-left" <?php selected(get_option("woo_smart_chatbot_position"), "bottom-left"); ?>>Bottom Left</option>
                                <option value="top-right" <?php selected(get_option("woo_smart_chatbot_position"), "top-right"); ?>>Top Right</option>
                                <option value="top-left" <?php selected(get_option("woo_smart_chatbot_position"), "top-left"); ?>>Top Left</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Theme</th>
                        <td>
                            <select name="woo_smart_chatbot_theme">
                                <option value="modern" <?php selected(get_option("woo_smart_chatbot_theme", "modern"), "modern"); ?>>Modern (Default)</option>
                                <option value="dark" <?php selected(get_option("woo_smart_chatbot_theme"), "dark"); ?>>Dark Mode</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3>🎯 Smart Product Recommendation Features</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4>🧠 AI-Powered Features:</h4>
                        <ul>
                            <li>✅ <strong>Intent Analysis</strong> - Understands user needs</li>
                            <li>✅ <strong>Personalized Recommendations</strong> - Learns preferences</li>
                            <li>✅ <strong>Smart Questioning</strong> - Interactive clarification</li>
                            <li>✅ <strong>Product Scoring</strong> - Multi-factor ranking</li>
                            <li>✅ <strong>Visual Product Cards</strong> - Beautiful displays</li>
                            <li>✅ <strong>Conversation Context</strong> - Remembers chat history</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4>🛍️ Product Features:</h4>
                        <ul>
                            <li>✅ <strong>3+ Product Cards</strong> - Rich visual display</li>
                            <li>✅ <strong>Product Images</strong> - High-quality thumbnails</li>
                            <li>✅ <strong>Pricing Info</strong> - Current/sale/savings</li>
                            <li>✅ <strong>Ratings & Reviews</strong> - Customer feedback</li>
                            <li>✅ <strong>Smart Badges</strong> - Hot picks, bestsellers</li>
                            <li>✅ <strong>Recommendation Reasons</strong> - Why suggested</li>
                        </ul>
                    </div>
                </div>
                
                <div style="background: #e8f4fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3; margin-top: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #1976d2;">🚀 Your Smart Chatbot is Ready!</h4>
                    <p style="margin: 0; color: #424242;">
                        The advanced product recommendation system is now active on your site. Customers can get personalized 
                        product suggestions with beautiful visual cards, smart questioning, and AI-powered recommendations!
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new WooSmartChatbotAdvanced();

?>