<?php
/**
 * AI Chat Plugin - Production Configuration
 * 
 * This file contains production-ready configuration settings
 * for optimal performance and security.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Production Configuration Class
 */
class AI_Chat_Production_Config {
    
    /**
     * Initialize production settings
     */
    public static function init() {
        // Disable debug mode
        add_filter('ai_chat_debug_mode', '__return_false');
        
        // Set production cache settings
        add_filter('ai_chat_cache_duration', array(__CLASS__, 'set_cache_duration'));
        
        // Optimize API timeouts
        add_filter('ai_chat_api_timeout', array(__CLASS__, 'set_api_timeout'));
        
        // Set production error handling
        add_filter('ai_chat_error_reporting', array(__CLASS__, 'set_error_reporting'));
        
        // Optimize database queries
        add_action('init', array(__CLASS__, 'optimize_database'));
    }
    
    /**
     * Set cache duration for production (5 minutes)
     */
    public static function set_cache_duration($duration) {
        return 5 * MINUTE_IN_SECONDS;
    }
    
    /**
     * Set API timeout for production (15 seconds)
     */
    public static function set_api_timeout($timeout) {
        return 15;
    }
    
    /**
     * Disable verbose error reporting in production
     */
    public static function set_error_reporting($enabled) {
        return false;
    }
    
    /**
     * Optimize database settings
     */
    public static function optimize_database() {
        // Clean up old conversations (older than 30 days)
        if (!wp_next_scheduled('ai_chat_cleanup_old_conversations')) {
            wp_schedule_event(time(), 'daily', 'ai_chat_cleanup_old_conversations');
        }
    }
    
    /**
     * Get production-ready settings
     */
    public static function get_production_settings() {
        return array(
            'debug_mode' => false,
            'cache_duration' => 5 * MINUTE_IN_SECONDS,
            'api_timeout' => 15,
            'max_message_length' => 2000000,
            'cleanup_interval' => 'daily',
            'conversation_retention_days' => 30,
            'error_logging' => false,
            'performance_monitoring' => true
        );
    }
}

// Initialize production config if not in development
if (!defined('AI_CHAT_DEVELOPMENT') || !AI_CHAT_DEVELOPMENT) {
    AI_Chat_Production_Config::init();
}
