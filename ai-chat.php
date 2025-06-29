<?php
/**
 * Plugin Name: AI 多平台聊天
 * Plugin URI: https://hugoshih.eu.org/ai-chat
 * Description: 整合 AI 智能聊天和多平台聊天功能的綜合性插件，支援 Facebook Messenger、WhatsApp、LINE、WeChat、AI 聊天、QQ、Instagram 等平台。
 * Version: 1.1.0
 * Author: Hugo Shih
 * Author URI: https://hugoshih.eu.org
 * Text Domain: ai-chat
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.2
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_CHAT_VERSION', '1.1.0');
define('AI_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AI_CHAT_PLUGIN_FILE', __FILE__);

// Include critical classes needed for activation
require_once AI_CHAT_PLUGIN_PATH . 'includes/class-ai-chat-activator.php';

/**
 * Main AI Chat Plugin Class
 */
class AI_Chat_Plugin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_init', array($this, 'check_version'));
        
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array('AI_Chat_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('AI_Chat_Activator', 'deactivate'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Include required files
        $this->includes();
        
        // Only initialize if all required classes exist
        if (class_exists('AI_Chat_Admin') && is_admin()) {
            new AI_Chat_Admin();
        }
        
        if (class_exists('AI_Chat_Frontend')) {
            new AI_Chat_Frontend();
        }
        
        if (class_exists('AI_Chat_Ajax')) {
            new AI_Chat_Ajax();
        }
        
        // Initialize optional components only if they exist
        
        // Hook cleanup action
        add_action('ai_chat_cleanup', array('AI_Chat_Database', 'cleanup_old_data'));
    }    /**
     * Include required files
     */
    private function includes() {
        // Include core files that are required (activator already included above)
        $core_files = array(
            'includes/class-ai-chat-admin.php',
            'includes/class-ai-chat-frontend.php',
            'includes/class-ai-chat-ajax.php',
            'includes/class-ai-chat-database.php',
            'includes/class-ai-chat-api.php'
        );
        
        // Include optional files that exist
        $optional_files = array();
        
        // Load core files (required)
        foreach ($core_files as $file) {
            $file_path = AI_CHAT_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Load optional files (if they exist)
        foreach ($optional_files as $file) {
            $file_path = AI_CHAT_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('ai-chat', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Check plugin version and update if needed
     */
    public function check_version() {
        if (class_exists('AI_Chat_Activator')) {
            AI_Chat_Activator::maybe_update_database();
        }
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Show activation notice
        if (get_transient('ai_chat_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('AI Multi-Platform Chat plugin has been activated successfully! <a href="' . admin_url('admin.php?page=ai-chat') . '">Configure your chat settings</a> to get started.', 'ai-chat'); ?></p>
            </div>
            <?php
            delete_transient('ai_chat_activation_notice');
        }
        
        // Show update notice
        if (get_transient('ai_chat_update_notice')) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><?php _e('AI Multi-Platform Chat plugin has been updated successfully!', 'ai-chat'); ?></p>
            </div>
            <?php
            delete_transient('ai_chat_update_notice');
        }
    }

    /**
     * Add settings link to plugin page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ai-chat') . '">' . __('設定', 'ai-chat') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin with error handling
function ai_chat_init_plugin() {
    try {
        new AI_Chat_Plugin();
    } catch (Exception $e) {
        if (is_admin()) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>AI Chat Plugin Error:</strong> ' . esc_html($e->getMessage());
                echo '</p></div>';
            });
        }
    }
}

// Initialize the plugin
ai_chat_init_plugin();
