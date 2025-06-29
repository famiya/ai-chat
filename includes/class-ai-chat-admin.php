<?php
/**
 * Admin functionality for AI Chat Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
    }
      /**
     * Add admin menu
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('聊天設置', 'ai-chat'),
            __('聊天設置', 'ai-chat'),
            'manage_options',
            'ai-chat',
            array($this, 'admin_page'),
            'dashicons-format-chat',
            30
        );

        // Sub-menu for chat history
        add_submenu_page(
            'ai-chat',
            __('聊天歷史', 'ai-chat'),
            __('聊天歷史', 'ai-chat'),
            'manage_options',
            'ai-chat-history',
            array($this, 'chat_history_page')
        );

        // Sub-menu for AI data sources
        add_submenu_page(
            'ai-chat',
            __('AI 數據源', 'ai-chat'),
            __('AI 數據源', 'ai-chat'),
            'manage_options',
            'ai-chat-data-sources',
            array($this, 'data_sources_page')
        );


    }
      /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Load scripts on all AI Chat admin pages
        $ai_chat_pages = array(
            'toplevel_page_ai-chat',
            'ai-chat_page_ai-chat-history',
            'ai-chat_page_ai-chat-data-sources',
            'ai-chat_page_ai-chat-analytics',
            'ai-chat_page_ai-chat-settings',
            'ai-chat_page_ai-chat-security',
            'ai-chat_page_ai-chat-languages'
        );
        
        if (!in_array($hook, $ai_chat_pages)) {
            return;
        }

        // Enqueue WordPress media library for image uploads
        wp_enqueue_media();

        wp_enqueue_script('ai-chat-admin', AI_CHAT_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'media-upload', 'media-views'), AI_CHAT_VERSION, true);
        wp_enqueue_style('ai-chat-admin', AI_CHAT_PLUGIN_URL . 'assets/css/admin.css', array(), AI_CHAT_VERSION);
        
        // Localize script
        wp_localize_script('ai-chat-admin', 'aiChatAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chat_nonce'),
            'strings' => array(
                'saving' => __('儲存中...', 'ai-chat'),
                'saved' => __('設置已儲存！', 'ai-chat'),
                'error' => __('儲存設置時發生錯誤', 'ai-chat'),
                'user' => __('用戶', 'ai-chat'),
                'ai' => __('AI', 'ai-chat'),
                'no_messages' => __('暫無訊息記錄', 'ai-chat')
            )
        ));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ai_chat_settings_group', 'ai_chat_settings', array($this, 'sanitize_settings'));
    }
      /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['enabled_platforms']) && is_array($input['enabled_platforms'])) {
            $sanitized['enabled_platforms'] = array_map('sanitize_text_field', $input['enabled_platforms']);
        } else {
            // Ensure at least ai-chat is enabled by default
            $sanitized['enabled_platforms'] = array('ai-chat');
        }
        
        // If no platforms are selected, default to ai-chat
        if (empty($sanitized['enabled_platforms'])) {
            $sanitized['enabled_platforms'] = array('ai-chat');
        }
          $sanitized['chat_position'] = sanitize_text_field($input['chat_position'] ?? 'bottom-right');
        $sanitized['chat_size'] = sanitize_text_field($input['chat_size'] ?? 'medium');
        $sanitized['chat_color'] = sanitize_hex_color($input['chat_color'] ?? '#007cba');
        $sanitized['bubble_mode'] = !empty($input['bubble_mode']);
        
        // AI Settings
        $sanitized['ai_api_provider'] = sanitize_text_field($input['ai_api_provider'] ?? 'openrouter');
        $sanitized['ai_api_key'] = sanitize_text_field($input['ai_api_key'] ?? '');
        $sanitized['ai_api_url'] = esc_url_raw($input['ai_api_url'] ?? '');
        $sanitized['ai_model'] = sanitize_text_field($input['ai_model'] ?? 'openai/gpt-3.5-turbo');
        $sanitized['ai_system_prompt'] = sanitize_textarea_field($input['ai_system_prompt'] ?? '');

        // AI Performance Settings
        $sanitized['max_system_prompt_length'] = intval($input['max_system_prompt_length'] ?? 100000);
        $sanitized['max_posts_count'] = intval($input['max_posts_count'] ?? 50);
        $sanitized['max_products_count'] = intval($input['max_products_count'] ?? 50);
        $sanitized['enable_parallel_processing'] = !empty($input['enable_parallel_processing']);
          // Platform Settings - Individual platform configurations
        $sanitized['whatsapp_phone'] = sanitize_text_field($input['whatsapp_phone'] ?? '');
        
        // LINE settings
        $sanitized['line_username'] = sanitize_text_field($input['line_username'] ?? '');
        
        // Telegram settings
        $sanitized['telegram_username'] = sanitize_text_field($input['telegram_username'] ?? '');
        
        // Instagram settings  
        $sanitized['instagram_username'] = sanitize_text_field($input['instagram_username'] ?? '');
        
        // Twitter settings
        $sanitized['twitter_username'] = sanitize_text_field($input['twitter_username'] ?? '');
        
        // QQ settings
        $sanitized['qq_number'] = sanitize_text_field($input['qq_number'] ?? '');
        
        // Facebook settings
        $sanitized['facebook_page_name'] = sanitize_text_field($input['facebook_page_name'] ?? '');
        $sanitized['facebook_messenger_url'] = esc_url_raw($input['facebook_messenger_url'] ?? '');
        
        // Email settings
        $sanitized['contact_email'] = sanitize_email($input['contact_email'] ?? '');
        $sanitized['email_subject'] = sanitize_text_field($input['email_subject'] ?? __('Website Inquiry', 'ai-chat'));
        
        // Phone settings
        $sanitized['contact_phone'] = sanitize_text_field($input['contact_phone'] ?? '');
        
        // WeChat specific settings
        $sanitized['wechat_account'] = sanitize_text_field($input['wechat_account'] ?? '');
        $sanitized['wechat_qr_url'] = esc_url_raw($input['wechat_qr_url'] ?? '');
        $sanitized['whatsapp_message'] = sanitize_textarea_field($input['whatsapp_message'] ?? '');

        // Generic platform URLs
        $sanitized['wechat_url'] = esc_url_raw($input['wechat_url'] ?? '');
        $sanitized['discord_url'] = esc_url_raw($input['discord_url'] ?? '');
        $sanitized['slack_url'] = esc_url_raw($input['slack_url'] ?? '');
        $sanitized['teams_url'] = esc_url_raw($input['teams_url'] ?? '');
        $sanitized['skype_url'] = esc_url_raw($input['skype_url'] ?? '');
        $sanitized['viber_url'] = esc_url_raw($input['viber_url'] ?? '');
        
        // Display Settings
        $sanitized['button_position'] = sanitize_text_field($input['button_position'] ?? 'bottom-right');
        // 統一使用 chat_position
        if (!empty($input['button_position'])) {
            $sanitized['chat_position'] = $sanitized['button_position'];
        }
        // 支持兩種字段名稱以保持兼容性
        $sanitized['button_color'] = sanitize_hex_color($input['button_color'] ?? $input['chat_color'] ?? '#007cba');
        $sanitized['chat_color'] = sanitize_hex_color($input['chat_color'] ?? $input['button_color'] ?? '#007cba');
        $sanitized['button_size'] = sanitize_text_field($input['button_size'] ?? 'medium');
        $sanitized['bubble_animation_duration'] = intval($input['bubble_animation_duration'] ?? 300);
        $sanitized['auto_hide_bubbles'] = intval($input['auto_hide_bubbles'] ?? 0);

        // Distance Settings
        $sanitized['bottom_distance'] = intval($input['bottom_distance'] ?? 20);
        $sanitized['top_distance'] = intval($input['top_distance'] ?? 20);

        // Display Options
        $sanitized['display_on_mobile'] = isset($input['display_on_mobile']);
        $sanitized['animation_enabled'] = isset($input['animation_enabled']);
        $sanitized['show_notification_badge'] = isset($input['show_notification_badge']);

        return $sanitized;
    }
      /**
     * Admin page content
    /**
     * Main admin page
     */
    public function admin_page() {
        // Auto-fix missing database tables
        $missing_tables = AI_Chat_Database::force_create_tables();
        if (!empty($missing_tables)) {
            echo '<div class="notice notice-success"><p>' . sprintf(__('已自動創建缺失的數據表：%s', 'ai-chat'), implode(', ', $missing_tables)) . '</p></div>';
        }
          $settings = get_option('ai_chat_settings', array());
        ?>
        <div class="wrap ai-chat-admin">
            <!-- 美化的頁面標題 -->
            <div class="page-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 50px; border-radius: 20px; margin-bottom: 30px; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; opacity: 0.3;"></div>
                <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
                <div style="position: relative; z-index: 2;">
                    <h1 style="margin: 0 0 15px 0; font-size: 36px; font-weight: 300; color: white;">🤖 <?php _e('AI 多平台聊天設置', 'ai-chat'); ?></h1>
                    <p style="margin: 0; font-size: 18px; opacity: 0.9; font-weight: 300;"><?php _e('整合多個聊天平台並提供智能對話服務', 'ai-chat'); ?></p>
                </div>
            </div>



            <!-- 插件介紹區域 -->
            <div class="plugin-intro-section" style="background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; border: 1px solid #e1e5e9; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: center;">
                    <div>
                        <h3 style="margin: 0 0 15px 0; color: #333; font-size: 20px; display: flex; align-items: center; gap: 10px;">
                            <span style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px;">🤖</span>
                            <?php _e('關於 AI 多平台聊天插件', 'ai-chat'); ?>
                        </h3>
                        <p style="margin: 0 0 15px 0; color: #666; line-height: 1.6; font-size: 15px;">
                            <?php _e('整合 AI 智能聊天和多平台聊天功能，讓訪客能夠獲得即時回答或連接到社交媒體平台。支持 GPT-4、Claude、Gemini 等 AI 模型，以及 WhatsApp、LINE、Telegram 等 15+ 社交平台。', 'ai-chat'); ?>
                        </p>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <div style="background: #f0f8ff; padding: 8px 15px; border-radius: 20px; font-size: 13px; color: #007cba; font-weight: 500;">
                                🤖 <?php _e('AI 智能聊天', 'ai-chat'); ?>
                            </div>
                            <div style="background: #f0fff0; padding: 8px 15px; border-radius: 20px; font-size: 13px; color: #28a745; font-weight: 500;">
                                📱 <?php _e('15+ 社交平台', 'ai-chat'); ?>
                            </div>
                            <div style="background: #fff5f5; padding: 8px 15px; border-radius: 20px; font-size: 13px; color: #dc3545; font-weight: 500;">
                                🌍 <?php _e('多語言支持', 'ai-chat'); ?>
                            </div>
                            <div style="background: #f8f9fa; padding: 8px 15px; border-radius: 20px; font-size: 13px; color: #6c757d; font-weight: 500;">
                                📊 <?php _e('對話記錄', 'ai-chat'); ?>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 20px; border-radius: 15px; color: white;">
                            <h4 style="margin: 0 0 10px 0; font-size: 16px;">💬 <?php _e('需要幫助？', 'ai-chat'); ?></h4>
                            <p style="margin: 0 0 15px 0; font-size: 13px; opacity: 0.9;">
                                <?php _e('查看文檔或聯繫支援', 'ai-chat'); ?>
                            </p>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <a href="<?php echo admin_url('admin.php?page=ai-chat-history'); ?>" class="button" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 12px;">
                                    📊 <?php _e('查看聊天歷史', 'ai-chat'); ?>
                                </a>
                                <a href="https://hugoshih.eu.org/ai-chat/" target="_blank" class="button" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 12px;">
                                    📖 <?php _e('查看文檔', 'ai-chat'); ?>
                                </a>
                                <a href="mailto:blog@hugoshih.eu.org" class="button" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 12px;">
                                    📧 <?php _e('聯繫支援', 'ai-chat'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" action="options.php" id="ai-chat-settings-form">
                <?php
                settings_fields('ai_chat_settings_group');
                do_settings_sections('ai_chat_settings_group');
                ?>
                
                <div class="ai-chat-admin-container" style="max-width: 95%; margin: 20px auto 0 auto; display: grid; grid-template-columns: 30% 70%; gap: 25px; align-items: start;">
                    <!-- 左側：平台選擇 (30%) -->
                    <div class="platform-selection-panel">
                        <div class="card modern-card">
                            <div class="card-header">
                                <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                                    <span style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px;">🎯</span>
                                    <?php _e('選擇聊天平台', 'ai-chat'); ?>
                                </h2>
                                <p style="margin: 10px 0 0 0; color: #666;"><?php _e('勾選您要啟用的聊天平台，相應的設置將出現在右側', 'ai-chat'); ?></p>
                            </div>
                            
                            <?php $this->render_platform_selection_list($settings); ?>
                            
                            <!-- 快捷選擇按鈕 -->
                            <div class="platform-quick-actions" style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 12px;">
                                <h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('快捷選擇', 'ai-chat'); ?></h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <button type="button" class="button quick-select-btn" id="select-popular" data-platforms="ai-chat,whatsapp,line">🔥 <?php _e('熱門組合', 'ai-chat'); ?></button>
                                    <button type="button" class="button quick-select-btn" id="select-social" data-platforms="whatsapp,line,telegram,facebook">📱 <?php _e('社交組合', 'ai-chat'); ?></button>
                                    <button type="button" class="button quick-select-btn" id="select-all">✅ <?php _e('全選', 'ai-chat'); ?></button>
                                    <button type="button" class="button quick-select-btn" id="clear-all">❌ <?php _e('清除全部', 'ai-chat'); ?></button>
                                </div>
                            </div>
                            
                            <div id="platform-feedback" style="margin-top: 15px; padding: 15px; background: #fff; border: 2px solid #ddd; border-radius: 10px; text-align: center; font-weight: 500;"></div>
                        </div>
                        
                        <!-- <?php _e('Frontend Preview', 'ai-chat'); ?> -->
                        <div class="card modern-card" style="margin-top: 20px;">
                            <h3 style="display: flex; align-items: center; gap: 8px; margin: 0 0 15px 0;">
                                <span style="font-size: 20px;">🔍</span> <?php _e('前台預覽', 'ai-chat'); ?>
                            </h3>
                            <p style="color: #666; margin-bottom: 15px;"><?php _e('選擇平台查看效果：', 'ai-chat'); ?></p>
                            <a href="<?php echo home_url(); ?>" target="_blank" class="button button-secondary preview-btn" style="width: 100%; text-align: center; padding: 12px; font-size: 16px; background: linear-gradient(135deg, #4facfe, #00f2fe); color: white; border: none; border-radius: 8px; transition: all 0.3s ease;">
                                <?php _e('查看前台效果', 'ai-chat'); ?> →
                            </a>
                            <div class="description" style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px; font-size: 13px; color: #666; line-height: 1.5;">
                                <strong><?php _e('顯示邏輯：', 'ai-chat'); ?></strong><br>
                                • <?php _e('單一平台：直接顯示聊天窗口', 'ai-chat'); ?><br>
                                • <?php _e('多個平台：顯示選擇菜單', 'ai-chat'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- 右側：平台設定與顯示設定 (70%) -->
                    <div class="platform-settings-panel" style="width: 100%; min-height: 600px;">
                        <div class="card modern-card" style="width: 100%; box-sizing: border-box;">
                            <div class="card-header" style="padding: 20px; border-bottom: 1px solid #e1e5e9;">
                                <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 20px;">
                                    <span style="background: linear-gradient(135deg, #f093fb, #f5576c); color: white; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px;">⚙️</span>
                                    <?php _e('平台設置', 'ai-chat'); ?>
                                </h2>
                                <p style="margin: 8px 0 0 50px; color: #666; font-size: 14px;"><?php _e('配置選中平台的連接信息和顯示選項', 'ai-chat'); ?></p>
                            </div>

                            <div id="no-platform-selected" class="platform-config-placeholder" style="text-align: center; padding: 40px 20px; color: #666;">
                                <div style="background: linear-gradient(135deg, #667eea, #764ba2); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto; box-shadow: 0 8px 20px rgba(102,126,234,0.3);">
                                    <span style="font-size: 24px; color: white;">⚙️</span>
                                </div>
                                <h3 style="margin: 0 0 8px 0; color: #333; font-size: 16px;"><?php _e('請選擇要配置的平台', 'ai-chat'); ?></h3>
                                <p style="font-size: 14px; margin: 0; line-height: 1.5;"><?php _e('在左側勾選平台後，相應的設置將在此處顯示', 'ai-chat'); ?></p>
                            </div>

                            <!-- 動態平台設定區域 -->
                            <div id="platform-configs-container">
                                <?php $this->render_all_platform_configs($settings); ?>
                            </div>

                            <!-- 顯示設定區域 -->
                            <div class="display-settings-section" style="border-top: 1px solid #e0e0e0; margin-top: 25px; padding-top: 25px;">
                                <div class="section-header" style="margin-bottom: 20px;">
                                    <h3 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 18px;">
                                        <span style="background: linear-gradient(135deg, #fa709a, #fee140); color: white; width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px;">🎨</span>
                                        <?php _e('顯示設置', 'ai-chat'); ?>
                                    </h3>
                                </div>
                                <?php $this->render_display_settings($settings); ?>
                            </div>

                            <!-- 儲存按鈕區域 -->
                            <div class="save-settings-section" style="border-top: 1px solid #e0e0e0; margin-top: 25px; padding-top: 20px; text-align: center;">
                                <button type="submit" id="ai-chat-save-settings-btn" class="button button-primary button-large" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none; padding: 12px 30px; font-size: 16px; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 15px rgba(102,126,234,0.3); transition: all 0.3s ease;">
                                    <span style="margin-right: 8px;">💾</span>
                                    <?php _e('儲存所有設置', 'ai-chat'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>


                

            </form>
        </div>
          <?php $this->render_admin_styles_and_scripts(); ?>
        <?php
    }
    
    /**
     * Render platform selection list (left side)
     */
    private function render_platform_selection_list($settings) {
        $platforms = array(
            'ai-chat' => array(
                'name' => __('AI 聊天', 'ai-chat'),
                'icon' => 'fas fa-robot',
                'color' => '#007cba'
            ),
            'whatsapp' => array(
                'name' => __('WhatsApp', 'ai-chat'),
                'icon' => 'fab fa-whatsapp',
                'color' => '#25d366'
            ),
            'line' => array(
                'name' => __('LINE', 'ai-chat'),
                'icon' => 'fab fa-line',
                'color' => '#00c300'
            ),
            'telegram' => array(
                'name' => __('Telegram', 'ai-chat'),
                'icon' => 'fab fa-telegram-plane',
                'color' => '#0088cc'
            ),
            'wechat' => array(
                'name' => __('WeChat 微信', 'ai-chat'),
                'icon' => 'fab fa-weixin',
                'color' => '#7bb32e'
            ),
            'qq' => array(
                'name' => __('QQ', 'ai-chat'),
                'icon' => 'fab fa-qq',
                'color' => '#eb1923'
            ),
            'facebook' => array(
                'name' => __('Facebook Messenger', 'ai-chat'),
                'icon' => 'fab fa-facebook-messenger',
                'color' => '#0084ff'
            ),
            'instagram' => array(
                'name' => __('Instagram', 'ai-chat'),
                'icon' => 'fab fa-instagram',
                'color' => '#e4405f'
            ),
            'twitter' => array(
                'name' => __('Twitter/X', 'ai-chat'),
                'icon' => 'fab fa-x-twitter',
                'color' => '#1da1f2'
            ),
            'discord' => array(
                'name' => __('Discord', 'ai-chat'),
                'icon' => 'fab fa-discord',
                'color' => '#7289da'
            ),
            'slack' => array(
                'name' => __('Slack', 'ai-chat'),
                'icon' => 'fab fa-slack',
                'color' => '#4a154b'
            ),
            'teams' => array(
                'name' => __('Microsoft Teams', 'ai-chat'),
                'icon' => 'fab fa-microsoft',
                'color' => '#6264a7'
            ),
            'skype' => array(
                'name' => __('Skype', 'ai-chat'),
                'icon' => 'fab fa-skype',
                'color' => '#00aff0'
            ),
            'viber' => array(
                'name' => __('Viber', 'ai-chat'),
                'icon' => 'fab fa-viber',
                'color' => '#665cac'
            ),
            'email' => array(
                'name' => __('Email 電子郵件', 'ai-chat'),
                'icon' => 'fas fa-envelope',
                'color' => '#dc3545'
            ),
            'phone' => array(
                'name' => __('Phone 電話', 'ai-chat'),
                'icon' => 'fas fa-phone',
                'color' => '#28a745'
            )
        );
        
        $enabled_platforms = $settings['enabled_platforms'] ?? array('ai-chat');
        
        echo '<div class="platform-selection-list">';
        
        foreach ($platforms as $key => $platform) {
            $checked = in_array($key, $enabled_platforms);
            
            echo '<label class="platform-selection-item' . ($checked ? ' active' : '') . '" data-platform="' . esc_attr($key) . '" style="--platform-color: ' . esc_attr($platform['color']) . '">';
            echo '<input type="checkbox" name="ai_chat_settings[enabled_platforms][]" value="' . esc_attr($key) . '" ' . checked($checked, true, false) . '>';
            echo '<span class="platform-icon"><i class="' . esc_attr($platform['icon']) . '"></i></span>';
            echo '<span class="platform-name">' . esc_html($platform['name']) . '</span>';
            echo '</label>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render all platform configs (right side)
     */
    private function render_all_platform_configs($settings) {
        // AI Chat 設定
        echo '<div class="platform-config" data-platform="ai-chat" style="display: none;">';
        $this->render_ai_chat_config($settings);
        echo '</div>';
        
        // WhatsApp 設定
        echo '<div class="platform-config" data-platform="whatsapp" style="display: none;">';
        $this->render_whatsapp_config($settings);
        echo '</div>';
        
        // WeChat 設定
        echo '<div class="platform-config" data-platform="wechat" style="display: none;">';
        $this->render_wechat_config($settings);
        echo '</div>';
        
        // 其他平台設定
        $other_platforms = ['line', 'telegram', 'qq', 'facebook', 'instagram', 'twitter', 'discord', 'slack', 'teams', 'skype', 'viber', 'email', 'phone'];
        
        foreach ($other_platforms as $platform) {
            echo '<div class="platform-config" data-platform="' . esc_attr($platform) . '" style="display: none;">';
            $this->render_generic_platform_config($platform, $settings);
            echo '</div>';
        }
    }
    
    /**
     * Render AI Chat specific config
     */
    private function render_ai_chat_config($settings) {
        ?>
        <h3><i class="fas fa-robot" style="color: #007cba; margin-right: 8px;"></i><?php _e('AI 聊天設定', 'ai-chat'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('API 提供商', 'ai-chat'); ?></th>
                <td>
                    <select name="ai_chat_settings[ai_api_provider]" id="ai_api_provider">
                        <option value="openrouter" <?php selected($settings['ai_api_provider'] ?? 'openrouter', 'openrouter'); ?>>OpenRouter.ai</option>
                        <option value="custom" <?php selected($settings['ai_api_provider'] ?? 'openrouter', 'custom'); ?>><?php _e('自定義 API', 'ai-chat'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('API 密鑰', 'ai-chat'); ?></th>
                <td>
                    <input type="password" name="ai_chat_settings[ai_api_key]" value="<?php echo esc_attr($settings['ai_api_key'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('輸入您的 API 密鑰', 'ai-chat'); ?></p>
                </td>
            </tr>

            <tr class="custom-api-field" style="display: none;">
                <th scope="row"><?php _e('自定義 API URL', 'ai-chat'); ?></th>
                <td>
                    <input type="url" name="ai_chat_settings[ai_api_url]" value="<?php echo esc_attr($settings['ai_api_url'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('輸入您的自定義 API 端點 URL', 'ai-chat'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('AI 模型', 'ai-chat'); ?></th>
                <td>
                    <input type="text" name="ai_chat_settings[ai_model]" value="<?php echo esc_attr($settings['ai_model'] ?? 'openai/gpt-3.5-turbo'); ?>" class="regular-text">
                    <p class="description"><?php _e('模型名稱（例如：openai/gpt-3.5-turbo）', 'ai-chat'); ?></p>
                </td>
            </tr>

            <!-- API 性能優化設置 -->
            <tr>
                <th scope="row" style="border-top: 2px solid #0073aa; padding-top: 20px;">
                    <span style="color: #0073aa; font-weight: 600;"><?php _e('⚡ API 性能優化', 'ai-chat'); ?></span>
                </th>
                <td style="border-top: 2px solid #0073aa; padding-top: 20px;">
                    <p style="margin: 0; color: #666; font-style: italic;">
                        <?php _e('調整以下設置可以顯著提升 AI 反應速度', 'ai-chat'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('系統提示最大長度', 'ai-chat'); ?></th>
                <td>
                    <input type="number" name="ai_chat_settings[max_system_prompt_length]" value="<?php echo esc_attr($settings['max_system_prompt_length'] ?? '100000'); ?>" class="regular-text" min="10000" max="500000" step="10000">
                    <p class="description">
                        <?php _e('限制發送給 AI 的系統提示長度（字符數）', 'ai-chat'); ?><br>
                        <strong><?php _e('建議設置：', 'ai-chat'); ?></strong><br>
                        • <?php _e('快速模式：50,000 - 80,000 字符（反應最快）', 'ai-chat'); ?><br>
                        • <?php _e('平衡模式：100,000 - 150,000 字符（推薦）', 'ai-chat'); ?><br>
                        • <?php _e('完整模式：200,000+ 字符（內容最全但較慢）', 'ai-chat'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('網站文章抓取數量', 'ai-chat'); ?></th>
                <td>
                    <input type="number" name="ai_chat_settings[max_posts_count]" value="<?php echo esc_attr($settings['max_posts_count'] ?? '50'); ?>" class="regular-text" min="10" max="200" step="10">
                    <p class="description">
                        <?php _e('限制從網站抓取的文章數量', 'ai-chat'); ?><br>
                        <strong><?php _e('建議設置：', 'ai-chat'); ?></strong><br>
                        • <?php _e('快速模式：20-30 篇（反應最快）', 'ai-chat'); ?><br>
                        • <?php _e('平衡模式：50-80 篇（推薦）', 'ai-chat'); ?><br>
                        • <?php _e('完整模式：100+ 篇（內容最全但較慢）', 'ai-chat'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('產品信息抓取數量', 'ai-chat'); ?></th>
                <td>
                    <input type="number" name="ai_chat_settings[max_products_count]" value="<?php echo esc_attr($settings['max_products_count'] ?? '50'); ?>" class="regular-text" min="10" max="200" step="10">
                    <p class="description">
                        <?php _e('限制從 WooCommerce 抓取的產品數量', 'ai-chat'); ?><br>
                        <strong><?php _e('建議設置：', 'ai-chat'); ?></strong><br>
                        • <?php _e('快速模式：20-30 個產品', 'ai-chat'); ?><br>
                        • <?php _e('平衡模式：50-80 個產品（推薦）', 'ai-chat'); ?><br>
                        • <?php _e('完整模式：100+ 個產品', 'ai-chat'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('對話歷史發送數量', 'ai-chat'); ?></th>
                <td>
                    <input type="number" name="ai_chat_settings[max_conversation_history]" value="<?php echo esc_attr($settings['max_conversation_history'] ?? '10'); ?>" class="regular-text" min="5" max="50" step="5">
                    <p class="description">
                        <?php _e('發送給 AI 的最近對話消息數量', 'ai-chat'); ?><br>
                        <strong><?php _e('建議設置：', 'ai-chat'); ?></strong><br>
                        • <?php _e('快速模式：5-8 條消息', 'ai-chat'); ?><br>
                        • <?php _e('平衡模式：10-15 條消息（推薦）', 'ai-chat'); ?><br>
                        • <?php _e('完整模式：20+ 條消息', 'ai-chat'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('啟用並行處理', 'ai-chat'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="ai_chat_settings[enable_parallel_processing]" value="1" <?php checked($settings['enable_parallel_processing'] ?? '1', '1'); ?>>
                        <?php _e('啟用並行數據獲取（推薦）', 'ai-chat'); ?>
                    </label>
                    <p class="description">
                        <?php _e('同時獲取多個數據源，而不是順序處理，可顯著提升速度', 'ai-chat'); ?><br>
                        <strong style="color: #0073aa;"><?php _e('💡 提示：啟用此選項可將數據獲取時間減少 50-70%', 'ai-chat'); ?></strong>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render WhatsApp specific config
     */
    private function render_whatsapp_config($settings) {
        ?>
        <h3><i class="fab fa-whatsapp" style="color: #25d366; margin-right: 8px;"></i><?php _e('WhatsApp Configuration', 'ai-chat'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('電話號碼', 'ai-chat'); ?></th>
                <td>
                    <input type="text" name="ai_chat_settings[whatsapp_phone]" value="<?php echo esc_attr($settings['whatsapp_phone'] ?? ''); ?>" class="regular-text" placeholder="+886912345678">
                    <p class="description"><?php _e('包含國家代碼（例如：+1234567890）', 'ai-chat'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('預設訊息', 'ai-chat'); ?></th>
                <td>
                    <textarea name="ai_chat_settings[whatsapp_message]" rows="3" class="large-text"><?php echo esc_textarea($settings['whatsapp_message'] ?? ''); ?></textarea>
                    <p class="description"><?php _e('用戶點擊 WhatsApp 氣泡時的預設訊息（可選）', 'ai-chat'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render WeChat specific config
     */
    private function render_wechat_config($settings) {
        ?>
        <h3><i class="fab fa-weixin" style="color: #7bb32e; margin-right: 8px;"></i><?php _e('WeChat Configuration', 'ai-chat'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('WeChat Account', 'ai-chat'); ?></th>
                <td>
                    <input type="text" name="ai_chat_settings[wechat_account]" value="<?php echo esc_attr($settings['wechat_account'] ?? ''); ?>" class="regular-text" placeholder="your_wechat_id">
                    <p class="description"><?php _e('Your WeChat account ID', 'ai-chat'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('QR Code 圖片', 'ai-chat'); ?></th>
                <td>
                    <div class="wechat-qr-upload">
                        <input type="hidden" name="ai_chat_settings[wechat_qr_url]" id="wechat_qr_url" value="<?php echo esc_attr($settings['wechat_qr_url'] ?? ''); ?>">
                        <button type="button" class="button button-primary" id="upload_wechat_qr">
                            <span class="dashicons dashicons-upload" style="margin-right: 5px;"></span>
                            <?php _e('上傳 QR Code', 'ai-chat'); ?>
                        </button>
                        <button type="button" class="button" id="remove_wechat_qr" style="<?php echo empty($settings['wechat_qr_url']) ? 'display:none;' : ''; ?>">
                            <span class="dashicons dashicons-trash" style="margin-right: 5px;"></span>
                            <?php _e('移除', 'ai-chat'); ?>
                        </button>

                        <div id="wechat_qr_preview" style="margin-top: 15px;">
                            <?php if (!empty($settings['wechat_qr_url'])): ?>
                                <div style="border: 2px solid #ddd; border-radius: 8px; padding: 10px; display: inline-block; background: #f9f9f9;">
                                    <img src="<?php echo esc_url($settings['wechat_qr_url']); ?>" style="max-width: 200px; max-height: 200px; border-radius: 4px; display: block;">
                                    <p style="margin: 10px 0 0 0; text-align: center; font-size: 12px; color: #666;">當前 QR Code</p>
                                </div>
                            <?php else: ?>
                                <div style="border: 2px dashed #ddd; border-radius: 8px; padding: 40px; text-align: center; color: #999;">
                                    <span class="dashicons dashicons-format-image" style="font-size: 48px; margin-bottom: 10px;"></span>
                                    <p style="margin: 0;">尚未上傳 QR Code</p>
                                </div>
                            <?php endif; ?>
                        </div>


                    </div>
                    <p class="description"><?php _e('Upload WeChat QR Code image, users will see this image when they click', 'ai-chat'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render display settings
     */
    private function render_display_settings($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('聊天位置', 'ai-chat'); ?></th>
                <td>
                    <select name="ai_chat_settings[button_position]">
                        <option value="bottom-right" <?php selected($settings['button_position'] ?? 'bottom-right', 'bottom-right'); ?>><?php _e('右下角', 'ai-chat'); ?></option>
                        <option value="bottom-left" <?php selected($settings['button_position'] ?? 'bottom-right', 'bottom-left'); ?>><?php _e('左下角', 'ai-chat'); ?></option>
                        <option value="bottom-center" <?php selected($settings['button_position'] ?? 'bottom-right', 'bottom-center'); ?>><?php _e('底部中央', 'ai-chat'); ?></option>
                        <option value="top-right" <?php selected($settings['button_position'] ?? 'bottom-right', 'top-right'); ?>><?php _e('右上角', 'ai-chat'); ?></option>
                        <option value="top-left" <?php selected($settings['button_position'] ?? 'bottom-right', 'top-left'); ?>><?php _e('左上角', 'ai-chat'); ?></option>
                        <option value="middle-right" <?php selected($settings['button_position'] ?? 'bottom-right', 'middle-right'); ?>><?php _e('右側中央', 'ai-chat'); ?></option>
                        <option value="middle-left" <?php selected($settings['button_position'] ?? 'bottom-right', 'middle-left'); ?>><?php _e('左側中央', 'ai-chat'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('底部距離', 'ai-chat'); ?></th>
                <td>
                    <input type="number" name="ai_chat_settings[bottom_distance]" value="<?php echo esc_attr($settings['bottom_distance'] ?? '20'); ?>" min="0" max="200" style="width: 80px;" />
                    <span class="description"><?php _e('px - 聊天按鈕距離底部的距離（適用於底部位置）', 'ai-chat'); ?></span>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('頂部距離', 'ai-chat'); ?></th>
                <td>
                    <input type="number" name="ai_chat_settings[top_distance]" value="<?php echo esc_attr($settings['top_distance'] ?? '20'); ?>" min="0" max="200" style="width: 80px;" />
                    <span class="description"><?php _e('px - 聊天按鈕距離頂部的距離（適用於頂部位置）', 'ai-chat'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('按鈕顏色', 'ai-chat'); ?></th>
                <td>
                    <input type="color" name="ai_chat_settings[chat_color]" value="<?php echo esc_attr($settings['chat_color'] ?? $settings['button_color'] ?? '#007cba'); ?>" class="regular-text">
                    <p class="description"><?php _e('聊天按鈕的背景顏色', 'ai-chat'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('按鈕大小', 'ai-chat'); ?></th>
                <td>
                    <select name="ai_chat_settings[button_size]">
                        <option value="small" <?php selected($settings['button_size'] ?? 'medium', 'small'); ?>><?php _e('小', 'ai-chat'); ?></option>
                        <option value="medium" <?php selected($settings['button_size'] ?? 'medium', 'medium'); ?>><?php _e('中', 'ai-chat'); ?></option>
                        <option value="large" <?php selected($settings['button_size'] ?? 'medium', 'large'); ?>><?php _e('大', 'ai-chat'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('顯示選項', 'ai-chat'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="ai_chat_settings[display_on_mobile]" value="1" <?php checked($settings['display_on_mobile'] ?? true, true); ?>>
                            <?php _e('在移動設備上顯示', 'ai-chat'); ?>
                        </label>
                        <br><br>
                        <label>
                            <input type="checkbox" name="ai_chat_settings[animation_enabled]" value="1" <?php checked($settings['animation_enabled'] ?? true, true); ?>>
                            <?php _e('啟用動畫', 'ai-chat'); ?>
                        </label>
                        <br><br>
                        <label>
                            <input type="checkbox" name="ai_chat_settings[show_notification_badge]" value="1" <?php checked($settings['show_notification_badge'] ?? false, true); ?>>
                            <?php _e('顯示通知徽章', 'ai-chat'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('氣泡動畫持續時間', 'ai-chat'); ?></th>
                <td>
                    <input type="number" name="ai_chat_settings[bubble_animation_duration]" value="<?php echo esc_attr($settings['bubble_animation_duration'] ?? 300); ?>" min="100" max="1000" step="50" class="small-text"> ms
                    <p class="description"><?php _e('氣泡展開/收縮動畫的持續時間（毫秒）', 'ai-chat'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('自動隱藏氣泡', 'ai-chat'); ?></th>
                <td>
                    <input type="number" name="ai_chat_settings[auto_hide_bubbles]" value="<?php echo esc_attr($settings['auto_hide_bubbles'] ?? 0); ?>" min="0" max="60" class="small-text"> <?php _e('秒', 'ai-chat'); ?>
                    <p class="description"><?php _e('氣泡展開後自動隱藏的時間，0 表示永不自動隱藏', 'ai-chat'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render generic platform config
     */
    private function render_generic_platform_config($platform, $settings) {
        $platform_info = $this->get_platform_info($platform);
        ?>
        <h3><i class="<?php echo esc_attr($platform_info['icon']); ?>" style="color: <?php echo esc_attr($platform_info['color']); ?>; margin-right: 8px;"></i><?php echo esc_html($platform_info['name']); ?> 設定</h3>
          <table class="form-table">
            <?php if (in_array($platform, ['line', 'telegram', 'instagram', 'twitter'])): ?>
                <tr>
                    <th scope="row"><?php _e('Username', 'ai-chat'); ?></th>
                    <td>
                        <input type="text" name="ai_chat_settings[<?php echo $platform; ?>_username]" value="<?php echo esc_attr($settings[$platform . '_username'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php echo sprintf(__('Your %s username', 'ai-chat'), $platform_info['name']); ?></p>
                    </td>
                </tr>
            <?php elseif ($platform === 'facebook'): ?>
                <tr>
                    <th scope="row"><?php _e('Facebook Page Name', 'ai-chat'); ?></th>
                    <td>
                        <input type="text" name="ai_chat_settings[facebook_page_name]" value="<?php echo esc_attr($settings['facebook_page_name'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php _e('Your Facebook Page name or ID', 'ai-chat'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Messenger 連結', 'ai-chat'); ?></th>
                    <td>
                        <input type="url" name="ai_chat_settings[facebook_messenger_url]" value="<?php echo esc_attr($settings['facebook_messenger_url'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php _e('Facebook Messenger 的完整連結網址', 'ai-chat'); ?></p>
                    </td>
                </tr>
            <?php elseif ($platform === 'qq'): ?>
                <tr>
                    <th scope="row"><?php _e('QQ Number', 'ai-chat'); ?></th>
                    <td>
                        <input type="text" name="ai_chat_settings[qq_number]" value="<?php echo esc_attr($settings['qq_number'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php _e('Your QQ number', 'ai-chat'); ?></p>
                    </td>
                </tr>
            <?php elseif ($platform === 'email'): ?>
                <tr>
                    <th scope="row"><?php _e('電子郵件', 'ai-chat'); ?></th>
                    <td>
                        <input type="email" name="ai_chat_settings[contact_email]" value="<?php echo esc_attr($settings['contact_email'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php _e('聯絡電子郵件地址', 'ai-chat'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Email Subject', 'ai-chat'); ?></th>
                    <td>
                        <input type="text" name="ai_chat_settings[email_subject]" value="<?php echo esc_attr($settings['email_subject'] ?? 'Website Inquiry'); ?>" class="regular-text">
                        <p class="description"><?php _e('Default email subject', 'ai-chat'); ?></p>
                    </td>
                </tr>
            <?php elseif ($platform === 'phone'): ?>
                <tr>
                    <th scope="row"><?php _e('Phone Number', 'ai-chat'); ?></th>
                    <td>
                        <input type="tel" name="ai_chat_settings[contact_phone]" value="<?php echo esc_attr($settings['contact_phone'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php _e('Contact phone number', 'ai-chat'); ?></p>
                    </td>
                </tr>
            <?php else: ?>
                <tr>
                    <th scope="row"><?php _e('連結 URL', 'ai-chat'); ?></th>
                    <td>
                        <input type="url" name="ai_chat_settings[<?php echo $platform; ?>_url]" value="<?php echo esc_attr($settings[$platform . '_url'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php echo sprintf(__('您的 %s 連結 URL', 'ai-chat'), $platform_info['name']); ?></p>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /**
     * Get platform info
     */
    private function get_platform_info($platform) {
        $platforms = array(
            'line' => array('name' => 'LINE', 'icon' => 'fab fa-line', 'color' => '#00c300'),
            'telegram' => array('name' => 'Telegram', 'icon' => 'fab fa-telegram-plane', 'color' => '#0088cc'),
            'qq' => array('name' => 'QQ', 'icon' => 'fab fa-qq', 'color' => '#eb1923'),
            'facebook' => array('name' => 'Facebook Messenger', 'icon' => 'fab fa-facebook-messenger', 'color' => '#0084ff'),
            'instagram' => array('name' => 'Instagram', 'icon' => 'fab fa-instagram', 'color' => '#e4405f'),
            'twitter' => array('name' => 'Twitter/X', 'icon' => 'fab fa-x-twitter', 'color' => '#1da1f2'),
            'discord' => array('name' => 'Discord', 'icon' => 'fab fa-discord', 'color' => '#7289da'),
            'slack' => array('name' => 'Slack', 'icon' => 'fab fa-slack', 'color' => '#4a154b'),
            'teams' => array('name' => 'Microsoft Teams', 'icon' => 'fab fa-microsoft', 'color' => '#6264a7'),
            'skype' => array('name' => 'Skype', 'icon' => 'fab fa-skype', 'color' => '#00aff0'),
            'viber' => array('name' => 'Viber', 'icon' => 'fab fa-viber', 'color' => '#665cac'),
            'email' => array('name' => 'Email', 'icon' => 'fas fa-envelope', 'color' => '#dc3545'),
            'phone' => array('name' => 'Phone', 'icon' => 'fas fa-phone', 'color' => '#28a745')
        );
        
        return $platforms[$platform] ?? array('name' => ucfirst($platform), 'icon' => 'fas fa-comments', 'color' => '#666');
    }
    
    /**
     * Render admin styles and scripts
     */    private function render_admin_styles_and_scripts() {
        ?>
        <style>
        /* === AI Chat Admin 現代化樣式系統 === */
        
        /* 全域設定 - 覆蓋 WordPress 默認樣式 */
        .wrap.ai-chat-admin {
            max-width: none !important;
            margin: 20px 20px 20px 0 !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
        }

        /* 強制覆蓋 WordPress 的 .card max-width 限制 */
        .ai-chat-admin .card,
        .ai-chat-admin .postbox,
        .ai-chat-admin .modern-card,
        .ai-chat-admin-container .card,
        .platform-settings-panel .card {
            max-width: none !important;
            width: 100% !important;
        }

        /* 最高優先級覆蓋 - 針對具體的卡片容器 */
        body.wp-admin .ai-chat-admin .card {
            max-width: none !important;
            width: 100% !important;
        }
        
        /* 頁面標題 */
        .page-header {
            position: relative;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        
        /* 現代化卡片 - 覆蓋 WordPress 默認樣式 */
        .ai-chat-admin .card,
        .ai-chat-admin .modern-card {
            background: white;
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            max-width: none !important; /* 覆蓋 WordPress 的 max-width: 520px */
            width: 100% !important;
        }
        
        .modern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .card-header {
            padding: 25px 30px 20px 30px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #eee;
        }
        
        .modern-card .card-header + * {
            padding: 25px 30px;
        }
        
        /* 容器佈局 - 2欄 Grid 布局 */
        .ai-chat-admin-container {
            display: grid !important;
            grid-template-columns: 30% 70% !important;
            gap: 25px !important;
            margin-top: 20px;
            align-items: start;
            max-width: 95% !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }

        /* 響應式設計 */
        @media (max-width: 1400px) {
            .ai-chat-admin-container {
                grid-template-columns: 35% 65% !important;
                gap: 20px !important;
            }
        }

        @media (max-width: 1200px) {
            .ai-chat-admin-container {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
                max-width: 100% !important;
            }
        }

        /* 平台設置面板優化 - 強制覆蓋 WordPress 樣式 */
        .ai-chat-admin .platform-settings-panel {
            width: 100% !important;
            min-height: 600px;
        }

        .ai-chat-admin .platform-settings-panel .card,
        .ai-chat-admin .platform-settings-panel .modern-card {
            width: 100% !important;
            max-width: none !important;
            box-sizing: border-box !important;
        }

        /* 確保所有 AI Chat 管理頁面的卡片都不受 WordPress 限制 */
        .ai-chat-admin .card {
            max-width: none !important;
            width: 100% !important;
        }
        
        /* 平台選擇列表 */
        .platform-selection-list {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            background: #fafafa;
            margin: 20px 0;
        }
        
        .platform-selection-item {
            display: flex;
            align-items: center;
            padding: 16px;
            margin: 10px 0;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background: white;
            position: relative;
        }
        
        .platform-selection-item:hover {
            border-color: #007cba;
            box-shadow: 0 4px 20px rgba(0,124,186,0.15);
            transform: translateY(-2px);
        }
        
        .platform-selection-item.active {
            border-color: var(--platform-color, #007cba);
            background: linear-gradient(135deg, var(--platform-color, #007cba)10, white);
            box-shadow: 0 6px 25px var(--platform-color, #007cba)25;
            transform: scale(1.02);
        }

        .platform-selection-item.current-config {
            border-color: #28a745 !important;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), white) !important;
            box-shadow: 0 6px 25px rgba(40, 167, 69, 0.25) !important;
            position: relative;
        }

        .platform-selection-item.current-config::after {
            content: attr(data-config-text);
            position: absolute;
            top: -8px;
            right: -8px;
            background: #28a745;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .platform-selection-item input[type="checkbox"] {
            margin-right: 15px;
            transform: scale(1.4);
            cursor: pointer;
            accent-color: var(--platform-color, #007cba);
        }
        
        .platform-selection-item .platform-icon {
            font-size: 24px;
            margin-right: 15px;
            width: 32px;
            text-align: center;
            color: var(--platform-color, #666);
        }
        
        .platform-selection-item .platform-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        /* 快速選擇按鈕 */
        .quick-select-btn {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .quick-select-btn:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102,126,234,0.3);
        }
        
        /* 預覽按鈕 */
        .preview-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79,172,254,0.4);
        }
        
        /* 平台設定區域 */
        .platform-config {
            display: none;
            animation: fadeInUp 0.3s ease;
        }
        
        .platform-config.active {
            display: block;
        }
        
        .platform-config-placeholder {
            animation: pulse 2s infinite;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* 表單樣式美化 */
        .form-table th {
            font-weight: 600;
            color: #333;
            padding: 15px 10px;
        }
        
        .form-table td {
            padding: 15px 10px;
        }
        
        .form-table input[type="text"],
        .form-table input[type="url"],
        .form-table input[type="email"],
        .form-table textarea,
        .form-table select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 400px;
        }
        
        .form-table input:focus,
        .form-table textarea:focus,
        .form-table select:focus {
            border-color: #007cba;
            box-shadow: 0 0 0 3px rgba(0,124,186,0.1);
            outline: none;
        }
        
        /* 提交按鈕美化 */
        .button-primary.large {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 15px 40px;
            font-size: 16px;
            font-weight: 600;
            text-shadow: none;
            box-shadow: 0 4px 15px rgba(102,126,234,0.3);
            transition: all 0.3s ease;
        }
        
        .button-primary.large:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
        }
        
        /* 說明文檔樣式 */
        .help-content {
            font-size: 14px;
            line-height: 1.6;
        }
        
        .help-content h4 {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .help-content ul {
            margin: 8px 0;
            padding-left: 20px;
        }
        
        .help-content li {
            margin-bottom: 5px;
        }
        
        .help-content a {
            color: #007cba;
            text-decoration: none;
            font-weight: 500;
        }
        
        .help-content a:hover {
            text-decoration: underline;
        }
        
        /* 響應式設計 */
        @media (max-width: 768px) {
            .wrap.ai-chat-admin {
                margin: 10px !important;
            }
            
            .page-header {
                padding: 25px 20px !important;
                text-align: center;
            }
            
            .page-header h1 {
                font-size: 24px !important;
            }
            
            .ai-chat-admin-container {
                gap: 20px !important;
            }
            
            .platform-selection-panel,
            .platform-config-panel {
                min-width: auto !important;
            }
            
            .card-header {
                padding: 20px 15px 15px 15px !important;
            }
            
            .modern-card .card-header + * {
                padding: 20px 15px !important;
            }
            
            .quick-select-btn {
                font-size: 12px;
                padding: 8px 12px;
            }
        }
        
        /* 滾動條美化 */
        .platform-selection-list::-webkit-scrollbar {
            width: 8px;
        }
        
        .platform-selection-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .platform-selection-list::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 4px;
        }
        
        .platform-selection-list::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #5a67d8, #6b46c1);
        }
        </style>

        <script>
        // Translation strings
        const aiChatTranslations = {
            'no_platform_selected': '<?php echo esc_js(__('No platform selected yet', 'ai-chat')); ?>',
            'single_platform_selected': '<?php echo esc_js(__('✅ Selected 1 platform (Single mode)', 'ai-chat')); ?>',
            'multiple_platforms_selected': '<?php echo esc_js(__('✅ Selected %d platforms (Multi-select mode)', 'ai-chat')); ?>',
            'api_key_required': '<?php echo esc_js(__('⚠️ AI chat function requires API key setup!', 'ai-chat')); ?>',
            'configuring': '⚙️ <?php echo esc_js(__('正在設定', 'ai-chat')); ?>'
        };

        // 為對話詳情模態框提供翻譯字符串
        window.aiChatAdmin = {
            strings: {
                'conversation_id': '<?php echo esc_js(__('對話ID:', 'ai-chat')); ?>',
                'platform': '<?php echo esc_js(__('平台:', 'ai-chat')); ?>',
                'status': '<?php echo esc_js(__('狀態:', 'ai-chat')); ?>',
                'started_at': '<?php echo esc_js(__('開始時間:', 'ai-chat')); ?>',
                'last_activity': '<?php echo esc_js(__('最後活動:', 'ai-chat')); ?>',
                'message_count': '<?php echo esc_js(__('訊息數量:', 'ai-chat')); ?>',
                'message_records': '<?php echo esc_js(__('訊息記錄', 'ai-chat')); ?>'
            }
        };

        jQuery(document).ready(function($) {
            // Remove possible error messages
            setTimeout(function() {
                $('.notice-error, .ai-chat-error, .error').each(function() {
                    var text = $(this).text();
                    if (text.includes('API') && (text.includes('金鑰') || text.includes('key'))) {
                        $(this).fadeOut().remove();
                    }
                });
            }, 100);

            // 平台選擇功能
            function updatePlatformVisibility() {
                // 獲取選中的平台
                const checkedPlatforms = $('input[name="ai_chat_settings[enabled_platforms][]"]:checked');

                if (checkedPlatforms.length > 0) {
                    $('#no-platform-selected').hide();

                    // 如果沒有顯示任何設定，顯示第一個選中平台的設定
                    if ($('.platform-config.active').length === 0) {
                        const firstPlatform = checkedPlatforms.first().val();
                        showPlatformConfig(firstPlatform);
                    }

                    // 更新回饋信息
                    updatePlatformFeedback(checkedPlatforms.length);
                } else {
                    // 隱藏所有平台設定
                    $('.platform-config').removeClass('active').hide();
                    $('#no-platform-selected').show();
                    updatePlatformFeedback(0);
                }

                // 更新平台項目的視覺狀態
                $('.platform-selection-item').removeClass('active');
                checkedPlatforms.each(function() {
                    $(this).closest('.platform-selection-item').addClass('active');
                });
            }

            function showPlatformConfig(platform) {
                // 隱藏所有平台設定
                $('.platform-config').removeClass('active').hide();

                // 顯示指定平台的設定
                $(`.platform-config[data-platform="${platform}"]`).addClass('active').show();

                // 高亮當前選中的平台項目
                $('.platform-selection-item').removeClass('current-config').removeAttr('data-config-text');
                $(`.platform-selection-item[data-platform="${platform}"]`)
                    .addClass('current-config')
                    .attr('data-config-text', aiChatTranslations.configuring);
            }
            
            function updatePlatformFeedback(count) {
                const feedback = $('#platform-feedback');
                if (count === 0) {
                    feedback.html('<span style="color: #dc3545;">' + aiChatTranslations.no_platform_selected + '</span>').css({
                        'background': 'linear-gradient(135deg, #fff5f5, #fee)',
                        'border': '2px solid #dc3545'
                    });
                } else if (count === 1) {
                    feedback.html('<span style="color: #28a745;">' + aiChatTranslations.single_platform_selected + '</span>').css({
                        'background': 'linear-gradient(135deg, #f0fff4, #e8f5e8)',
                        'border': '2px solid #28a745'
                    });
                } else {
                    const message = aiChatTranslations.multiple_platforms_selected.replace('%d', count);
                    feedback.html(`<span style="color: #007cba;">${message}</span>`).css({
                        'background': 'linear-gradient(135deg, #f0f8ff, #e8f4fd)',
                        'border': '2px solid #007cba'
                    });
                }
            }
            
            // Platform selection change event
            $(document).on('change', 'input[name="ai_chat_settings[enabled_platforms][]"]', function() {
                updatePlatformVisibility();
                updateCheckboxStyles();
            });
            
            // 平台項目點擊事件
            $(document).on('click', '.platform-selection-item', function(e) {
                if (e.target.type !== 'checkbox') {
                    const checkbox = $(this).find('input[type="checkbox"]');
                    checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
                }

                // 顯示對應平台的設定
                const platform = $(this).data('platform');
                if (platform) {
                    showPlatformConfig(platform);
                }
            });
            
            // 快捷選擇按鈕
            $('#select-popular').click(function() {
                const platforms = $(this).data('platforms').split(',');
                $('input[name="ai_chat_settings[enabled_platforms][]"]').prop('checked', false);
                platforms.forEach(platform => {
                    $(`input[value="${platform}"]`).prop('checked', true);
                });
                updatePlatformVisibility();
            });
            
            $('#select-social').click(function() {
                const platforms = $(this).data('platforms').split(',');
                $('input[name="ai_chat_settings[enabled_platforms][]"]').prop('checked', false);
                platforms.forEach(platform => {
                    $(`input[value="${platform}"]`).prop('checked', true);
                });
                updatePlatformVisibility();
            });
            
            $('#select-all').click(function() {
                $('input[name="ai_chat_settings[enabled_platforms][]"]').prop('checked', true);
                updatePlatformVisibility();
            });
            
            $('#clear-all').click(function() {
                $('input[name="ai_chat_settings[enabled_platforms][]"]').prop('checked', false);
                updatePlatformVisibility();
            });
            
            // 更新復選框樣式
            function updateCheckboxStyles() {
                $('.platform-selection-item').each(function() {
                    const checkbox = $(this).find('input[type="checkbox"]');
                    const platformColor = $(this).find('.platform-icon').css('color');
                    
                    if (checkbox.is(':checked')) {
                        $(this).css('--platform-color', platformColor);
                    }
                });
            }
            
            // 表單提交前驗證
            $('#ai-chat-settings-form').on('submit', function(e) {
                const checkedPlatforms = $('input[name="ai_chat_settings[enabled_platforms][]"]:checked');
                
                if (checkedPlatforms.length === 0) {
                    e.preventDefault();
                    alert('<?php echo esc_js(__('⚠️ 請至少選擇一個聊天平台！', 'ai-chat')); ?>');
                    return false;
                }
                
                // 檢查AI聊天是否選中但缺少API設定
                const aiChatSelected = $('input[value="ai-chat"]:checked').length > 0;
                if (aiChatSelected) {
                    const apiKey = $('input[name="ai_chat_settings[ai_api_key]"]').val();
                    if (!apiKey || apiKey.trim() === '') {
                        e.preventDefault();
                        alert(aiChatTranslations.api_key_required);
                        return false;
                    }
                }
                
                // 顯示保存中狀態
                const submitBtn = $(this).find('input[type="submit"]');
                submitBtn.val('保存中...').prop('disabled', true);
            });
            
            // 初始化
            updatePlatformVisibility();
            updateCheckboxStyles();
        });
        </script>
        <?php
    }

    /**
     * Chat History admin page
     */
    public function chat_history_page() {
        $database = new AI_Chat_Database();

        // Silently ensure tables exist and are up to date
        $missing_tables = $database->check_tables();
        if (!empty($missing_tables)) {
            AI_Chat_Database::force_create_tables();
        }

        // Silently upgrade message table if needed
        AI_Chat_Database::upgrade_message_table();



        // Handle bulk delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete_conversations' && wp_verify_nonce($_POST['_wpnonce'], 'bulk_delete_conversations')) {
            $conversation_ids = $_POST['conversation_ids'] ?? array();
            $deleted_count = 0;

            foreach ($conversation_ids as $conversation_id) {
                if ($database->delete_conversation(intval($conversation_id))) {
                    $deleted_count++;
                }
            }

            if ($deleted_count > 0) {
                echo '<div class="notice notice-success"><p>' . sprintf(__('Deleted %d conversation records.', 'ai-chat'), $deleted_count) . '</p></div>';
            }
        }

        // Handle clear all history action
        if (isset($_POST['clear_all_history']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_all_chat_history')) {
            $deleted = $database->clear_all_conversations();
            echo '<div class="notice notice-success"><p>' . sprintf(__('Cleared all conversation records, total %d conversations.', 'ai-chat'), $deleted) . '</p></div>';
        }

        // Get conversation statistics
        $total_conversations = $database->get_conversation_count();
        $conversations = array();
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        $total_pages = 0;

        if ($total_conversations > 0) {
            $conversations = $database->get_conversations($per_page, $offset);
            $total_pages = ceil($total_conversations / $per_page);
        }

        // Get additional statistics
        $total_messages = method_exists($database, 'get_total_message_count') ? $database->get_total_message_count() : 0;
        $active_conversations = method_exists($database, 'get_active_conversation_count') ? $database->get_active_conversation_count() : 0;

        ?>
        <style>
            /* 強力清理頁面頂部空白 */
            .wrap.ai-chat-history {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }

            /* 隱藏所有可能的空白通知和元素 */
            .wrap.ai-chat-history .notice:empty,
            .wrap.ai-chat-history .notice p:empty,
            .wrap.ai-chat-history > .notice:empty,
            .wrap.ai-chat-history > div:empty,
            .wrap.ai-chat-history .updated:empty,
            .wrap.ai-chat-history .error:empty {
                display: none !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* 確保第一個可見元素緊貼頂部 */
            .wrap.ai-chat-history > *:first-child {
                margin-top: 0 !important;
            }

            /* 移除 WordPress 默認的頁面頂部間距 */
            .wrap.ai-chat-history::before {
                display: none !important;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // 動態移除空白元素
            $('.wrap.ai-chat-history').find('.notice:empty, .updated:empty, .error:empty, div:empty').remove();

            // 移除只包含空白字符的元素
            $('.wrap.ai-chat-history').children().each(function() {
                if ($(this).text().trim() === '' && $(this).children().length === 0) {
                    $(this).remove();
                }
            });

            // 確保頁面標題是第一個元素
            var $pageHeader = $('.wrap.ai-chat-history .page-header');
            if ($pageHeader.length > 0) {
                $pageHeader.prependTo('.wrap.ai-chat-history');
            }
        });
        </script>
        <div class="wrap ai-chat-history">
            <!-- 頁面標題區域 -->
            <div class="page-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 20px; margin-bottom: 30px; position: relative; overflow: hidden;">
                <div style="position: relative; z-index: 2;">
                    <h1 style="margin: 0 0 10px 0; font-size: 32px; font-weight: 300;">
                        <i class="dashicons dashicons-format-chat" style="font-size: 36px; margin-right: 15px; vertical-align: middle;"></i>
                        <?php _e('AI 聊天對話歷史', 'ai-chat'); ?>
                    </h1>
                    <p style="margin: 0; font-size: 16px; opacity: 0.9;"><?php _e('管理和查看所有 AI 聊天對話記錄', 'ai-chat'); ?></p>
                </div>
                <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; opacity: 0.3;"></div>
            </div>

            <!-- 統計卡片區域 -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 30px; max-width: 1200px;">
            <style>
                @media (max-width: 768px) {
                    .stats-grid {
                        grid-template-columns: 1fr !important;
                    }
                }
                @media (max-width: 1024px) and (min-width: 769px) {
                    .stats-grid {
                        grid-template-columns: repeat(2, 1fr) !important;
                    }
                }
            </style>
                <div class="modern-card stat-card" style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: all 0.3s ease;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;"><?php _e('總對話數', 'ai-chat'); ?></h3>
                            <div class="stat-number" style="font-size: 36px; font-weight: 700; color: #333; margin: 0;"><?php echo number_format($total_conversations); ?></div>
                        </div>
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="dashicons dashicons-format-chat" style="font-size: 24px; color: white;"></i>
                        </div>
                    </div>
                </div>

                <div class="modern-card stat-card" style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: all 0.3s ease;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;"><?php _e('總訊息數', 'ai-chat'); ?></h3>
                            <div class="stat-number" style="font-size: 36px; font-weight: 700; color: #333; margin: 0;"><?php echo number_format($total_messages); ?></div>
                        </div>
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #4facfe, #00f2fe); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="dashicons dashicons-email-alt" style="font-size: 24px; color: white;"></i>
                        </div>
                    </div>
                </div>

                <div class="modern-card stat-card" style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: all 0.3s ease;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;"><?php _e('活躍對話', 'ai-chat'); ?></h3>
                            <div class="stat-number" style="font-size: 36px; font-weight: 700; color: #333; margin: 0;"><?php echo number_format($active_conversations); ?></div>
                        </div>
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #43e97b, #38f9d7); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="dashicons dashicons-yes-alt" style="font-size: 24px; color: white;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($conversations)): ?>
                <!-- 無對話記錄狀態 -->
                <div class="modern-card" style="background: white; border-radius: 16px; padding: 60px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <div style="font-size: 64px; color: #e0e0e0; margin-bottom: 20px;">
                        <i class="dashicons dashicons-format-chat"></i>
                    </div>
                    <h3 style="color: #666; margin-bottom: 10px;"><?php _e('尚無對話記錄', 'ai-chat'); ?></h3>
                    <p style="color: #999; margin: 0;"><?php _e('當用戶開始使用 AI 聊天功能時，對話記錄將會顯示在這裡。', 'ai-chat'); ?></p>
                </div>
            <?php else: ?>
                <!-- 工具欄 -->
                <div class="modern-card" style="background: white; border-radius: 16px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <h3 style="margin: 0; color: #333;"><?php _e('對話管理', 'ai-chat'); ?></h3>
                            <span style="background: #f0f0f0; color: #666; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;">
                                <?php echo sprintf(__('共 %d 個對話', 'ai-chat'), $total_conversations); ?>
                            </span>
                        </div>

                        <div style="display: flex; gap: 10px; align-items: center;">
                            <!-- 批量操作 -->
                            <form method="post" style="display: inline-block;" onsubmit="return confirmBulkDelete()">
                                <?php wp_nonce_field('bulk_delete_conversations'); ?>
                                <input type="hidden" name="action" value="delete_conversations">
                                <button type="submit" class="button" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px;" disabled id="bulk-delete-btn">
                                    <i class="dashicons dashicons-trash" style="font-size: 14px; margin-right: 5px;"></i>
                                    <?php _e('刪除選中項', 'ai-chat'); ?> (<span id="selected-count">0</span>)
                                </button>
                            </form>

                            <!-- 清除所有記錄 -->
                            <form method="post" style="display: inline-block;" onsubmit="return confirm('<?php echo esc_js(__('⚠️ 確定要清除所有對話記錄嗎？\\n\\n此操作將永久刪除所有對話和訊息，無法撤銷！', 'ai-chat')); ?>')">
                                <?php wp_nonce_field('clear_all_chat_history'); ?>
                                <button type="submit" name="clear_all_history" class="button" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px;">
                                    <i class="dashicons dashicons-dismiss" style="font-size: 14px; margin-right: 5px;"></i>
                                    <?php _e('清除全部', 'ai-chat'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 批量操作區域 -->
                <div class="batch-operations modern-card" style="background: linear-gradient(135deg, #fff3cd, #ffeaa7); border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); display: none;" id="batch-operations">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="dashicons dashicons-info" style="font-size: 20px; color: #856404;"></i>
                            <span style="color: #856404; font-weight: 500;">
                                已選擇 <strong id="selected-count-display">0</strong> 個對話
                            </span>
                        </div>

                        <div class="platform-quick-actions" style="display: flex; gap: 8px;">
                            <button type="button" class="button quick-select-btn" onclick="selectAll()" style="font-size: 12px; padding: 6px 12px;">
                                <?php _e('全選', 'ai-chat'); ?>
                            </button>
                            <button type="button" class="button quick-select-btn" onclick="selectNone()" style="font-size: 12px; padding: 6px 12px;">
                                <?php _e('取消全選', 'ai-chat'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 對話表格 -->
                <div class="conversations-table modern-card" style="background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <form method="post" id="conversations-form">
                        <?php wp_nonce_field('bulk_delete_conversations'); ?>
                        <input type="hidden" name="action" value="delete_conversations">

                        <table class="wp-list-table widefat fixed striped" style="border: none; border-radius: 0;">
                            <thead style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                                <tr>
                                    <td class="manage-column column-cb check-column" style="padding: 15px;">
                                        <input type="checkbox" id="cb-select-all" style="transform: scale(1.2);">
                                    </td>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-id" style="margin-right: 5px;"></i>
                                        <?php _e('對話ID', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-calendar-alt" style="margin-right: 5px;"></i>
                                        <?php _e('開始時間', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-email-alt" style="margin-right: 5px;"></i>
                                        <?php _e('訊息數', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-clock" style="margin-right: 5px;"></i>
                                        <?php _e('最後活動', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-admin-generic" style="margin-right: 5px;"></i>
                                        <?php _e('平台', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-location" style="margin-right: 5px;"></i>
                                        <?php _e('IP地址', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></i>
                                        <?php _e('操作', 'ai-chat'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conversations as $conversation):
                                    $message_count = $database->get_conversation_message_count($conversation['conversation_id']);
                                    $platform = $conversation['platform'] ?? 'ai-chat';
                                    $platform_info = $this->get_platform_display_info($platform);
                                ?>
                                    <tr style="border-left: 4px solid <?php echo esc_attr($platform_info['color']); ?>; transition: all 0.3s ease;"
                                        onmouseover="this.style.backgroundColor='#f8f9fa'"
                                        onmouseout="this.style.backgroundColor=''">
                                        <th scope="row" class="check-column" style="padding: 15px;">
                                            <input type="checkbox" name="conversation_ids[]" value="<?php echo esc_attr($conversation['id']); ?>" style="transform: scale(1.2);">
                                        </th>
                                        <td style="padding: 15px;">
                                            <strong style="color: #007cba; font-weight: 600;">#<?php echo esc_html($conversation['id']); ?></strong>
                                        </td>
                                        <td style="padding: 15px;">
                                            <div style="display: flex; flex-direction: column;">
                                                <span style="font-weight: 500; color: #333;">
                                                    <?php echo date('Y-m-d', strtotime($conversation['created_at'])); ?>
                                                </span>
                                                <small style="color: #666; font-size: 12px;">
                                                    <?php echo date('H:i:s', strtotime($conversation['created_at'])); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td style="padding: 15px;">
                                            <span style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                <?php echo esc_html($message_count); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 15px;">
                                            <div style="display: flex; flex-direction: column;">
                                                <span style="font-weight: 500; color: #333;">
                                                    <?php echo human_time_diff(strtotime($conversation['updated_at']), current_time('timestamp')); ?> 前
                                                </span>
                                                <small style="color: #666; font-size: 12px;">
                                                    <?php echo date('m-d H:i', strtotime($conversation['updated_at'])); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td style="padding: 15px;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <i class="<?php echo esc_attr($platform_info['icon']); ?>" style="color: <?php echo esc_attr($platform_info['color']); ?>; font-size: 16px;"></i>
                                                <span style="font-weight: 500; color: #333;"><?php echo esc_html($platform_info['name']); ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 15px;">
                                            <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-size: 11px; color: #666;">
                                                <?php echo esc_html($conversation['user_ip'] ?? 'N/A'); ?>
                                            </code>
                                        </td>
                                        <td style="padding: 15px;">
                                            <div style="display: flex; gap: 5px;">
                                                <button type="button" class="button button-small" onclick="viewConversation('<?php echo esc_attr($conversation['conversation_id']); ?>')"
                                                        style="background: #007cba; color: white; border: none; padding: 6px 10px; border-radius: 4px; font-size: 11px;">
                                                    <i class="dashicons dashicons-visibility" style="font-size: 12px;"></i>
                                                    <?php _e('查看', 'ai-chat'); ?>
                                                </button>
                                                <button type="button" class="button button-small" onclick="deleteConversation(<?php echo esc_attr($conversation['id']); ?>)"
                                                        style="background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; font-size: 11px;">
                                                    <i class="dashicons dashicons-trash" style="font-size: 12px;"></i>
                                                    <?php _e('刪除', 'ai-chat'); ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                </div>

                <!-- 分頁導航 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper" style="background: #f8f9fa; border-top: 1px solid #eee; padding: 30px 40px; border-radius: 0 0 16px 16px;">
                        <div class="pagination-nav" style="display: flex; justify-content: center; align-items: center; gap: 8px;">
                            <?php
                            $pagination_args = array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '<i class="dashicons dashicons-arrow-left-alt2"></i> ' . __('上一頁', 'ai-chat'),
                                'next_text' => __('下一頁', 'ai-chat') . ' <i class="dashicons dashicons-arrow-right-alt2"></i>',
                                'total' => $total_pages,
                                'current' => $page,
                                'show_all' => false,
                                'end_size' => 1,
                                'mid_size' => 2,
                                'type' => 'array'
                            );

                            $pagination_links = paginate_links($pagination_args);

                            if ($pagination_links) {
                                foreach ($pagination_links as $link) {
                                    echo $link;
                                }
                            }
                            ?>
                        </div>

                        <div style="text-align: center; margin-top: 15px; color: #666; font-size: 14px;">
                            <?php echo sprintf(__('第 %d 頁，共 %d 頁 | 顯示 %d-%d 項，共 %d 項', 'ai-chat'),
                                $page,
                                $total_pages,
                                ($offset + 1),
                                min($offset + $per_page, $total_conversations),
                                $total_conversations
                            ); ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 對話查看模態框 -->
        <div id="conversation-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; backdrop-filter: blur(3px);">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 20px; max-width: 1000px; width: 95%; max-height: 90vh; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.3);">
                <!-- 模態框標題 -->
                <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px 40px; position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="margin: 0; font-size: 24px; font-weight: 500; display: flex; align-items: center; gap: 12px;">
                            <i class="dashicons dashicons-format-chat" style="font-size: 28px;"></i>
                            <?php _e('對話詳情', 'ai-chat'); ?>
                            <span style="background: rgba(255,255,255,0.2); padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: normal;" id="conversation-id-display"></span>
                        </h2>
                    </div>
                    <button onclick="closeConversationModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 50px; height: 50px; border-radius: 50%; font-size: 20px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;">
                        <i class="dashicons dashicons-no-alt"></i>
                    </button>
                    <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; opacity: 0.3;"></div>
                </div>

                <!-- 模態框內容 -->
                <div id="conversation-content" style="padding: 40px; max-height: 60vh; overflow-y: auto; background: #fafafa;">
                    <!-- 對話內容將通過 AJAX 載入 -->
                </div>

                <!-- 模態框底部 -->
                <div style="padding: 25px 40px; background: white; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                    <div style="color: #666; font-size: 14px;">
                        <i class="dashicons dashicons-info" style="margin-right: 5px;"></i>
                        <?php _e('點擊模態框外部或按 ESC 鍵關閉', 'ai-chat'); ?>
                    </div>
                    <button onclick="closeConversationModal()" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 12px 24px; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                        <?php _e('關閉', 'ai-chat'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php
        // 輸出 JavaScript 和 CSS
        $this->render_chat_history_styles_and_scripts();
    }



    /**
     * Get database size information
     */
    private function get_database_size_info($database) {
        $total_messages = method_exists($database, 'get_total_message_count') ? $database->get_total_message_count() : 0;
        $size_bytes = 1024 * 50; // 預設估計值
        
        // Format size
        if ($size_bytes < 1024) {
            $size_formatted = $size_bytes . ' B';
        } elseif ($size_bytes < 1024 * 1024) {
            $size_formatted = round($size_bytes / 1024, 1) . ' KB';
        } else {
            $size_formatted = round($size_bytes / (1024 * 1024), 1) . ' MB';
        }
        
        return array(
            'total_messages' => $total_messages,
            'size_bytes' => $size_bytes,
            'size_formatted' => $size_formatted,
            'last_activity' => '1 小時前'
        );
    }

    /**
     * Output admin styles
     */
    public function admin_styles() {
        ?>
        <style>
            .platform-config-item {
                font-size: 14px;
                flex: 1;
            }
            
            .platform-config {
                animation: fadeIn 0.4s ease-in-out;
            }
            
            .platform-config h3 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 2px solid #f0f0f1;
                font-size: 18px;
            }
            
            @keyframes fadeIn {
                from { 
                    opacity: 0; 
                    transform: translateY(15px); 
                }
                to { 
                    opacity: 1; 
                    transform: translateY(0); 
                }
            }
            
            .platform-quick-actions {
                display: flex;
                justify-content: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .platform-quick-actions .button {
                font-size: 12px;
                padding: 6px 12px;
                border-radius: 20px;
                border: 1px solid #ddd;
                background: white;
                transition: all 0.3s ease;
            }
            
            .platform-quick-actions .button:hover {
                background: #0073aa;
                color: white;
                border-color: #0073aa;
            }
            
            #platform-feedback {
                font-size: 14px;
                text-align: center;
                border-radius: 6px;
                transition: all 0.3s ease;
            }
            
            .platform-config-placeholder {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 200px;
            }
            
            .ai-chat-history .stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
                transition: all 0.3s ease;
            }
            
            .wp-list-table td, .wp-list-table th {
                padding: 12px 8px;
            }
            
            .button-small {
                font-size: 12px;
                padding: 4px 8px;
                margin-right: 4px;
            }
            
            /* 分頁樣式 */
            .pagination-wrapper {
                padding: 30px 40px;
                background: #f8f9fa;
                border-top: 1px solid #eee;
            }
            
            .pagination-nav {
                display: flex;
                gap: 8px;
                align-items: center;
            }
            
            .pagination-nav .page-numbers {
                background: white;
                border: 2px solid #ddd;
                color: #007cba;
                padding: 12px 16px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                transition: all 0.3s ease;
                display: inline-block;
                min-width: 44px;
                text-align: center;
            }
            
            .pagination-nav .page-numbers:hover {
                background-color: #007cba;
                color: white;
                border-color: #007cba;
                transform: translateY(-2px);
            }
            
            .pagination-nav .prev.page-numbers {
                background: white;
                border: 2px solid #ddd;
                color: #007cba;
                padding: 12px 16px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                transition: all 0.3s ease;
                display: inline-block;
            }
            
            .pagination-nav .next.page-numbers {
                background: white;
                border: 2px solid #ddd;
                color: #007cba;
                padding: 12px 16px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                transition: all 0.3s ease;
                display: inline-block;
            }
            
            .pagination-nav .page-numbers.current {
                background: linear-gradient(135deg, #007cba, #0056b3);
                border: 2px solid #007cba;
                color: white;
                padding: 12px 16px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                box-shadow: 0 4px 15px rgba(0,124,186,0.3);
                display: inline-block;
                min-width: 44px;
                text-align: center;
            }
            
            /* 高級對話查看模態框 */
            #conversation-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                z-index: 10000;
                backdrop-filter: blur(3px);
            }
            
            #conversation-modal .modal-content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border-radius: 20px;
                max-width: 1000px;
                width: 95%;
                max-height: 90vh;
                overflow: hidden;
                box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            }
            
            #conversation-modal .modal-header {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 30px 40px;
                position: relative;
                overflow: hidden;
            }
            
            #conversation-modal .modal-header:before {
                content: '';
                position: absolute;
                top: -50px;
                right: -50px;
                width: 200px;
                height: 200px;
                background: rgba(255,255,255,0.1);
                border-radius: 50%;
                opacity: 0.3;
            }
            
            #conversation-modal .modal-title {
                margin: 0;
                font-size: 24px;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            #conversation-modal .modal-title span {
                background: rgba(255,255,255,0.2);
                padding: 6px 16px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: normal;
            }
            
            #conversation-modal .modal-close {
                background: rgba(255,255,255,0.2);
                border: none;
                color: white;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                font-size: 20px;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            #conversation-modal .modal-close:hover {
                background-color: rgba(255,255,255,0.3);
                transform: scale(1.1);
            }
            
            #conversation-modal .modal-content {
                padding: 40px;
                max-height: 60vh;
                overflow-y: auto;
                background: #fafafa;
            }
            
            #conversation-modal .modal-footer {
                padding: 25px 40px;
                background: white;
                border-top: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            #conversation-modal .modal-footer .button {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            #conversation-modal .modal-footer .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(102,126,234,0.3);
            }
            
            /* 現代化樣式系統 */
        
        /* 全局設定 */
        .wrap.ai-chat-history {
            max-width: none !important;
            margin: 20px 20px 20px 0 !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
        }
        
        /* 頁面標題區域 */
        .page-header {
            position: relative;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        
        /* 統計卡片動畫 */
        .modern-card {
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .modern-card:hover {
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }
        
        /* 工具卡片動畫 */
        .tool-card {
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }
        
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        /* 對話表格樣式 */
        .conversations-table table {
            border-spacing: 0;
        }
        
        .conversations-table tbody tr {
            border-left: 4px solid transparent;
        }
        
        /* 批量操作區域 */
        .batch-operations {
            transition: all 0.3s ease;
        }
        
        .batch-operations:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        /* 模態框動畫 */
        #conversation-modal {
            animation: fadeIn 0.3s ease;
        }
        
        #conversation-modal > div {
            animation: slideIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translate(-50%, -60%) scale(0.9); opacity: 0; }
            to { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }
        
        /* 載入動畫 */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-spinner {
            border: 3px solid rgba(0,124,186,0.3);
            border-top-color: #007cba;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        /* 響應式設計 */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        
        @media (max-width: 768px) {
            .wrap.ai-chat-history {
                margin: 10px !important;
            }
            
            .page-header {
                padding: 25px 20px !important;
                text-align: center;
            }
            
            .page-header h1 {
                font-size: 24px !important;
            }
            
            .stats-grid {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
            }
            
            .modern-card {
                padding: 25px 20px !important;
            }
            
            .batch-operations {
                flex-direction: column !important;
                gap: 15px !important;
            }
            
            /* 移動端表格卡片化 */
            .conversations-table table,
            .conversations-table thead,
            .conversations-table tbody,
            .conversations-table th,
            .conversations-table td,
            .conversations-table tr {
                display: block;
            }
            
            .conversations-table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            .conversations-table tbody tr {
                border: 1px solid #ddd;
                border-radius: 12px;
                margin-bottom: 15px;
                padding: 20px;
                background: white;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            }
            
            .conversations-table td {
                border: none;
                position: relative;
                padding: 12px 0 12px 40% !important;
                text-align: left;
            }
            
            .conversations-table td:before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 0;
                width: 35%;
                font-weight: 600;
                color: #333;
                font-size: 14px;
            }
            
            /* 為移動端添加數據標籤 */
            .conversations-table td:nth-child(1):before { content: "<?php echo esc_js(__('選擇: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(2):before { content: "<?php echo esc_js(__('對話ID: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(3):before { content: "<?php echo esc_js(__('開始時間: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(4):before { content: "<?php echo esc_js(__('訊息數: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(5):before { content: "<?php echo esc_js(__('最後活動: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(6):before { content: "<?php echo esc_js(__('平台: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(7):before { content: "<?php echo esc_js(__('IP地址: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(8):before { content: "<?php echo esc_js(__('操作: ', 'ai-chat')); ?>"; }
            
            #conversation-modal > div {
                width: 95% !important;
                max-height: 95vh !important;
                margin: 20px !important;
            }
        }
        </style>


        
        <?php
    }

    /**
     * Get platform display information
     */
    private function get_platform_display_info($platform) {
        $platforms = array(
            'ai-chat' => array('name' => 'AI 聊天', 'icon' => 'dashicons-format-chat', 'color' => '#007cba'),
            'whatsapp' => array('name' => 'WhatsApp', 'icon' => 'dashicons-whatsapp', 'color' => '#25d366'),
            'facebook' => array('name' => 'Facebook', 'icon' => 'dashicons-facebook', 'color' => '#0084ff'),
            'line' => array('name' => 'LINE', 'icon' => 'dashicons-admin-links', 'color' => '#00c300'),
            'telegram' => array('name' => 'Telegram', 'icon' => 'dashicons-admin-comments', 'color' => '#0088cc'),
            'wechat' => array('name' => 'WeChat', 'icon' => 'dashicons-admin-users', 'color' => '#7bb32e'),
            'qq' => array('name' => 'QQ', 'icon' => 'dashicons-admin-users', 'color' => '#eb1923'),
            'instagram' => array('name' => 'Instagram', 'icon' => 'dashicons-camera', 'color' => '#e4405f'),
            'twitter' => array('name' => 'Twitter/X', 'icon' => 'dashicons-twitter', 'color' => '#1da1f2'),
            'discord' => array('name' => 'Discord', 'icon' => 'dashicons-admin-comments', 'color' => '#7289da'),
            'slack' => array('name' => 'Slack', 'icon' => 'dashicons-admin-comments', 'color' => '#4a154b'),
            'teams' => array('name' => 'Teams', 'icon' => 'dashicons-admin-users', 'color' => '#6264a7'),
            'email' => array('name' => 'Email', 'icon' => 'dashicons-email', 'color' => '#dc3545'),
            'phone' => array('name' => 'Phone', 'icon' => 'dashicons-phone', 'color' => '#28a745'),
        );

        return $platforms[$platform] ?? array('name' => ucfirst($platform), 'icon' => 'dashicons-admin-generic', 'color' => '#666666');
    }

    /**
     * Render chat history styles and scripts
     */
    private function render_chat_history_styles_and_scripts() {
        ?>
        <style>
        /* Chat History 專用樣式 */
        .ai-chat-history .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12) !important;
        }

        .conversations-table tbody tr:hover {
            background-color: #f8f9fa !important;
            transform: translateX(5px);
        }

        .pagination-nav .page-numbers {
            background: white;
            border: 2px solid #ddd;
            color: #007cba;
            padding: 12px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            min-width: 44px;
            text-align: center;
        }

        .pagination-nav .page-numbers:hover {
            background-color: #007cba;
            color: white;
            border-color: #007cba;
            transform: translateY(-2px);
        }

        .pagination-nav .page-numbers.current {
            background: linear-gradient(135deg, #007cba, #0056b3);
            border: 2px solid #007cba;
            color: white;
            box-shadow: 0 4px 15px rgba(0,124,186,0.3);
        }

        /* 響應式設計 */
        @media (max-width: 768px) {
            .conversations-table table,
            .conversations-table thead,
            .conversations-table tbody,
            .conversations-table th,
            .conversations-table td,
            .conversations-table tr {
                display: block;
            }

            .conversations-table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            .conversations-table tbody tr {
                border: 1px solid #ddd;
                border-radius: 12px;
                margin-bottom: 15px;
                padding: 20px;
                background: white;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            }

            .conversations-table td {
                border: none;
                position: relative;
                padding: 12px 0 12px 40% !important;
                text-align: left;
            }

            .conversations-table td:before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 0;
                width: 35%;
                font-weight: 600;
                color: #333;
                font-size: 14px;
            }

            .conversations-table td:nth-child(1):before { content: "<?php echo esc_js(__('選擇: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(2):before { content: "<?php echo esc_js(__('對話ID: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(3):before { content: "<?php echo esc_js(__('開始時間: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(4):before { content: "<?php echo esc_js(__('訊息數: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(5):before { content: "<?php echo esc_js(__('最後活動: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(6):before { content: "<?php echo esc_js(__('平台: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(7):before { content: "<?php echo esc_js(__('IP地址: ', 'ai-chat')); ?>"; }
            .conversations-table td:nth-child(8):before { content: "<?php echo esc_js(__('操作: ', 'ai-chat')); ?>"; }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // 全選功能
            $('#cb-select-all').change(function() {
                const checked = $(this).prop('checked');
                $('input[name="conversation_ids[]"]').prop('checked', checked);
                updateSelectedCount();
            });

            // 子選框變更時更新全選狀態
            $(document).on('change', 'input[name="conversation_ids[]"]', function() {
                const total = $('input[name="conversation_ids[]"]').length;
                const checked = $('input[name="conversation_ids[]"]:checked').length;
                $('#cb-select-all').prop('checked', total === checked);
                updateSelectedCount();
            });

            // 初始化選中計數
            updateSelectedCount();
        });

        // 更新選中數量顯示
        function updateSelectedCount() {
            const count = jQuery('input[name="conversation_ids[]"]:checked').length;
            jQuery('#selected-count').text(count);
            jQuery('#selected-count-display').text(count);

            // 顯示/隱藏批量操作區域
            if (count > 0) {
                jQuery('#batch-operations').show();
                jQuery('#bulk-delete-btn').prop('disabled', false);
            } else {
                jQuery('#batch-operations').hide();
                jQuery('#bulk-delete-btn').prop('disabled', true);
            }
        }

        // 全選功能
        function selectAll() {
            jQuery('input[name="conversation_ids[]"]').prop('checked', true);
            jQuery('#cb-select-all').prop('checked', true);
            updateSelectedCount();
        }

        // 取消全選
        function selectNone() {
            jQuery('input[name="conversation_ids[]"]').prop('checked', false);
            jQuery('#cb-select-all').prop('checked', false);
            updateSelectedCount();
        }

        // 確認批量刪除
        function confirmBulkDelete() {
            const count = jQuery('input[name="conversation_ids[]"]:checked').length;
            if (count === 0) {
                alert('<?php echo esc_js(__('請先選擇要刪除的對話記錄。', 'ai-chat')); ?>');
                return false;
            }

            return confirm(`<?php echo esc_js(__('⚠️ 確定要刪除選中的', 'ai-chat')); ?> ${count} <?php echo esc_js(__('個對話嗎？\\n\\n此操作將永久刪除這些對話的所有訊息記錄，無法撤銷。', 'ai-chat')); ?>`);
        }

        function viewConversation(conversationId) {
            // 顯示模態框
            document.getElementById('conversation-modal').style.display = 'block';
            document.getElementById('conversation-id-display').textContent = '#' + conversationId;
            document.getElementById('conversation-content').innerHTML = '<div style="text-align: center; padding: 40px;"><div class="loading-spinner"></div><p>載入對話內容中...</p></div>';

            // AJAX 請求載入對話內容
            jQuery.post(ajaxurl, {
                action: 'get_conversation_messages',
                conversation_id: conversationId,
                nonce: '<?php echo wp_create_nonce('get_conversation_messages'); ?>'
            }, function(response) {
                if (response.success) {
                    document.getElementById('conversation-content').innerHTML = response.data.html;
                } else {
                    document.getElementById('conversation-content').innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="dashicons dashicons-warning" style="font-size: 48px; margin-bottom: 15px;"></i><p>載入失敗：' + (response.data.message || '未知錯誤') + '</p></div>';
                }
            }).fail(function() {
                document.getElementById('conversation-content').innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="dashicons dashicons-warning" style="font-size: 48px; margin-bottom: 15px;"></i><p>網路錯誤，請稍後再試</p></div>';
            });
        }

        function closeConversationModal() {
            const modal = document.getElementById('conversation-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // 確保函數在全局作用域中可用
        window.closeConversationModal = closeConversationModal;

        function deleteConversation(conversationId) {
            if (confirm('<?php echo esc_js(__('⚠️ 確定要刪除這個對話嗎？\\n\\n此操作將永久刪除該對話的所有訊息記錄，無法撤銷。', 'ai-chat')); ?>')) {
                // 通過表單提交刪除
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_conversations">
                    <input type="hidden" name="conversation_ids[]" value="${conversationId}">
                    <?php echo wp_nonce_field('bulk_delete_conversations', '_wpnonce', true, false); ?>
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // 點擊模態框外部關閉
        document.getElementById('conversation-modal').onclick = function(e) {
            if (e.target === this) {
                closeConversationModal();
            }
        };

        // ESC 鍵關閉模態框
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeConversationModal();
            }
        });
        </script>

        <?php
    }

    /**
     * Data Sources admin page
     */
    public function data_sources_page() {
        $database = new AI_Chat_Database();

        // Handle form submissions
        if (isset($_POST['action'])) {
            // Handle sitemap management
            if ($_POST['action'] === 'add_sitemap' && wp_verify_nonce($_POST['_wpnonce'], 'add_sitemap')) {
                $sitemap_url = sanitize_url($_POST['sitemap_url'] ?? '');
                $sitemap_title = sanitize_text_field($_POST['sitemap_title'] ?? '');

                if (!empty($sitemap_url)) {
                    $sitemaps = get_option('ai_chat_sitemaps', array());
                    $sitemaps[] = array(
                        'url' => $sitemap_url,
                        'title' => $sitemap_title ?: '未命名 Sitemap',
                        'added_at' => current_time('mysql')
                    );
                    update_option('ai_chat_sitemaps', $sitemaps);
                    echo '<div class="notice notice-success"><p style="color: #000;">' . __('Sitemap 添加成功！', 'ai-chat') . '</p></div>';
                }
            }

            if ($_POST['action'] === 'delete_sitemap' && wp_verify_nonce($_POST['_wpnonce'], 'delete_sitemap')) {
                $sitemap_index = intval($_POST['sitemap_index'] ?? -1);
                if ($sitemap_index >= 0) {
                    $sitemaps = get_option('ai_chat_sitemaps', array());
                    if (isset($sitemaps[$sitemap_index])) {
                        unset($sitemaps[$sitemap_index]);
                        $sitemaps = array_values($sitemaps); // 重新索引
                        update_option('ai_chat_sitemaps', $sitemaps);
                        echo '<div class="notice notice-success"><p style="color: #000;">' . __('Sitemap 刪除成功！', 'ai-chat') . '</p></div>';
                    }
                }
            }

            if ($_POST['action'] === 'force_update_database' && wp_verify_nonce($_POST['_wpnonce'], 'force_update_database')) {
                try {
                    AI_Chat_Database::create_tables();
                    echo '<div class="notice notice-success"><p>' . __('數據庫表更新成功！', 'ai-chat') . '</p></div>';
                } catch (Exception $e) {
                    echo '<div class="notice notice-error"><p>' . __('數據庫更新失敗: ', 'ai-chat') . $e->getMessage() . '</p></div>';
                }
            }

            if ($_POST['action'] === 'add_data_source' && wp_verify_nonce($_POST['_wpnonce'], 'add_data_source')) {
                $url = sanitize_url($_POST['url'] ?? '');
                $title = sanitize_text_field($_POST['title'] ?? '');

                if (!empty($url)) {
                    $result = $database->add_data_source($url, $title);
                    if ($result) {
                        echo '<div class="notice notice-success"><p style="color: #000;">' . __('數據源添加成功！', 'ai-chat') . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p style="color: #000;">' . __('添加失敗，URL可能已存在或格式無效。', 'ai-chat') . '</p></div>';
                    }
                }
            }

            if ($_POST['action'] === 'delete_data_source' && wp_verify_nonce($_POST['_wpnonce'], 'delete_data_source')) {
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    $result = $database->delete_data_source($id);
                    if ($result) {
                        echo '<div class="notice notice-success"><p style="color: #000;">' . __('數據源刪除成功！', 'ai-chat') . '</p></div>';
                    }
                }
            }

            if ($_POST['action'] === 'clear_all_data_sources' && wp_verify_nonce($_POST['_wpnonce'], 'clear_all_data_sources')) {
                $count = $database->clear_all_data_sources();
                echo '<div class="notice notice-success"><p style="color: #000;">' . sprintf(__('已清除 %d 個數據源！', 'ai-chat'), $count) . '</p></div>';
            }

            if ($_POST['action'] === 'save_ai_custom_text' && wp_verify_nonce($_POST['_wpnonce'], 'save_ai_custom_text')) {
                $custom_text = sanitize_textarea_field($_POST['ai_custom_text'] ?? '');
                update_option('ai_chat_custom_text', $custom_text);
                echo '<div class="notice notice-success"><p style="color: #000;">' . __('AI 指導文字保存成功！', 'ai-chat') . '</p></div>';
            }
        }

        // Pagination
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // Check if data sources table exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chat_data_sources';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $table_missing = ($table_exists != $table_name);

        // Get data sources (only if table exists)
        $data_sources = array();
        $total_sources = 0;
        $total_pages = 0;

        if (!$table_missing) {
            $data_sources = $database->get_data_sources($per_page, $offset);
            $total_sources = $database->get_data_sources_count();
            $total_pages = ceil($total_sources / $per_page);
        }

        ?>
        <div class="wrap ai-chat-data-sources">
            <!-- 頁面標題區域 -->
            <div class="page-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 20px; margin-bottom: 30px; position: relative; overflow: hidden;">
                <div style="position: relative; z-index: 2;">
                    <h1 style="margin: 0 0 10px 0; font-size: 32px; font-weight: 300;">
                        <i class="dashicons dashicons-admin-links" style="font-size: 36px; margin-right: 15px; vertical-align: middle;"></i>
                        <?php _e('AI獲取資料', 'ai-chat'); ?>
                    </h1>
                    <p style="margin: 0; font-size: 16px; opacity: 0.9;">
                        <?php _e('管理AI助手可以讀取的外部數據源連結', 'ai-chat'); ?>
                    </p>
                </div>
                <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; opacity: 0.3;"></div>
            </div>

            <!-- Sitemap 管理區域 -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px;">
                <h2 style="margin-top: 0; color: #e74c3c; border-bottom: 2px solid #e74c3c; padding-bottom: 10px;">
                    <i class="dashicons dashicons-networking" style="margin-right: 8px;"></i>
                    <?php _e('🗺️ Sitemap 管理（產品推薦數據源）', 'ai-chat'); ?>
                </h2>

                <p style="color: #666; margin-bottom: 20px;">
                    <?php _e('添加您的產品 sitemap 網址，AI 將從這些 sitemap 中抓取正確的產品連結進行推薦', 'ai-chat'); ?>
                </p>

                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('add_sitemap', '_wpnonce'); ?>
                    <input type="hidden" name="action" value="add_sitemap">

                    <div style="display: flex; gap: 15px; align-items: end;">
                        <div style="flex: 1;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                <?php _e('Sitemap 網址', 'ai-chat'); ?>
                            </label>
                            <input type="url" name="sitemap_url" placeholder="https://example.com/product-sitemap.xml"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" required>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                <?php _e('標題（可選）', 'ai-chat'); ?>
                            </label>
                            <input type="text" name="sitemap_title" placeholder="<?php _e('產品 Sitemap', 'ai-chat'); ?>"
                                   style="width: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        </div>
                        <button type="submit" class="button button-primary" style="background: #e74c3c; border-color: #e74c3c; padding: 10px 20px; height: auto;">
                            <i class="dashicons dashicons-plus-alt" style="margin-right: 5px;"></i>
                            <?php _e('添加 Sitemap', 'ai-chat'); ?>
                        </button>
                    </div>
                </form>

                <!-- 顯示現有的 sitemaps -->
                <?php
                $sitemaps = get_option('ai_chat_sitemaps', array());
                if (!empty($sitemaps)) {
                    echo '<div style="border-top: 1px solid #eee; padding-top: 20px;">';
                    echo '<h3 style="margin-bottom: 15px;">' . __('已添加的 Sitemaps', 'ai-chat') . '</h3>';
                    echo '<div style="display: grid; gap: 10px;">';

                    foreach ($sitemaps as $index => $sitemap) {
                        echo '<div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #e74c3c;">';
                        echo '<div>';
                        echo '<strong>' . esc_html($sitemap['title'] ?: '未命名 Sitemap') . '</strong><br>';
                        echo '<small style="color: #666;">' . esc_html($sitemap['url']) . '</small>';
                        echo '</div>';
                        echo '<form method="post" style="margin: 0;">';
                        wp_nonce_field('delete_sitemap', '_wpnonce');
                        echo '<input type="hidden" name="action" value="delete_sitemap">';
                        echo '<input type="hidden" name="sitemap_index" value="' . $index . '">';
                        echo '<button type="submit" class="button button-small" style="color: #dc3545;" onclick="return confirm(\'' . __('確定要刪除這個 Sitemap 嗎？', 'ai-chat') . '\')">';
                        echo '<i class="dashicons dashicons-trash"></i> ' . __('刪除', 'ai-chat');
                        echo '</button>';
                        echo '</form>';
                        echo '</div>';
                    }

                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- 統計卡片 -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="modern-card" style="background: white; padding: 25px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-left: 4px solid #667eea;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0 0 5px 0; color: #333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?php _e('總數據源', 'ai-chat'); ?>
                            </h3>
                            <p style="margin: 0; font-size: 32px; font-weight: 700; color: #667eea;">
                                <?php echo number_format($total_sources); ?>
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="dashicons dashicons-admin-links" style="font-size: 24px;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 數據庫狀態檢查 -->
            <?php if ($table_missing): ?>
                <div class="modern-card" style="background: #fff3cd; border: 2px solid #ffc107; padding: 30px; border-radius: 16px; margin-bottom: 30px;">
                    <h2 style="margin: 0 0 15px 0; color: #856404; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                        <i class="dashicons dashicons-warning" style="color: #ffc107; font-size: 24px;"></i>
                        <?php _e('數據庫表缺失', 'ai-chat'); ?>
                    </h2>
                    <p style="margin: 0 0 20px 0; color: #856404;">
                        <?php _e('ai_chat_data_sources 數據表不存在，需要創建才能使用數據源功能。', 'ai-chat'); ?>
                    </p>
                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('force_update_database'); ?>
                        <input type="hidden" name="action" value="force_update_database">
                        <button type="submit" class="button button-primary" style="background: #ffc107; border-color: #ffc107; color: #212529; font-weight: 600;">
                            <i class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></i>
                            <?php _e('修復數據庫', 'ai-chat'); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- 添加新數據源表單 -->
            <?php if (!$table_missing): ?>
            <div class="modern-card" style="background: white; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 30px;">
                <h2 style="margin: 0 0 20px 0; color: #333; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    <i class="dashicons dashicons-plus-alt" style="color: #667eea;"></i>
                    <?php _e('添加新數據源', 'ai-chat'); ?>
                </h2>

                <form method="post" style="display: grid; grid-template-columns: 1fr 200px auto; gap: 15px; align-items: end;">
                    <?php wp_nonce_field('add_data_source'); ?>
                    <input type="hidden" name="action" value="add_data_source">

                    <div>
                        <label for="data_source_url" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                            <?php _e('URL 連結', 'ai-chat'); ?>
                        </label>
                        <input type="url" id="data_source_url" name="url" required
                               placeholder="https://example.com/page"
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                    </div>

                    <div>
                        <label for="data_source_title" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                            <?php _e('標題 (可選)', 'ai-chat'); ?>
                        </label>
                        <input type="text" id="data_source_title" name="title"
                               placeholder="<?php esc_attr_e('自動獲取', 'ai-chat'); ?>"
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                    </div>

                    <button type="submit" class="button button-primary" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; height: fit-content;">
                        <i class="dashicons dashicons-plus-alt" style="margin-right: 5px;"></i>
                        <?php _e('添加', 'ai-chat'); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- 數據源列表 -->
            <?php if (!$table_missing && !empty($data_sources)): ?>
                <div class="modern-card" style="background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden;">
                    <div style="padding: 30px 40px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; color: #333; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                            <i class="dashicons dashicons-list-view" style="color: #667eea;"></i>
                            <?php _e('數據源列表', 'ai-chat'); ?>
                        </h2>

                        <!-- 清除全部按鈕 -->
                        <form method="post" style="display: inline-block;" onsubmit="return confirm('<?php echo esc_js(__('⚠️ 確定要清除所有數據源嗎？\\n\\n此操作將永久刪除所有數據源，無法撤銷！', 'ai-chat')); ?>')">
                            <?php wp_nonce_field('clear_all_data_sources'); ?>
                            <input type="hidden" name="action" value="clear_all_data_sources">
                            <button type="submit" class="button" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 12px;">
                                <i class="dashicons dashicons-dismiss" style="font-size: 14px; margin-right: 5px;"></i>
                                <?php _e('清除全部', 'ai-chat'); ?>
                            </button>
                        </form>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="wp-list-table widefat fixed striped" style="border: none;">
                            <thead>
                                <tr>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333; width: 50px;">
                                        <?php _e('ID', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-admin-links" style="margin-right: 5px;"></i>
                                        <?php _e('URL', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-format-aside" style="margin-right: 5px;"></i>
                                        <?php _e('標題', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333;">
                                        <i class="dashicons dashicons-clock" style="margin-right: 5px;"></i>
                                        <?php _e('添加時間', 'ai-chat'); ?>
                                    </th>
                                    <th scope="col" style="padding: 15px; font-weight: 600; color: #333; width: 100px;">
                                        <i class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></i>
                                        <?php _e('操作', 'ai-chat'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_sources as $source): ?>
                                    <tr style="transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor=''">
                                        <td style="padding: 15px;">
                                            <strong style="color: #007cba; font-weight: 600;">#<?php echo esc_html($source['id']); ?></strong>
                                        </td>
                                        <td style="padding: 15px;">
                                            <a href="<?php echo esc_url($source['url']); ?>" target="_blank" style="color: #007cba; text-decoration: none; word-break: break-all;">
                                                <?php echo esc_html($source['url']); ?>
                                                <i class="dashicons dashicons-external" style="font-size: 12px; margin-left: 5px;"></i>
                                            </a>
                                        </td>
                                        <td style="padding: 15px;">
                                            <?php echo esc_html($source['title'] ?: __('無標題', 'ai-chat')); ?>
                                        </td>
                                        <td style="padding: 15px;">
                                            <div style="display: flex; flex-direction: column;">
                                                <span style="font-weight: 500; color: #333;">
                                                    <?php echo date('Y-m-d', strtotime($source['created_at'])); ?>
                                                </span>
                                                <small style="color: #666; font-size: 12px;">
                                                    <?php echo date('H:i:s', strtotime($source['created_at'])); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td style="padding: 15px;">
                                            <form method="post" style="display: inline-block;" onsubmit="return confirm('<?php echo esc_js(__('確定要刪除這個數據源嗎？', 'ai-chat')); ?>')">
                                                <?php wp_nonce_field('delete_data_source'); ?>
                                                <input type="hidden" name="action" value="delete_data_source">
                                                <input type="hidden" name="id" value="<?php echo esc_attr($source['id']); ?>">
                                                <button type="submit" class="button button-small" style="background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; font-size: 11px;">
                                                    <i class="dashicons dashicons-trash" style="font-size: 12px;"></i>
                                                    <?php _e('刪除', 'ai-chat'); ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- 分頁導航 -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper" style="background: #f8f9fa; border-top: 1px solid #eee; padding: 30px 40px; border-radius: 0 0 16px 16px;">
                            <div class="pagination-nav" style="display: flex; justify-content: center; align-items: center; gap: 8px;">
                                <?php
                                $pagination_args = array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '<i class="dashicons dashicons-arrow-left-alt2"></i> ' . __('上一頁', 'ai-chat'),
                                    'next_text' => __('下一頁', 'ai-chat') . ' <i class="dashicons dashicons-arrow-right-alt2"></i>',
                                    'total' => $total_pages,
                                    'current' => $page,
                                    'show_all' => false,
                                    'end_size' => 1,
                                    'mid_size' => 2,
                                    'type' => 'array'
                                );

                                $pagination_links = paginate_links($pagination_args);

                                if ($pagination_links) {
                                    foreach ($pagination_links as $link) {
                                        echo $link;
                                    }
                                }
                                ?>
                            </div>

                            <div style="text-align: center; margin-top: 15px; color: #666; font-size: 14px;">
                                <?php echo sprintf(__('第 %d 頁，共 %d 頁 | 顯示 %d-%d 項，共 %d 項', 'ai-chat'),
                                    $page,
                                    $total_pages,
                                    ($offset + 1),
                                    min($offset + $per_page, $total_sources),
                                    $total_sources
                                ); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif (!$table_missing): ?>
                <div class="modern-card" style="background: white; padding: 60px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center;">
                    <div style="color: #999; font-size: 48px; margin-bottom: 20px;">
                        <i class="dashicons dashicons-admin-links"></i>
                    </div>
                    <h3 style="margin: 0 0 10px 0; color: #666; font-size: 18px;">
                        <?php _e('尚未添加任何數據源', 'ai-chat'); ?>
                    </h3>
                    <p style="margin: 0; color: #999; font-size: 14px;">
                        <?php _e('請使用上方表單添加第一個數據源連結', 'ai-chat'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- 自定義 AI 指導文字 -->
            <div class="modern-card" style="background: white; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-top: 30px;">
                <h2 style="margin: 0 0 20px 0; color: #333; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    <i class="dashicons dashicons-edit" style="color: #667eea;"></i>
                    <?php _e('自定義 AI 指導文字', 'ai-chat'); ?>
                </h2>
                <p style="margin: 0 0 20px 0; color: #666; font-size: 14px;">
                    <?php _e('在此輸入額外的文字內容來指導 AI 助手的回應。這些內容將與網站數據一起提供給 AI。', 'ai-chat'); ?>
                </p>

                <form method="post">
                    <?php wp_nonce_field('save_ai_custom_text'); ?>
                    <input type="hidden" name="action" value="save_ai_custom_text">

                    <div style="margin-bottom: 20px;">
                        <label for="ai_custom_text" style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">
                            <?php _e('AI 指導文字', 'ai-chat'); ?>
                        </label>
                        <textarea id="ai_custom_text" name="ai_custom_text" rows="8"
                                  style="width: 100%; padding: 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; font-family: monospace; line-height: 1.5; resize: vertical;"
                                  placeholder="<?php esc_attr_e('例如：我們是專業的泳衣零售商，主要銷售 FUNKTIA、TYR 等品牌的泳衣產品。我們提供專業的游泳裝備建議和優質的客戶服務...', 'ai-chat'); ?>"><?php echo esc_textarea(get_option('ai_chat_custom_text', '')); ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="color: #666; font-size: 12px;">
                            <?php _e('提示：這些文字將幫助 AI 更好地了解您的業務和產品，提供更準確的回應。', 'ai-chat'); ?>
                        </div>
                        <button type="submit" id="ai-chat-save-custom-text-btn" class="button button-primary" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600;">
                            <i class="dashicons dashicons-saved" style="margin-right: 5px;"></i>
                            <?php _e('保存設定', 'ai-chat'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <style>
        .ai-chat-data-sources input[type="url"]:focus,
        .ai-chat-data-sources input[type="text"]:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
            outline: none !important;
        }

        .ai-chat-data-sources .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .ai-chat-data-sources .pagination-nav .page-numbers {
            background: white;
            border: 2px solid #ddd;
            color: #007cba;
            padding: 12px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            min-width: 44px;
            text-align: center;
        }

        .ai-chat-data-sources .pagination-nav .page-numbers:hover {
            background-color: #007cba;
            color: white;
            border-color: #007cba;
            transform: translateY(-2px);
        }

        .ai-chat-data-sources .pagination-nav .page-numbers.current {
            background: linear-gradient(135deg, #007cba, #0056b3);
            border: 2px solid #007cba;
            color: white;
            box-shadow: 0 4px 15px rgba(0,124,186,0.3);
        }
        </style>
        <?php
    }
}
