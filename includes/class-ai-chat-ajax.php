<?php
/**
 * AJAX functionality for AI Chat Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Ajax {
    
    public function __construct() {
        // Frontend AJAX actions
        add_action('wp_ajax_ai_chat_send_message', array($this, 'send_message'));
        add_action('wp_ajax_nopriv_ai_chat_send_message', array($this, 'send_message'));
        add_action('wp_ajax_get_conversation_messages', array($this, 'get_conversation_messages'));

        add_action('wp_ajax_ai_chat_get_platform_url', array($this, 'get_platform_url'));

        // Data sources AJAX actions
        add_action('wp_ajax_add_data_source', array($this, 'add_data_source'));
        add_action('wp_ajax_delete_data_source', array($this, 'delete_data_source'));
        add_action('wp_ajax_clear_all_data_sources', array($this, 'clear_all_data_sources'));
        add_action('wp_ajax_nopriv_ai_chat_get_platform_url', array($this, 'get_platform_url'));
        
        // Admin AJAX actions
        add_action('wp_ajax_ai_chat_test_api', array($this, 'test_api'));
        add_action('wp_ajax_ai_chat_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_ai_chat_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_ai_chat_clear_logs', array($this, 'clear_logs'));

        add_action('wp_ajax_ai_chat_get_conversation_details', array($this, 'get_conversation_details'));
        add_action('wp_ajax_ai_chat_export_chat_data', array($this, 'export_chat_data'));

        // 添加修復 enabled_platforms 的 AJAX 處理
        add_action('wp_ajax_fix_enabled_platforms', array($this, 'fix_enabled_platforms'));


    }
    
    /**
     * Send message to AI
     */
    public function send_message() {
        error_log('AI Chat Debug: ===== SEND MESSAGE AJAX CALLED =====');
        error_log('AI Chat Debug: POST data: ' . print_r($_POST, true));

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_frontend_nonce')) {
            error_log('AI Chat Debug: Nonce verification failed');
            wp_die(__('Security check failed', 'ai-chat'));
        }

        error_log('AI Chat Debug: Nonce verification passed');

        $message = sanitize_text_field($_POST['message'] ?? '');
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');

        error_log('AI Chat Debug: Message: ' . $message);
        error_log('AI Chat Debug: Conversation ID: ' . $conversation_id);

        if (empty($message)) {
            error_log('AI Chat Debug: Empty message received');
            wp_send_json_error(__('Message cannot be empty', 'ai-chat'));
        }
        
        try {
            $ai_api = new AI_Chat_API();
            $response = $ai_api->send_message($message, $conversation_id);

            error_log('AI Chat AJAX: API response = ' . print_r($response, true));

            if ($response['success']) {
                wp_send_json_success(array(
                    'message' => $response['message'],
                    'conversation_id' => $response['conversation_id']
                ));
            } else {
                // Even if AI fails, we should still save the user message
                $user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

                if (empty($conversation_id)) {
                    $conversation_id = AI_Chat_Database::start_conversation($user_ip, $user_agent);
                }

                if ($conversation_id) {
                    AI_Chat_Database::save_message($conversation_id, 'user', $message);
                }

                wp_send_json_error($response['error'] ?? __('Failed to get AI response', 'ai-chat'));
            }
            
        } catch (Exception $e) {
            // 提供更友好的錯誤信息
            $error_message = $e->getMessage();

            if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'cURL error 28') !== false) {
                $user_message = 'The AI service is currently experiencing delays. Please try again in a moment.';
            } elseif (strpos($error_message, 'API key') !== false || strpos($error_message, 'authentication') !== false) {
                $user_message = 'There seems to be a configuration issue. Please contact support.';
            } elseif (strpos($error_message, 'network') !== false || strpos($error_message, 'connection') !== false) {
                $user_message = 'Unable to connect to AI service. Please check your internet connection and try again.';
            } else {
                $user_message = 'Sorry, I\'m temporarily unable to respond. Please try again later.';
            }

            // 檢查是否為調試模式
            if (defined('WP_DEBUG') && WP_DEBUG) {
                wp_send_json_error('Debug: ' . $error_message);
            } else {
                wp_send_json_error($user_message);
            }
        }
    }
    
    /**
     * Get platform URL for redirect
     */
    public function get_platform_url() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_frontend_nonce')) {
            wp_send_json_error(__('Security check failed', 'ai-chat'));
        }

        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $settings = get_option('ai_chat_settings', array());

        $url = $this->generate_platform_url($platform, $settings);

        if ($url) {
            wp_send_json_success(array('url' => $url));
        } else {
            wp_send_json_error(__('Platform not configured', 'ai-chat'));
        }
    }
    
    /**
     * Generate platform URL
     */
    private function generate_platform_url($platform, $settings) {
        $site_name = get_bloginfo('name');
        $current_url = wp_get_referer() ?: home_url();
        $message = sprintf(__('Hello! I\'m visiting %s and would like to chat.', 'ai-chat'), $site_name);
        
        switch ($platform) {
            case 'whatsapp':
                $phone = $settings['whatsapp_phone'] ?? '';
                if ($phone) {
                    $phone = preg_replace('/[^0-9+]/', '', $phone);
                    return 'https://wa.me/' . ltrim($phone, '+') . '?text=' . urlencode($message);
                }
                break;
                
            case 'facebook':
                // Use the specific Messenger URL provided
                $messenger_url = $settings['facebook_messenger_url'] ?? '';
                if ($messenger_url) {
                    return $messenger_url;
                }
                // Fallback to the specific URL requested
                return 'https://m.me/113269170212688';
                break;
                
            case 'line':
                $channel_id = $settings['line_channel_id'] ?? '';
                if ($channel_id) {
                    return 'https://line.me/R/ti/p/' . $channel_id;
                }
                break;
                
            case 'wechat':
                // WeChat shows QR code instead of URL
                $qr_url = $settings['wechat_qr_url'] ?? '';
                if ($qr_url) {
                    return 'qr:' . $qr_url; // Special prefix to indicate QR code display
                }
                break;
                
            case 'qq':
                $qq_number = $settings['qq_number'] ?? '';
                if ($qq_number) {
                    return 'tencent://message/?uin=' . $qq_number . '&Site=&Menu=yes';
                }
                break;
                  case 'instagram':
                $username = $settings['instagram_username'] ?? '';
                if ($username) {
                    return 'https://ig.me/m/' . $username;
                }
                break;
                
            case 'telegram':
                $username = $settings['telegram_username'] ?? '';
                if ($username) {
                    return 'https://t.me/' . $username;
                }
                break;
                
            case 'discord':
                $invite_code = $settings['discord_invite'] ?? '';
                if ($invite_code) {
                    return 'https://discord.gg/' . $invite_code;
                }
                break;

            case 'email':
                $email = $settings['contact_email'] ?? '';
                $subject = $settings['email_subject'] ?? 'Website Inquiry';
                if ($email) {
                    return 'mailto:' . $email . '?subject=' . urlencode($subject);
                }
                break;

            case 'phone':
                $phone = $settings['contact_phone'] ?? '';
                if ($phone) {
                    // Clean phone number and ensure it starts with tel:
                    $phone = preg_replace('/[^0-9+]/', '', $phone);
                    return 'tel:' . $phone;
                }
                break;
                
            case 'slack':
                $workspace_url = $settings['slack_workspace'] ?? '';
                if ($workspace_url) {
                    return rtrim($workspace_url, '/') . '/messages';
                }
                break;
                
            case 'teams':
                $team_url = $settings['teams_url'] ?? '';
                if ($team_url) {
                    return $team_url;
                }
                break;
        }
        
        return false;
    }
    
    /**
     * Test AI API connection (Admin only)
     */
    public function test_api() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-chat'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_nonce')) {
            wp_die(__('Security check failed', 'ai-chat'));
        }
        
        $api_provider = sanitize_text_field($_POST['api_provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $api_url = esc_url_raw($_POST['api_url'] ?? '');
        $model = sanitize_text_field($_POST['model'] ?? '');
        
        try {
            $ai_api = new AI_Chat_API();
            $result = $ai_api->test_connection($api_provider, $api_key, $api_url, $model);
            
            if ($result['success']) {
                wp_send_json_success(__('API connection successful!', 'ai-chat'));
            } else {
                wp_send_json_error($result['error'] ?? __('API connection failed', 'ai-chat'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(__('Connection test failed: ', 'ai-chat') . $e->getMessage());
        }
    }
    
    /**
     * Save settings via AJAX (Admin only)
     */
    public function save_settings() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-chat'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_nonce')) {
            wp_die(__('Security check failed', 'ai-chat'));
        }
        
        $settings = $_POST['settings'] ?? array();
        
        // Sanitize settings
        $admin = new AI_Chat_Admin();
        $sanitized_settings = $admin->sanitize_settings($settings);
        
        // Update settings
        $updated = update_option('ai_chat_settings', $sanitized_settings);
        
        if ($updated) {
            wp_send_json_success(__('Settings saved successfully!', 'ai-chat'));
        } else {
            wp_send_json_error(__('Failed to save settings', 'ai-chat'));
        }
    }
    
    /**
     * Get conversation details for admin viewing
     */
    public function get_conversation_details() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-chat'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_nonce')) {
            wp_send_json_error(__('Security check failed', 'ai-chat'));
        }
        
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        
        if (empty($conversation_id)) {
            wp_send_json_error(__('Invalid conversation ID', 'ai-chat'));
        }
          $database = new AI_Chat_Database();
        $conversation_data = $database->get_conversation_details($conversation_id);
        
        if (!$conversation_data) {
            wp_send_json_error(__('Conversation not found', 'ai-chat'));
        }
        
        // Format the data for display
        $formatted_messages = array();
        foreach ($conversation_data['messages'] as $message) {
            $formatted_messages[] = array(
                'sender_type' => $message['sender_type'],
                'content' => $message['message_content'],
                'timestamp' => date('Y-m-d H:i:s', strtotime($message['created_at'])),
                'metadata' => json_decode($message['metadata'], true)
            );
        }
        
        wp_send_json_success(array(
            'conversation' => $conversation_data['conversation'],
            'messages' => $formatted_messages
        ));
    }
    
    /**
     * Export chat data (CSV format)
     */
    public function export_chat_data() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-chat'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'ai_chat_export_nonce')) {
            wp_die(__('Security check failed', 'ai-chat'));
        }
        
        $database = new AI_Chat_Database();
        $conversations = $database->get_conversations(1000, 0); // Get up to 1000 conversations
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ai-chat-export-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'Conversation ID',
            'Platform',
            'Started At',
            'Last Activity',
            'Status',
            'Message Count',
            'User Messages',
            'AI Messages'
        ));
        
        foreach ($conversations as $conversation) {
            $message_count = $database->get_conversation_message_count($conversation['id']);
            $messages = $database->get_conversation_details($conversation['id'])['messages'];
            
            $user_messages = count(array_filter($messages, function($msg) {
                return $msg['sender_type'] === 'user';
            }));
            
            $ai_messages = count(array_filter($messages, function($msg) {
                return $msg['sender_type'] === 'ai';
            }));
            
            fputcsv($output, array(
                $conversation['id'],
                $conversation['platform'],
                $conversation['started_at'],
                $conversation['last_activity'],
                $conversation['status'],
                $message_count,
                $user_messages,
                $ai_messages
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get conversation messages for viewing
     */
    public function get_conversation_messages() {
        check_ajax_referer('get_conversation_messages', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $conversation_id = sanitize_text_field($_POST['conversation_id']);

        $database = new AI_Chat_Database();

        $conversation_data = $database->get_conversation_details($conversation_id);

        if (!$conversation_data) {
            wp_send_json_error(array('message' => '對話不存在'));
            return;
        }
        
        $conversation = $conversation_data['conversation'];
        $messages = $conversation_data['messages'];
        
        ob_start();
        ?>
        <div class="conversation-header" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <h4 style="margin: 0 0 10px 0;"><?php printf(__('對話 #%d', 'ai-chat'), $conversation->id); ?></h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 14px; color: #666;">
                <div>
                    <strong><?php _e('開始時間：', 'ai-chat'); ?></strong><?php echo date('Y-m-d H:i:s', strtotime($conversation->created_at)); ?><br>
                    <strong><?php _e('最後活動：', 'ai-chat'); ?></strong><?php echo human_time_diff(strtotime($conversation->updated_at), current_time('timestamp')); ?> <?php _e('前', 'ai-chat'); ?>
                </div>
                <div>
                    <strong><?php _e('平台：', 'ai-chat'); ?></strong><?php echo esc_html($conversation->platform ?? 'ai-chat'); ?><br>
                    <strong><?php _e('用戶IP：', 'ai-chat'); ?></strong><code><?php echo esc_html($conversation->user_ip); ?></code>
                </div>
            </div>
        </div>
        
        <div class="conversation-messages" style="max-height: 400px; overflow-y: auto;">
            <?php if (empty($messages)): ?>
                <p style="text-align: center; color: #666; padding: 20px;"><?php _e('此對話尚無訊息記錄', 'ai-chat'); ?></p>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message-item" style="margin-bottom: 15px; padding: 12px; border-radius: 8px; <?php echo $message->sender === 'user' ? 'background: #f0f8ff; border-left: 3px solid #007cba;' : 'background: #f8f9fa; border-left: 3px solid #28a745;'; ?>">
                        <div class="message-meta" style="font-size: 12px; color: #666; margin-bottom: 5px;">
                            <strong><?php echo $message->sender === 'user' ? '👤 ' . __('用戶', 'ai-chat') : '🤖 ' . __('AI助手', 'ai-chat'); ?></strong>
                            <span style="float: right;"><?php echo date('H:i:s', strtotime($message->created_at)); ?></span>
                        </div>
                        <div class="message-content" style="line-height: 1.4;">
                            <?php echo nl2br(esc_html($message->message)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="conversation-footer" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 14px;">
            <?php printf(__('共 %d 則訊息', 'ai-chat'), count($messages)); ?>
        </div>
        <?php
        
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }

    /**
     * Test API connection for debugging
     */
    public function test_connection() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-chat'));
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_test_nonce')) {
            wp_die(__('Security check failed', 'ai-chat'));
        }

        try {
            $settings = get_option('ai_chat_settings', array());
            $ai_api = new AI_Chat_API();

            $api_provider = $settings['ai_api_provider'] ?? 'openrouter';
            $api_key = $settings['ai_api_key'] ?? '';
            $api_url = $settings['ai_api_url'] ?? '';
            $model = $settings['ai_model'] ?? 'openai/gpt-3.5-turbo';

            if (empty($api_key)) {
                wp_send_json_error(__('API 金鑰未設定', 'ai-chat'));
                return;
            }

            $result = $ai_api->test_connection($api_provider, $api_key, $api_url, $model);

            if ($result['success']) {
                wp_send_json_success(array('message' => $result['message']));
            } else {
                wp_send_json_error($result['error']);
            }

        } catch (Exception $e) {
            error_log('AI Chat Test Connection Error: ' . $e->getMessage());
            wp_send_json_error(__('連接測試失敗: ', 'ai-chat') . $e->getMessage());
        }
    }



    /**
     * Fix enabled_platforms setting
     */
    public function fix_enabled_platforms() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-chat'));
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'fix_enabled_platforms')) {
            wp_die(__('Security check failed', 'ai-chat'));
        }

        try {
            // Get current settings
            $settings = get_option('ai_chat_settings', array());

            // Ensure enabled_platforms includes ai-chat
            $settings['enabled_platforms'] = array('ai-chat');

            // Update settings
            $updated = update_option('ai_chat_settings', $settings);

            if ($updated) {
                wp_send_json_success(__('enabled_platforms 已修復，ai-chat 平台已啟用', 'ai-chat'));
            } else {
                wp_send_json_error(__('設定更新失敗', 'ai-chat'));
            }

        } catch (Exception $e) {
            wp_send_json_error('修復失敗: ' . $e->getMessage());
        }
    }

    /**
     * Add data source
     */
    public function add_data_source() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_nonce')) {
            wp_send_json_error(__('Security check failed', 'ai-chat'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-chat'));
        }

        $url = sanitize_url($_POST['url'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');

        if (empty($url)) {
            wp_send_json_error(__('URL cannot be empty', 'ai-chat'));
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(__('Invalid URL format', 'ai-chat'));
        }

        $database = new AI_Chat_Database();
        $result = $database->add_data_source($url, $title);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Data source added successfully!', 'ai-chat'),
                'id' => $result
            ));
        } else {
            wp_send_json_error(__('Failed to add data source. URL may already exist.', 'ai-chat'));
        }
    }

    /**
     * Delete data source
     */
    public function delete_data_source() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_nonce')) {
            wp_send_json_error(__('Security check failed', 'ai-chat'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-chat'));
        }

        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            wp_send_json_error(__('Invalid data source ID', 'ai-chat'));
        }

        $database = new AI_Chat_Database();
        $result = $database->delete_data_source($id);

        if ($result) {
            wp_send_json_success(array('message' => __('Data source deleted successfully!', 'ai-chat')));
        } else {
            wp_send_json_error(__('Failed to delete data source', 'ai-chat'));
        }
    }

    /**
     * Clear all data sources
     */
    public function clear_all_data_sources() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_nonce')) {
            wp_send_json_error(__('Security check failed', 'ai-chat'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-chat'));
        }

        $database = new AI_Chat_Database();
        $count = $database->clear_all_data_sources();

        wp_send_json_success(array(
            'message' => sprintf(__('Cleared %d data sources successfully!', 'ai-chat'), $count)
        ));
    }
}
