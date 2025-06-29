<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package AI_Chat
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include the activator class
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-chat-activator.php';

// Run uninstall process
AI_Chat_Activator::uninstall();
