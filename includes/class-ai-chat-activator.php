<?php
/**
 * AI Chat Plugin Activator/Deactivator
 * 
 * @package AI_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Activation and Deactivation Handler
 */
class AI_Chat_Activator {
      /**
     * Plugin activation hook
     */
    public static function activate() {
        // Create database tables
        self::create_database_tables();
        
        // Set default options
        self::set_default_options();
        
        // Create default chat widget
        self::create_default_widget();
        
        // Schedule cleanup task
        AI_Chat_Database::schedule_cleanup();
        
        // Set plugin version
        add_option('ai_chat_version', AI_CHAT_VERSION);
        
        // Set activation timestamp
        add_option('ai_chat_activated_time', current_time('timestamp'));
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set flag for showing welcome notice
        set_transient('ai_chat_activation_notice', true, 30);
    }
    
    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        // Clear scheduled events
        AI_Chat_Database::unschedule_cleanup();
        wp_clear_scheduled_hook('ai_chat_analytics_summary');
        
        // Clear any cached data
        wp_cache_flush();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Remove activation notice
        delete_transient('ai_chat_activation_notice');
    }
    
    /**
     * Plugin uninstall hook (static method for uninstall.php)
     */
    public static function uninstall() {
        // Get uninstall preferences
        $settings = get_option('ai_chat_settings', array());
        $remove_data = isset($settings['remove_data_on_uninstall']) ? $settings['remove_data_on_uninstall'] : false;
        
        if ($remove_data) {
            self::remove_all_data();
        }
    }
    
    /**
     * Create database tables
     */
    private static function create_database_tables() {
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-ai-chat-database.php';
        AI_Chat_Database::create_tables();
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_options = array(
            // Platform settings
            'enabled_platforms' => array('ai-chat'),
            'platform_order' => array('ai-chat', 'whatsapp', 'facebook', 'line', 'wechat', 'qq', 'instagram'),
            
            // Display settings
            'chat_position' => 'bottom-right',
            'chat_size' => 'medium',
            'chat_color' => '#007cba',
            'display_on_mobile' => true,
            'animation_enabled' => true,
            'show_platform_icons' => true,
            
            // AI settings
            'ai_api_provider' => 'openrouter',
            'ai_model' => 'openai/gpt-3.5-turbo',
            'ai_system_prompt' => '',
            'ai_temperature' => 0.7,
            'ai_max_tokens' => 500,
            
            // Platform configurations
            'whatsapp_phone' => '',
            'facebook_page_id' => '',
            'line_channel_id' => '',
            'wechat_account' => '',
            'qq_number' => '',
            'instagram_username' => '',
            
            // Advanced settings
            'conversation_timeout' => 30, // minutes
            'max_conversation_length' => 50,
            'enable_analytics' => true,
            'remove_data_on_uninstall' => false,
            
            // Security settings
            'rate_limit_enabled' => true,
            'rate_limit_messages' => 10,
            'rate_limit_period' => 60, // seconds
            
            // Language settings
            'default_language' => get_locale(),
            'auto_detect_language' => false
        );
        
        add_option('ai_chat_settings', $default_options);
    }
    
    /**
     * Create default chat widget configuration
     */
    private static function create_default_widget() {
        $default_widget = array(
            'greeting_message' => __('Hello! How can I help you today?', 'ai-chat'),
            'welcome_messages' => array(
                __('Hello! Welcome to %s. How can I help you today?', 'ai-chat'),
                __('Hi there! I\'m the AI assistant for %s. What would you like to know?', 'ai-chat'),
                __('Welcome! I\'m here to help you with any questions about %s.', 'ai-chat')
            ),
            'offline_message' => __('We\'re currently offline. Please leave a message and we\'ll get back to you soon!', 'ai-chat'),
            'placeholder_text' => __('Type your message...', 'ai-chat'),
            'button_labels' => array(
                'send' => __('Send', 'ai-chat'),
                'minimize' => __('Minimize', 'ai-chat'),
                'maximize' => __('Maximize', 'ai-chat'),
                'close' => __('Close', 'ai-chat')
            )
        );
        
        add_option('ai_chat_widget', $default_widget);
    }
    
    /**
     * Remove all plugin data
     */
    private static function remove_all_data() {
        global $wpdb;
        
        // Remove database tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_chat_conversations");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_chat_messages");
        
        // Remove all plugin options
        delete_option('ai_chat_settings');
        delete_option('ai_chat_widget');
        delete_option('ai_chat_version');
        delete_option('ai_chat_activated_time');
        
        // Remove transients
        delete_transient('ai_chat_activation_notice');
        delete_transient('ai_chat_analytics_cache');
        
        // Remove user meta related to plugin
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ai_chat_%'");
        
        // Clear any cached data
        wp_cache_flush();
    }
    
    /**
     * Check if plugin needs database update
     */
    public static function needs_database_update() {
        $current_version = get_option('ai_chat_version', '0.0.0');
        return version_compare($current_version, AI_CHAT_VERSION, '<');
    }
    
    /**
     * Update database if needed
     */
    public static function maybe_update_database() {
        if (self::needs_database_update()) {
            self::update_database();
        }
    }
    
    /**
     * Update database schema
     */
    private static function update_database() {
        // Create or update tables
        self::create_database_tables();
        
        // Update version
        update_option('ai_chat_version', AI_CHAT_VERSION);
        
        // Set flag for showing update notice
        set_transient('ai_chat_update_notice', true, 30);
    }
}
