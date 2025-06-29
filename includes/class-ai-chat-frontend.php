<?php
/**
 * Frontend functionality for AI Chat Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Frontend {
    
    private $settings;
      public function __construct() {
        // Always add actions, but check enabled platforms in the methods
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_chat_widget'));
    }

    /**
     * Get current settings (always fresh)
     */
    private function get_settings() {
        return get_option('ai_chat_settings', array());
    }
      /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Skip if on admin pages
        if (is_admin()) {
            return;
        }

        // Get fresh settings
        $settings = $this->get_settings();

        // Check if any platforms are enabled
        $enabled_platforms = $settings['enabled_platforms'] ?? array();
        if (empty($enabled_platforms)) {
            return;
        }

        // Check if should display on mobile
        if (!($settings['display_on_mobile'] ?? true) && wp_is_mobile()) {
            return;
        }
          wp_enqueue_script('ai-chat-frontend', AI_CHAT_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), AI_CHAT_VERSION . '.' . time(), true);
        wp_enqueue_style('ai-chat-frontend', AI_CHAT_PLUGIN_URL . 'assets/css/frontend.css', array(), AI_CHAT_VERSION . '.' . time());
        
        // Font Awesome for icons
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0');
          // Localize script
        wp_localize_script('ai-chat-frontend', 'aiChatFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chat_frontend_nonce'),
            'settings' => array(
                'enabled_platforms' => $settings['enabled_platforms'] ?? array('ai-chat'),
                'bubble_mode' => !empty($settings['bubble_mode']),
                'chat_color' => $settings['chat_color'] ?? '#007cba',
                'chat_position' => $settings['button_position'] ?? 'bottom-right',
                'chat_size' => $settings['button_size'] ?? $settings['chat_size'] ?? 'medium',
                'animation_enabled' => !empty($settings['animation_enabled'])
            ),
            'strings' => array(
                'typing' => __('請不要離開或刷新, AI正在思考中..., ', 'ai-chat'),
                'error' => __('抱歉，發生錯誤。請重試。', 'ai-chat'),
                'placeholder' => __('請輸入您的信息...', 'ai-chat'),
                'send' => __('發送', 'ai-chat'),
                'minimize' => __('最小化', 'ai-chat'),
                'maximize' => __('最大化', 'ai-chat'),
                'fullscreen' => __('全螢幕', 'ai-chat'),
                'exit_fullscreen' => __('退出全螢幕', 'ai-chat'),
                'close' => __('關閉', 'ai-chat'),
                'welcome' => $this->get_welcome_message()
            ),
            'currentUrl' => get_permalink(),
            'siteTitle' => get_bloginfo('name'),
            'siteDescription' => get_bloginfo('description')
        ));
    }
    
    /**
     * Render chat widget in footer
     */
    public function render_chat_widget() {
        // Get fresh settings
        $settings = $this->get_settings();

        if (is_admin() || (!($settings['display_on_mobile'] ?? true) && wp_is_mobile())) {
            return;
        }

        $enabled_platforms = $settings['enabled_platforms'] ?? array();
        if (empty($enabled_platforms)) {
            return;
        }

        $position = $settings['button_position'] ?? 'bottom-right';
        $size = $settings['button_size'] ?? $settings['chat_size'] ?? 'medium';
        // 支持兩種字段名稱以保持兼容性
        $color = $settings['chat_color'] ?? $settings['button_color'] ?? '#007cba';
        $animation = $settings['animation_enabled'] ?? true;
        $bottom_distance = $settings['bottom_distance'] ?? '20';
        $top_distance = $settings['top_distance'] ?? '20';

        // Calculate hover color (darker version)
        $hover_color = $this->darken_color($color, 20);

        // Build CSS variables
        $css_vars = array(
            '--chat-color: ' . esc_attr($color),
            '--chat-hover-color: ' . esc_attr($hover_color),
            '--chat-primary-color: ' . esc_attr($color),
            '--chat-bottom-distance: ' . esc_attr($bottom_distance) . 'px',
            '--chat-top-distance: ' . esc_attr($top_distance) . 'px'
        );
        ?>
        <div id="ai-chat-widget" class="ai-chat-widget <?php echo esc_attr($position); ?> <?php echo esc_attr($size); ?> <?php echo $animation ? 'animated' : ''; ?>" style="<?php echo implode('; ', $css_vars); ?>;">
              <!-- Chat Toggle Button -->
            <div class="chat-toggle" id="chat-toggle" <?php if (count($enabled_platforms) == 1): ?>data-platform="<?php echo esc_attr($enabled_platforms[0]); ?>"<?php endif; ?>>
                <div class="chat-icon">
                    <?php if (count($enabled_platforms) == 1): ?>
                        <?php
                        $single_platform = $enabled_platforms[0];
                        $platforms = $this->get_platform_config();
                        $config = $platforms[$single_platform] ?? array();
                        ?>
                        <i class="<?php echo esc_attr($config['icon'] ?? 'fas fa-comments'); ?>"></i>
                    <?php else: ?>
                        <i class="fas fa-comments"></i>
                        <span class="chat-count"><?php echo count($enabled_platforms); ?></span>
                    <?php endif; ?>
                </div>
            </div>
              <!-- Platform Bubbles for Multi-Platform -->
            <?php if (count($enabled_platforms) > 1): ?>
                <div class="platform-bubbles" id="platform-bubbles">
                    <?php foreach ($enabled_platforms as $platform): ?>
                        <?php $this->render_platform_bubble($platform); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Platform Selection Menu -->
            <?php if (count($enabled_platforms) > 1): ?>
                <div class="platform-menu" id="platform-menu">
                    <div class="platform-list">
                        <?php foreach ($enabled_platforms as $platform): ?>
                            <?php $this->render_platform_item($platform); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- AI Chat Window -->
            <?php if (in_array('ai-chat', $enabled_platforms)): ?>
                <div class="chat-window" id="ai-chat-window">
                    <div class="chat-header">
                        <div class="chat-title-container">
                            <div class="chat-title">
                                <i class="fas fa-robot"></i>
                                <span><?php _e('AI智能助手', 'ai-chat'); ?></span>
                            </div>
                            <div class="chat-subtitle">
                                <small><?php _e('重要聲明：AI的回應速度可能較慢。AI提供的資訊僅供參考，本公司對其準確性或完整性不承擔任何責任。AI回覆不代表本公司立場。', 'ai-chat'); ?></small>
                            </div>
                        </div>
                        <div class="chat-controls">
                            <button class="fullscreen-btn" title="<?php esc_attr_e('全螢幕', 'ai-chat'); ?>">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button class="minimize-btn" title="<?php esc_attr_e('最小化', 'ai-chat'); ?>">
                                <i class="fas fa-minus"></i>
                            </button>
                            <button class="close-btn" title="<?php esc_attr_e('關閉', 'ai-chat'); ?>">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chat-messages">
                        <div class="message bot-message">
                            <div class="message-avatar">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div class="message-content">
                                <p><?php echo $this->get_welcome_message(); ?></p>
                                <span class="message-time"><?php echo current_time('H:i'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-input">
                        <div class="input-container">
                            <textarea id="chat-input" placeholder="<?php esc_attr_e('請輸入您的信息...', 'ai-chat'); ?>" rows="1" autocomplete="off"></textarea>
                            <button type="button" id="send-message" class="send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>



        <!-- Force Color Application -->
        <style>
            /* 強制應用顏色變量 */
            #ai-chat-widget {
                --chat-color: <?php echo esc_attr($color); ?> !important;
                --chat-hover-color: <?php echo esc_attr($hover_color); ?> !important;
                --chat-primary-color: <?php echo esc_attr($color); ?> !important;
            }

            /* 強制應用到所有相關元素 */
            #ai-chat-widget .chat-toggle,
            #ai-chat-widget .chat-toggle .chat-icon {
                background-color: <?php echo esc_attr($color); ?> !important;
                background: <?php echo esc_attr($color); ?> !important;
            }

            #ai-chat-widget .chat-toggle:hover,
            #ai-chat-widget .chat-toggle:hover .chat-icon {
                background-color: <?php echo esc_attr($hover_color); ?> !important;
                background: <?php echo esc_attr($hover_color); ?> !important;
            }

            #ai-chat-widget .platform-item[data-platform="ai-chat"] .platform-icon {
                background-color: <?php echo esc_attr($color); ?> !important;
                background: <?php echo esc_attr($color); ?> !important;
            }

            #ai-chat-widget .chat-header {
                background-color: <?php echo esc_attr($color); ?> !important;
                background: <?php echo esc_attr($color); ?> !important;
            }

            #ai-chat-widget .send-btn {
                background-color: <?php echo esc_attr($color); ?> !important;
                background: <?php echo esc_attr($color); ?> !important;
            }

            #ai-chat-widget .user-message .message-content p {
                background-color: <?php echo esc_attr($color); ?> !important;
                background: <?php echo esc_attr($color); ?> !important;
            }

            #ai-chat-widget .bot-message .message-avatar,
            #ai-chat-widget .typing-indicator .message-avatar {
                background-color: <?php echo esc_attr($color); ?> !important;
                background: <?php echo esc_attr($color); ?> !important;
            }
        </style>
        <?php
    }
    
    /**
     * Render single platform toggle
     */
    private function render_single_platform_toggle($platform) {
        $platforms = $this->get_platform_config();
        $config = $platforms[$platform] ?? array();
        
        echo '<div class="chat-icon single-platform" data-platform="' . esc_attr($platform) . '">';
        echo '<i class="' . esc_attr($config['icon'] ?? 'fas fa-comments') . '"></i>';
        echo '</div>';
    }
    
    /**
     * Render platform item in menu
     */
    private function render_platform_item($platform) {
        $platforms = $this->get_platform_config();
        $config = $platforms[$platform] ?? array();
        
        echo '<div class="platform-item" data-platform="' . esc_attr($platform) . '">';
        echo '<div class="platform-icon">';
        echo '<i class="' . esc_attr($config['icon'] ?? 'fas fa-comments') . '"></i>';
        echo '</div>';
        echo '<div class="platform-name">' . esc_html($config['name'] ?? ucfirst($platform)) . '</div>';
        echo '</div>';
    }
    
    /**
     * Render platform bubble
     */
    private function render_platform_bubble($platform) {
        $platforms = $this->get_platform_config();
        $config = $platforms[$platform] ?? array();
        
        echo '<div class="platform-bubble" data-platform="' . esc_attr($platform) . '" style="--platform-color: ' . esc_attr($config['color'] ?? '#007cba') . ';">';
        echo '<div class="bubble-icon">';
        echo '<i class="' . esc_attr($config['icon'] ?? 'fas fa-comments') . '"></i>';
        echo '</div>';
        echo '<div class="bubble-tooltip">' . esc_html($config['name'] ?? ucfirst($platform)) . '</div>';
        echo '</div>';
    }
    
    /**
     * Get platform configuration
     */
    private function get_platform_config() {
        return array(
            'ai-chat' => array(
                'name' => __('AI智能助手', 'ai-chat'),
                'icon' => 'fas fa-robot',
                'color' => '#007cba',
                'url_pattern' => ''
            ),
            'whatsapp' => array(
                'name' => __('WhatsApp', 'ai-chat'),
                'icon' => 'fab fa-whatsapp',
                'color' => '#25d366',
                'url_pattern' => 'https://wa.me/{phone}'
            ),
            'facebook' => array(
                'name' => __('Facebook Messenger', 'ai-chat'),
                'icon' => 'fab fa-facebook-messenger',
                'color' => '#0084ff',
                'url_pattern' => 'https://m.me/{username}'
            ),
            'line' => array(
                'name' => __('LINE', 'ai-chat'),
                'icon' => 'fab fa-line',
                'color' => '#00c300',
                'url_pattern' => 'https://line.me/ti/p/{id}'
            ),
            'telegram' => array(
                'name' => __('Telegram', 'ai-chat'),
                'icon' => 'fab fa-telegram-plane',
                'color' => '#0088cc',
                'url_pattern' => 'https://t.me/{username}'
            ),
            'discord' => array(
                'name' => __('Discord', 'ai-chat'),
                'icon' => 'fab fa-discord',
                'color' => '#7289da',
                'url_pattern' => 'https://discord.gg/{invite}'
            ),
            'slack' => array(
                'name' => __('Slack', 'ai-chat'),
                'icon' => 'fab fa-slack',
                'color' => '#4a154b',
                'url_pattern' => 'https://{workspace}.slack.com'
            ),
            'teams' => array(
                'name' => __('Microsoft Teams', 'ai-chat'),
                'icon' => 'fab fa-microsoft',
                'color' => '#6264a7',
                'url_pattern' => '{teams_url}'
            ),
            'wechat' => array(
                'name' => __('WeChat', 'ai-chat'),
                'icon' => 'fab fa-weixin',
                'color' => '#7bb32e',
                'url_pattern' => 'weixin://dl/chat?{wechat_id}'
            ),
            'qq' => array(
                'name' => __('QQ', 'ai-chat'),
                'icon' => 'fab fa-qq',
                'color' => '#eb1923',
                'url_pattern' => 'mqqwpa://im/chat?chat_type=wpa&uin={qq_number}'
            ),
            'instagram' => array(
                'name' => __('Instagram', 'ai-chat'),
                'icon' => 'fab fa-instagram',
                'color' => '#e4405f',
                'url_pattern' => 'https://ig.me/m/{username}'
            ),
            'twitter' => array(
                'name' => __('Twitter/X', 'ai-chat'),
                'icon' => 'fab fa-x-twitter',
                'color' => '#1da1f2',
                'url_pattern' => 'https://twitter.com/messages/compose?recipient_id={user_id}'
            ),
            'skype' => array(
                'name' => __('Skype', 'ai-chat'),
                'icon' => 'fab fa-skype',
                'color' => '#00aff0',
                'url_pattern' => 'skype:{username}?chat'
            ),
            'viber' => array(
                'name' => __('Viber', 'ai-chat'),
                'icon' => 'fab fa-viber',
                'color' => '#665cac',
                'url_pattern' => 'viber://chat?number={phone}'
            ),
            'email' => array(
                'name' => __('Email', 'ai-chat'),
                'icon' => 'fas fa-envelope',
                'color' => '#dc3545',
                'url_pattern' => 'mailto:{email}'
            ),
            'phone' => array(
                'name' => __('Phone', 'ai-chat'),
                'icon' => 'fas fa-phone',
                'color' => '#28a745',
                'url_pattern' => 'tel:{phone}'
            )
        );
    }
    
    /**
     * Get welcome message for AI chat
     */
    private function get_welcome_message() {
        $site_name = get_bloginfo('name');
        $messages = array(
            sprintf(__('您好！歡迎來到 %s。我可以如何幫助您？', 'ai-chat'), $site_name),
            sprintf(__('您好！我是 %s 的AI智能助手。您想了解什麼？', 'ai-chat'), $site_name),
            sprintf(__('歡迎！我在這裡為您解答關於 %s 的任何問題。', 'ai-chat'), $site_name)
        );

        return $messages[array_rand($messages)];
    }

    /**
     * Darken a hex color by a percentage
     */
    private function darken_color($hex, $percent) {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Darken by percentage
        $r = max(0, $r - ($r * $percent / 100));
        $g = max(0, $g - ($g * $percent / 100));
        $b = max(0, $b - ($b * $percent / 100));

        // Convert back to hex
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
