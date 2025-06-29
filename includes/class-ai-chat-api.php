<?php
/**
 * AI API functionality for AI Chat Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_API {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('ai_chat_settings', array());
    }
    
    /**
     * Send message to AI and get response
     */
    public function send_message($message, $conversation_id = '') {
        try {
            // Get or create conversation based on IP
            if (empty($conversation_id)) {
                $user_ip = $this->get_user_ip();
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

                $conversation_id = $this->get_or_create_conversation($user_ip, $user_agent);

                if (!$conversation_id) {
                    return array('success' => false, 'error' => __('Failed to start conversation', 'ai-chat'));
                }

                AI_Chat_Database::update_analytics('ai-chat', true, false);
            }
            
            // Save user message
            $user_save_result = AI_Chat_Database::save_message($conversation_id, 'user', $message);
            AI_Chat_Database::update_analytics('ai-chat', false, true);
            
            // Get conversation context
            $context = $this->build_context($conversation_id);
            
            // Get AI response
            $ai_response = $this->call_ai_api($message, $context);
            
            if ($ai_response['success']) {
                // Save AI response
                $ai_save_result = AI_Chat_Database::save_message($conversation_id, 'ai', $ai_response['message']);
                
                return array(
                    'success' => true,
                    'message' => $ai_response['message'],
                    'conversation_id' => $conversation_id
                );
            } else {
                return array(
                    'success' => false,
                    'error' => $ai_response['error']
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => __('AI service temporarily unavailable', 'ai-chat')
            );
        }
    }
    
    /**
     * Call AI API
     */
    private function call_ai_api($message, $context = array()) {
        $api_provider = $this->settings['ai_api_provider'] ?? 'openrouter';
        $api_key = $this->settings['ai_api_key'] ?? '';
        $model = $this->settings['ai_model'] ?? 'openai/gpt-3.5-turbo';

        if (empty($api_key)) {
            return array('success' => false, 'error' => __('AI API key not configured', 'ai-chat'));
        }

        try {
            // Build messages array
            $messages = array();

            // Add system prompt
            $system_prompt = $this->build_system_prompt();
            if ($system_prompt) {
                // Clean UTF-8 encoding first
                $system_prompt = $this->clean_utf8_text($system_prompt);

                // Limit system prompt length to prevent API errors and timeouts
                // Get max system prompt length from settings (reduced default for better performance)
                $max_system_length = intval($this->settings['max_system_prompt_length'] ?? 50000);
                if (strlen($system_prompt) > $max_system_length) {

                    // Try to preserve external data sources (store info) when truncating
                    $external_marker = "外部數據源：";
                    $external_pos = strpos($system_prompt, $external_marker);

                    if ($external_pos !== false) {
                        // Keep base content + external sources, truncate other content
                        $base_content = substr($system_prompt, 0, $external_pos);
                        $external_content = substr($system_prompt, $external_pos);

                        $available_space = $max_system_length - strlen($external_content) - 100;
                        if ($available_space > 1000) {
                            $truncated_base = mb_substr($base_content, 0, $available_space, 'UTF-8');
                            $system_prompt = $truncated_base . "\n\n" . $external_content;
                        } else {
                            // If external content is too long, truncate normally
                            $system_prompt = mb_substr($system_prompt, 0, $max_system_length, 'UTF-8');
                        }
                    } else {
                        $system_prompt = mb_substr($system_prompt, 0, $max_system_length, 'UTF-8');
                    }

                    $system_prompt .= "\n\n[Content truncated due to length limits]";
                }

                $messages[] = array(
                    'role' => 'system',
                    'content' => $system_prompt
                );
            }

            // Add conversation context (limit to last 5 messages to prevent token overflow)
            if (is_array($context) && !empty($context)) {
                $context_limit = 5;
                $limited_context = array_slice($context, -$context_limit);

                foreach ($limited_context as $ctx_message) {
                    if (is_object($ctx_message) && isset($ctx_message->sender) && isset($ctx_message->message)) {
                        $role = $ctx_message->sender === 'user' ? 'user' : 'assistant';
                        $content = is_string($ctx_message->message) ? $ctx_message->message : (string) $ctx_message->message;
                        $content = $this->clean_utf8_text($content);

                        $messages[] = array(
                            'role' => $role,
                            'content' => $content
                        );
                    }
                }
            }

            // Add current message
            if (!is_string($message)) {
                $message = (string) $message;
            }
            $message = $this->clean_utf8_text($message);

            $messages[] = array(
                'role' => 'user',
                'content' => $message
            );

            // Validate all messages before sending
            $validated_messages = array();
            foreach ($messages as $msg) {
                if (is_array($msg) && isset($msg['role']) && isset($msg['content'])) {
                    $validated_messages[] = array(
                        'role' => (string) $msg['role'],
                        'content' => (string) $msg['content']
                    );
                }
            }

            if (empty($validated_messages)) {
                return array('success' => false, 'error' => 'No valid messages to send');
            }



            // Prepare API request
            if ($api_provider === 'openrouter') {
                return $this->call_openrouter_api($validated_messages, $model, $api_key);
            } else {
                return $this->call_custom_api($validated_messages, $model, $api_key);
            }

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => __('AI service error: ', 'ai-chat') . $e->getMessage()
            );
        }
    }
    
    /**
     * Call OpenRouter API
     */
    private function call_openrouter_api($messages, $model, $api_key) {
        // Validate inputs
        if (!is_array($messages) || empty($messages)) {
            return array('success' => false, 'error' => 'Invalid messages array');
        }

        if (!is_string($model) || empty($model)) {
            $model = 'openai/gpt-3.5-turbo'; // Default model
        }

        if (!is_string($api_key) || empty($api_key)) {
            return array('success' => false, 'error' => 'Invalid API key');
        }

        $url = 'https://openrouter.ai/api/v1/chat/completions';

        $body = array(
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );

        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => home_url(),
            'X-Title' => get_bloginfo('name')
        );

        return $this->make_api_request($url, $body, $headers);
    }
    
    /**
     * Call custom API
     */
    private function call_custom_api($messages, $model, $api_key) {
        // Validate inputs
        if (!is_array($messages) || empty($messages)) {
            return array('success' => false, 'error' => 'Invalid messages array');
        }

        if (!is_string($model) || empty($model)) {
            $model = 'gpt-3.5-turbo'; // Default model
        }

        if (!is_string($api_key) || empty($api_key)) {
            return array('success' => false, 'error' => 'Invalid API key');
        }

        $api_url = $this->settings['ai_api_url'] ?? '';

        if (!is_string($api_url) || empty($api_url)) {
            return array('success' => false, 'error' => __('Custom API URL not configured', 'ai-chat'));
        }

        $body = array(
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7
        );

        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        );

        return $this->make_api_request($api_url, $body, $headers);
    }
    
    /**
     * Make API request
     */
    private function make_api_request($url, $body, $headers) {
        // Validate inputs
        if (!is_string($url) || empty($url)) {
            return array('success' => false, 'error' => 'Invalid URL provided');
        }

        if (!is_array($body)) {
            return array('success' => false, 'error' => 'Invalid request body provided');
        }

        if (!is_array($headers)) {
            return array('success' => false, 'error' => 'Invalid headers provided');
        }

        // Encode body to JSON and check for errors
        $json_body = json_encode($body, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('success' => false, 'error' => 'Failed to encode request data');
        }

        // Prepare request arguments with increased timeout
        $request_args = array(
            'headers' => $headers,
            'body' => $json_body,
            'timeout' => 120, // Increased from 60 to 120 seconds
            'sslverify' => false,
            'user-agent' => 'AI-Chat-Plugin/1.0',
            'method' => 'POST'
        );

        $response = wp_remote_request($url, $request_args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();

            // Provide more specific error messages
            if (strpos($error_message, 'cURL error 28') !== false || strpos($error_message, 'timeout') !== false) {
                return array(
                    'success' => false,
                    'error' => __('AI反應會有點慢, 約1分鐘, 請耐心等候, 也可縮小後先瀏覽, 稍後回來再看', 'ai-chat')
                );
            } elseif (strpos($error_message, 'cURL error 6') !== false) {
                return array(
                    'success' => false,
                    'error' => __('Cannot connect to AI service. Please check your internet connection.', 'ai-chat')
                );
            } else {
                return array(
                    'success' => false,
                    'error' => __('Network error: ', 'ai-chat') . $error_message
                );
            }
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);



        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? __('API request failed', 'ai-chat') . ' (HTTP ' . $response_code . ')';

            return array(
                'success' => false,
                'error' => $error_message
            );
        }

        $data = json_decode($response_body, true);

        // Check if JSON decode failed
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => __('Invalid JSON response from API', 'ai-chat')
            );
        }

        // Try different response formats
        $message_content = null;

        // OpenAI/OpenRouter format
        if (isset($data['choices'][0]['message']['content'])) {
            $message_content = $data['choices'][0]['message']['content'];
        }
        // Alternative format 1: direct content
        elseif (isset($data['content'])) {
            $message_content = $data['content'];
        }
        // Alternative format 2: response field
        elseif (isset($data['response'])) {
            $message_content = $data['response'];
        }
        // Alternative format 3: text field
        elseif (isset($data['text'])) {
            $message_content = $data['text'];
        }
        // Alternative format 4: message field
        elseif (isset($data['message'])) {
            $message_content = $data['message'];
        }
        // Alternative format 5: data.message
        elseif (isset($data['data']['message'])) {
            $message_content = $data['data']['message'];
        }

        if ($message_content === null) {

            // Try to extract any text content from the response
            $fallback_content = '';
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_string($value) && strlen($value) > 10) {
                        $fallback_content = $value;
                        break;
                    }
                    if (is_array($value)) {
                        foreach ($value as $subkey => $subvalue) {
                            if (is_string($subvalue) && strlen($subvalue) > 10) {
                                $fallback_content = $subvalue;
                                break 2;
                            }
                        }
                    }
                }
            }

            if (!empty($fallback_content)) {
                return array(
                    'success' => true,
                    'message' => trim($fallback_content)
                );
            }

            return array(
                'success' => false,
                'error' => __('Invalid API response format', 'ai-chat') . ': ' . __('No message content found', 'ai-chat')
            );
        }

        return array(
            'success' => true,
            'message' => trim($message_content)
        );
    }
    
    /**
     * Build system prompt
     */
    private function build_system_prompt() {
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $site_url = home_url();

        // Detect user language from URL or browser
        $user_language = $this->detect_user_language();
        $response_language = $this->get_response_language($user_language);

        // Use full system prompt with proper UTF-8 handling
        return $this->build_full_system_prompt_safe($site_name, $site_description, $site_url, $user_language);
    }

    /**
     * Build full system prompt with safe UTF-8 handling
     */
    private function build_full_system_prompt_safe($site_name, $site_description, $site_url, $user_language) {
        // Clean site information
        $site_name = $this->clean_utf8_text($site_name);
        $site_description = $this->clean_utf8_text($site_description);

        // Get recent posts for context (configurable)
        $max_posts = intval($this->settings['max_posts_count'] ?? 50);
        $recent_posts = get_posts(array(
            'numberposts' => $max_posts,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $posts_context = '';
        foreach ($recent_posts as $post) {
            $content = wp_strip_all_tags($post->post_content);
            $content = wp_trim_words($content, 3000); // Increased from 30 to 3000

            $title = $this->clean_utf8_text($post->post_title);
            $content = $this->clean_utf8_text($content);

            $posts_context .= "Title: {$title}\nContent: {$content}\nURL: " . get_permalink($post->ID) . "\n\n";
        }

        // Get all pages first, then filter for important ones
        $max_posts = intval($this->settings['max_posts_count'] ?? 50);
        $all_pages = get_posts(array(
            'post_type' => 'page',
            'numberposts' => $max_posts,
            'post_status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));

        $key_pages = array();
        $other_pages = array();

        // Separate important pages from others
        foreach ($all_pages as $page) {
            $page_title_lower = strtolower($page->post_title);
            $page_slug_lower = strtolower($page->post_name);
            $page_content_lower = strtolower($page->post_content);

            // Check if it's an important page (shop, contact, about, etc.)
            $is_important = (
                // Page title keywords
                strpos($page_title_lower, 'shop') !== false ||
                strpos($page_title_lower, 'store') !== false ||
                strpos($page_title_lower, '門店') !== false ||
                strpos($page_title_lower, '店舖') !== false ||
                strpos($page_title_lower, '地址') !== false ||
                strpos($page_title_lower, 'address') !== false ||
                strpos($page_title_lower, 'contact') !== false ||
                strpos($page_title_lower, '聯絡') !== false ||
                strpos($page_title_lower, '聯繫') !== false ||
                strpos($page_title_lower, 'about') !== false ||
                strpos($page_title_lower, '關於') !== false ||
                strpos($page_title_lower, 'location') !== false ||
                strpos($page_title_lower, 'hours') !== false ||
                strpos($page_title_lower, 'service') !== false ||
                strpos($page_title_lower, '服務') !== false ||
                // Page slug keywords
                strpos($page_slug_lower, 'shop') !== false ||
                strpos($page_slug_lower, 'contact') !== false ||
                strpos($page_slug_lower, 'about') !== false ||
                strpos($page_slug_lower, 'location') !== false ||
                strpos($page_slug_lower, 'store') !== false ||
                // Content keywords (common business info) - 增加更多關鍵詞
                strpos($page_content_lower, '營業時間') !== false ||
                strpos($page_content_lower, 'business hours') !== false ||
                strpos($page_content_lower, 'opening hours') !== false ||
                strpos($page_content_lower, '電話') !== false ||
                strpos($page_content_lower, 'phone') !== false ||
                strpos($page_content_lower, '地址') !== false ||
                strpos($page_content_lower, 'address') !== false ||
                strpos($page_content_lower, 'whatsapp') !== false ||
                strpos($page_content_lower, 'email') !== false ||
                strpos($page_content_lower, '傳真') !== false ||
                strpos($page_content_lower, 'fax') !== false ||
                strpos($page_content_lower, '泳泰') !== false ||
                strpos($page_content_lower, 'swim thai') !== false ||
                strpos($page_content_lower, '2186') !== false ||
                strpos($page_content_lower, 'kowloon bay') !== false ||
                strpos($page_content_lower, '九龍灣') !== false ||
                strpos($page_content_lower, 'tonic industrial') !== false ||
                strpos($page_content_lower, '68898033') !== false
            );

            if ($is_important) {
                $key_pages[] = $page;
            } else {
                $other_pages[] = $page;
            }
        }

        // If we don't have enough important pages, add some others
        while (count($key_pages) < 5 && !empty($other_pages)) {
            $key_pages[] = array_shift($other_pages);
        }

        // Limit to 10 pages maximum (increased from 5)
        $key_pages = array_slice($key_pages, 0, 10);

        // Check if parallel processing is enabled
        $enable_parallel = $this->settings['enable_parallel_processing'] ?? '1';

        if ($enable_parallel === '1') {
            // Use parallel processing for faster data retrieval
            $context_data = $this->get_context_data_parallel();
            $posts_context = $context_data['posts'];
            $products_context = $context_data['products'];
            $external_sources_context = $context_data['external'];
            // Pages context will be built below with the existing logic for important page detection
        } else {
            // Sequential processing (original method)
            $posts_context = $this->get_posts_context();
            $products_context = '';
            if (class_exists('WooCommerce')) {
                $products_context = $this->get_woocommerce_products_context();
            }
            $external_sources_context = $this->get_external_data_sources_context();
        }

        $pages_context = '';
        foreach ($key_pages as $page) {
            $content = wp_strip_all_tags($page->post_content);

            // Check if it's an important page with store/contact info
            $page_title_lower = strtolower($page->post_title);
            $page_content_lower = strtolower($content);
            $is_important_page = (
                strpos($page_title_lower, 'shop') !== false ||
                strpos($page_title_lower, 'store') !== false ||
                strpos($page_title_lower, '門店') !== false ||
                strpos($page_title_lower, '店舖') !== false ||
                strpos($page_title_lower, '地址') !== false ||
                strpos($page_title_lower, 'address') !== false ||
                strpos($page_title_lower, 'contact') !== false ||
                strpos($page_title_lower, '聯絡') !== false ||
                strpos($page_title_lower, '聯繫') !== false ||
                strpos($page_title_lower, 'about') !== false ||
                strpos($page_title_lower, '關於') !== false ||
                strpos($page_content_lower, 'tyr') !== false ||
                strpos($page_content_lower, 'swimhub') !== false ||
                strpos($page_content_lower, '崇光') !== false ||
                strpos($page_content_lower, '營業時間') !== false ||
                strpos($page_content_lower, '電話') !== false ||
                strpos($page_content_lower, '地址') !== false ||
                strpos($page_content_lower, '門市') !== false ||
                strpos($page_content_lower, '分店') !== false
            );

            if ($is_important_page) {
                // For contact/shop pages, include much more content to capture all details
                $content = wp_trim_words($content, 3000); // Increased from 500 to 3000
            } else {
                $content = wp_trim_words($content, 3000); // Increased from 50 to 3000
            }

            $title = $this->clean_utf8_text($page->post_title);
            $content = $this->clean_utf8_text($content);

            $pages_context .= "Page: {$title}\nContent: {$content}\nURL: " . get_permalink($page->ID) . "\n\n";
        }

        // Get WhatsApp settings for fallback
        $whatsapp_phone = $this->settings['whatsapp_phone'] ?? '';
        $whatsapp_message = $this->settings['whatsapp_message'] ?? 'Hello, I need assistance';

        $whatsapp_fallback = '';
        if (!empty($whatsapp_phone)) {
            $whatsapp_url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp_phone) . '?text=' . urlencode($whatsapp_message);

            if ($user_language === 'zh') {
                $whatsapp_fallback = "如果您無法從上述網站內容中找到用戶需要的資訊，請回應：'抱歉，我沒有這方面的資訊。請聯繫我們的人工客服團隊，透過 WhatsApp 獲得個人化協助：{$whatsapp_url}'";
            } else {
                $whatsapp_fallback = "If you cannot find the information the user needs from the website content above, respond with: 'I apologize, but I don't have that information available. Please contact our human support team on WhatsApp for personalized assistance: {$whatsapp_url}'";
            }
        }

        // Custom system prompt from settings
        $custom_prompt = $this->clean_utf8_text($this->settings['ai_system_prompt'] ?? '');

        // Get custom AI guidance text from data sources page
        $custom_ai_text = $this->clean_utf8_text(get_option('ai_chat_custom_text', ''));

        if ($user_language === 'zh') {
            $system_prompt = "您是 {$site_name} 網站的 AI 智能助手。

網站資訊：
- 網站名稱：{$site_name}
- 網站描述：{$site_description}
- 網站網址：{$site_url}

您的任務：
1. 基於以下網站內容為用戶提供有用的資訊和協助
2. 使用繁體中文回應，保持友善和專業的語調
3. 當網站內容無法完全回答用戶問題時，可以提供相關的一般性建議
4. 如需要更詳細的人工協助，可引導用戶聯繫客服

網站內容參考：

最新文章：
{$posts_context}

主要頁面：
{$pages_context}

{$products_context}

{$external_sources_context}

{$whatsapp_fallback}

自訂指示：
{$custom_prompt}

額外指導資訊：
{$custom_ai_text}

請根據網站內容和您的知識為用戶提供最佳的協助。";
        } else {
            $system_prompt = "You are the AI intelligent assistant for {$site_name} website.

Website Information:
- Website Name: {$site_name}
- Website Description: {$site_description}
- Website URL: {$site_url}

Your Mission:
1. Provide helpful information and assistance based on the website content below
2. Respond in English with a friendly and professional tone
3. When website content cannot fully answer user questions, you may provide relevant general advice
4. If more detailed human assistance is needed, guide users to contact customer service

Website Content Reference:

Latest Articles:
{$posts_context}

Main Pages:
{$pages_context}

{$products_context}

{$external_sources_context}

{$whatsapp_fallback}

Custom Instructions:
{$custom_prompt}

Additional Guidance:
{$custom_ai_text}

Please provide the best assistance to users based on the website content and your knowledge.";
        }



        // Clear any existing conversation to force new system prompt
        if (isset($_SESSION['ai_chat_conversation_id'])) {
            unset($_SESSION['ai_chat_conversation_id']);
        }

        return $system_prompt;
    }

    /**
     * Get posts context for AI responses
     */
    private function get_posts_context() {
        $max_posts = intval($this->settings['max_posts_count'] ?? 50);
        $recent_posts = get_posts(array(
            'numberposts' => $max_posts,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $posts_context = '';
        foreach ($recent_posts as $post) {
            $content = wp_strip_all_tags($post->post_content);
            $content = wp_trim_words($content, 100);

            // Clean UTF-8 encoding
            $title = $this->clean_utf8_text($post->post_title);
            $content = $this->clean_utf8_text($content);

            $posts_context .= "標題: {$title}\n內容: {$content}\nURL: " . get_permalink($post->ID) . "\n\n";
        }

        return $posts_context;
    }

    /**
     * Get WooCommerce products context for AI responses
     */
    private function get_woocommerce_products_context() {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $context = '';

        // Get products from sitemap first
        $products_from_sitemap = $this->get_products_from_sitemap();

        // Get max products count from settings
        $max_products = intval($this->settings['max_products_count'] ?? 50);

        if (!empty($products_from_sitemap)) {
            $products = array_slice($products_from_sitemap, 0, $max_products);
        } else {
            // Fallback to database query
            $products = get_posts(array(
                'post_type' => 'product',
                'numberposts' => $max_products,
                'post_status' => 'publish',
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ));
        }

        $context .= "商品資訊：\n";

        foreach ($products as $product) {
            $product_obj = wc_get_product($product->ID);
            if (!$product_obj) continue;

            $title = $this->clean_utf8_text($product->post_title);
            $price = $product_obj->get_price_html();
            $description = wp_strip_all_tags($product->post_content);
            $description = wp_trim_words($description, 50);
            $short_description = wp_strip_all_tags($product->post_excerpt);
            $short_description = wp_trim_words($short_description, 30);
            $categories = wp_get_post_terms($product->ID, 'product_cat', array('fields' => 'names'));
            $category_names = implode(', ', $categories);
            $stock_status = $product_obj->get_stock_status();
            $sku = $product_obj->get_sku();
            // Use sitemap URL if available, otherwise use permalink
            $url = isset($product->sitemap_url) ? $product->sitemap_url : get_permalink($product->ID);

            // Get sales data
            $sales_count = get_post_meta($product->ID, 'total_sales', true);

            $context .= __('產品名稱', 'ai-chat') . ": {$title}\n";
            $context .= __('價格', 'ai-chat') . ": {$price}\n";
            $context .= __('分類', 'ai-chat') . ": {$category_names}\n";
            $context .= __('庫存狀態', 'ai-chat') . ": " . ($stock_status === 'instock' ? __('有庫存', 'ai-chat') : __('缺貨', 'ai-chat')) . "\n";
            if ($sku) {
                $context .= __('產品編號', 'ai-chat') . ": {$sku}\n";
            }
            if ($sales_count) {
                $context .= __('銷售數量', 'ai-chat') . ": {$sales_count}\n";
            }
            if ($short_description) {
                $context .= __('簡介', 'ai-chat') . ": {$short_description}\n";
            }
            if ($description) {
                $context .= __('詳細描述', 'ai-chat') . ": {$description}\n";
            }
            $context .= __('連結', 'ai-chat') . ": {$url}\n\n";
        }

        // Get product categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'number' => 20
        ));

        if (!empty($categories)) {
            $context .= "商品分類：\n";
            foreach ($categories as $category) {
                $category_url = get_term_link($category);
                $context .= "分類: {$category->name}\n";
                $context .= "描述: {$category->description}\n";
                $context .= "連結: {$category_url}\n\n";
            }
        }

        return $context;
    }

    /**
     * Get products from sitemap (using custom sitemaps from backend)
     */
    private function get_products_from_sitemap() {
        // Get custom sitemaps from backend settings
        $custom_sitemaps = get_option('ai_chat_sitemaps', array());

        $sitemap_urls = array();

        // Add custom sitemaps first (priority)
        foreach ($custom_sitemaps as $sitemap) {
            $sitemap_urls[] = $sitemap['url'];
        }

        // Fallback to default sitemaps if no custom ones are set
        if (empty($sitemap_urls)) {
            $sitemap_urls = array(
                home_url('/product-sitemap.xml'),
                home_url('/product_cat-sitemap.xml'),
                home_url('/sitemap.xml')
            );
        }

        $products = array();

        foreach ($sitemap_urls as $sitemap_url) {
            $response = wp_remote_get($sitemap_url, array('timeout' => 10));

            if (is_wp_error($response)) {
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                continue;
            }

            // Parse XML
            $xml = simplexml_load_string($body);
            if ($xml === false) {
                continue;
            }

            // Extract URLs from sitemap
            foreach ($xml->url as $url) {
                $loc = (string)$url->loc;

                // Check if it's a product URL
                if (strpos($loc, '/product/') !== false) {
                    $product_id = url_to_postid($loc);
                    if ($product_id && get_post_type($product_id) === 'product') {
                        $product = get_post($product_id);
                        if ($product) {
                            // Store the actual URL from sitemap for accurate recommendations
                            $product->sitemap_url = $loc;
                            $products[] = $product;
                        }
                    }
                }
            }
        }

        return array_filter($products); // Remove null values
    }

    /**
     * Build full system prompt (original method - kept for reference)
     */
    private function build_full_system_prompt() {
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $site_url = home_url();

        // Detect user language from URL or browser
        $user_language = $this->detect_user_language();
        $response_language = $this->get_response_language($user_language);

        // Get recent posts for context
        $recent_posts = get_posts(array(
            'numberposts' => 3, // Reduced from 5 to 3
            'post_status' => 'publish'
        ));

        $posts_context = '';
        foreach ($recent_posts as $post) {
            $content = wp_strip_all_tags($post->post_content);
            $content = wp_trim_words($content, 30); // Reduced from 50 to 30

            // Clean UTF-8 encoding
            $title = $this->clean_utf8_text($post->post_title);
            $content = $this->clean_utf8_text($content);

            $posts_context .= "Title: {$title}\nContent: {$content}\nURL: " . get_permalink($post->ID) . "\n\n";
        }

        // Get all pages for context (including from sitemap if available)
        $pages = $this->get_all_pages_from_sitemap();

        $pages_context = '';
        $page_count = 0;
        $max_pages = 5; // Limit number of pages to process

        foreach ($pages as $page) {
            if ($page_count >= $max_pages) break;

            $content = wp_strip_all_tags($page->post_content);

            // Special handling for shop/store pages - include more content but limit total
            $page_slug = $page->post_name;
            $page_title_lower = strtolower($page->post_title);
            $page_url = get_permalink($page->ID);
            $page_url_lower = strtolower($page_url);

            if (strpos($page_slug, 'shop') !== false ||
                strpos($page_slug, 'store') !== false ||
                strpos($page_url_lower, '/shop') !== false ||
                strpos($page_url_lower, '/store') !== false ||
                strpos($page_title_lower, '門店') !== false ||
                strpos($page_title_lower, '店舖') !== false ||
                strpos($page_title_lower, 'shop') !== false ||
                strpos($page_title_lower, 'store') !== false ||
                strpos($page_title_lower, '地址') !== false ||
                strpos($page_title_lower, 'address') !== false ||
                strpos($page_title_lower, 'location') !== false ||
                strpos($content, '門店') !== false ||
                strpos($content, '店舖') !== false ||
                strpos($content, '營業時間') !== false ||
                strpos($content, '電話') !== false) {
                // For shop/store/address pages, include more content but limit to 500 words
                $content = wp_trim_words($content, 500);
            } else {
                // For other pages, use smaller limit
                $content = wp_trim_words($content, 30);
            }

            // Clean UTF-8 encoding
            $title = $this->clean_utf8_text($page->post_title);
            $content = $this->clean_utf8_text($content);

            $pages_context .= "Page: {$title}\nContent: {$content}\nURL: " . $page_url . "\n\n";
            $page_count++;
        }

        // Get WooCommerce products and categories from sitemap (if WooCommerce is active)
        $products_context = '';
        $categories_context = '';
        if (class_exists('WooCommerce')) {
            // Get products from sitemap first, then fallback to database
            $products_from_sitemap = $this->get_products_from_sitemap();

            if (!empty($products_from_sitemap)) {
                $products = array_slice($products_from_sitemap, 0, 10); // Limit to 10 products
            } else {
                $products = get_posts(array(
                    'post_type' => 'product',
                    'numberposts' => 10, // Reduced from 50 to 10
                    'post_status' => 'publish',
                    'orderby' => 'menu_order',
                    'order' => 'ASC'
                ));
            }

            foreach ($products as $product) {
                $product_obj = wc_get_product($product->ID);
                if ($product_obj) {
                    $price = $product_obj->get_price_html();
                    $description = wp_strip_all_tags($product->post_content);
                    $description = wp_trim_words($description, 15); // Reduced from 30 to 15
                    $short_description = wp_strip_all_tags($product->post_excerpt);
                    $short_description = wp_trim_words($short_description, 10); // Reduced from 20 to 10
                    $categories = wp_get_post_terms($product->ID, 'product_cat', array('fields' => 'names'));
                    $category_names = implode(', ', $categories);
                    $stock_status = $product_obj->get_stock_status();
                    $sku = $product_obj->get_sku();

                    // Clean UTF-8 encoding
                    $title = $this->clean_utf8_text($product->post_title);
                    $description = $this->clean_utf8_text($description);
                    $short_description = $this->clean_utf8_text($short_description);
                    $price = $this->clean_utf8_text($price);

                    $products_context .= "Product: {$title}\n";
                    $products_context .= "Price: {$price}\n";
                    $products_context .= "Categories: {$category_names}\n";
                    $products_context .= "Stock: " . ($stock_status === 'instock' ? 'In Stock' : 'Out of Stock') . "\n";
                    if ($sku) {
                        $products_context .= "SKU: {$sku}\n";
                    }
                    if ($short_description) {
                        $products_context .= "Description: {$short_description}\n";
                    }
                    if ($description) {
                        $products_context .= "Details: {$description}\n";
                    }
                    $products_context .= "URL: " . get_permalink($product->ID) . "\n\n";
                }
            }

            // Get product categories
            $categories_from_sitemap = $this->get_categories_from_sitemap();
            if (!empty($categories_from_sitemap)) {
                foreach ($categories_from_sitemap as $category) {
                    $categories_context .= "Category: {$category['name']}\n";
                    $categories_context .= "URL: {$category['url']}\n";
                    if (!empty($category['description'])) {
                        $categories_context .= "Description: {$category['description']}\n";
                    }
                    $categories_context .= "\n";
                }
            }

            // Get popular products for recommendations
            $popular_products = $this->get_popular_products();
            $popular_context = '';
            if (!empty($popular_products)) {
                $popular_context = "熱門推薦商品（根據銷售數據）：\n";
                foreach ($popular_products as $product) {
                    $popular_context .= "- {$product['name']} ({$product['price']}) - 銷量: {$product['sales']}\n";
                    $popular_context .= "  連結: {$product['url']}\n";
                }
                $popular_context .= "\n";
            }
        } else {
            // Initialize empty contexts if WooCommerce is not active
            $products_context = '';
            $categories_context = '';
            $popular_context = '';
        }

        // Custom system prompt from settings
        $custom_prompt = $this->settings['ai_system_prompt'] ?? '';

        // Get custom AI guidance text from data sources page
        $custom_ai_text = $this->clean_utf8_text(get_option('ai_chat_custom_text', ''));

        // Get WhatsApp settings for fallback
        $whatsapp_phone = $this->settings['whatsapp_phone'] ?? '';
        $whatsapp_message = $this->settings['whatsapp_message'] ?? 'Hello, I need assistance';

        $whatsapp_fallback = '';
        if (!empty($whatsapp_phone)) {
            $whatsapp_url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp_phone) . '?text=' . urlencode($whatsapp_message);

            if ($user_language === 'zh') {
                $whatsapp_fallback = "如果您無法從上述網站內容中找到用戶需要的資訊，請回應：'抱歉，我沒有這方面的資訊。請聯繫我們的人工客服團隊，透過 WhatsApp 獲得個人化協助：{$whatsapp_url}'";
            } else {
                $whatsapp_fallback = "If you cannot find the information the user needs from the website content above, respond with: 'I apologize, but I don't have that information available. Please contact our human support team on WhatsApp for personalized assistance: {$whatsapp_url}'";
            }
        }

        if ($user_language === 'zh') {
            $system_prompt = "您是 {$site_name} 網站的 AI 助手。

網站資訊：
- 網站名稱：{$site_name}
- 網站描述：{$site_description}
- 網站網址：{$site_url}

您的任務：
1. 基於以下網站內容為用戶提供有用的資訊和協助
2. 使用繁體中文回應，保持友善和專業的語調
3. 當網站內容無法完全回答用戶問題時，可以提供相關的一般性建議
4. 如需要更詳細的人工協助，可引導用戶聯繫客服

網站內容參考：

最新文章：
{$posts_context}

主要頁面：
{$pages_context}" .
(!empty($products_context) ? "\n\n商品資訊：\n{$products_context}" : "") .
(!empty($categories_context) ? "\n\n商品分類：\n{$categories_context}" : "") .
(!empty($popular_context) ? "\n\n{$popular_context}" : "") . "

{$external_sources_context}

{$whatsapp_fallback}

自訂指示：
{$custom_prompt}

額外指導資訊：
{$custom_ai_text}

請根據網站內容和您的知識為用戶提供最佳的協助。";
        } else {
            $system_prompt = "You are the AI assistant for {$site_name} website.

Website Information:
- Website Name: {$site_name}
- Website Description: {$site_description}
- Website URL: {$site_url}

Your Mission:
1. Provide helpful information and assistance based on the website content below
2. Respond in English with a friendly and professional tone
3. When website content cannot fully answer user questions, you may provide relevant general advice
4. If more detailed human assistance is needed, guide users to contact customer service

Website Content Reference:

Latest Articles:
{$posts_context}

Main Pages:
{$pages_context}" .
(!empty($products_context) ? "\n\nProducts Information:\n{$products_context}" : "") .
(!empty($categories_context) ? "\n\nProduct Categories:\n{$categories_context}" : "") .
(!empty($popular_context) ? "\n\nPopular Products (Based on Sales Data):\n{$popular_context}" : "") . "

{$external_sources_context}

{$whatsapp_fallback}

Custom Instructions:
{$custom_prompt}

Additional Guidance:
{$custom_ai_text}

Please provide the best assistance to users based on the website content and your knowledge.";
        }

        // Ensure we return a clean UTF-8 string
        if (!is_string($system_prompt)) {
            $system_prompt = (string) $system_prompt;
        }

        // Final UTF-8 cleaning
        $system_prompt = $this->clean_utf8_text($system_prompt);

        return $system_prompt;
    }

    /**
     * Get all pages from sitemap or fallback to standard method
     */
    private function get_all_pages_from_sitemap() {
        // Try to get pages from WordPress sitemap first
        $sitemap_urls = array(
            home_url('/wp-sitemap-pages-1.xml'),
            home_url('/sitemap.xml'),
            home_url('/sitemap_index.xml'),
            home_url('/wp-sitemap.xml')
        );

        $pages_from_sitemap = array();

        foreach ($sitemap_urls as $sitemap_url) {
            $sitemap_content = wp_remote_get($sitemap_url, array('timeout' => 10));

            if (!is_wp_error($sitemap_content) && wp_remote_retrieve_response_code($sitemap_content) === 200) {
                $xml_content = wp_remote_retrieve_body($sitemap_content);

                if ($xml_content) {
                    $xml = simplexml_load_string($xml_content);

                    if ($xml !== false) {
                        // Handle different sitemap formats
                        if (isset($xml->url)) {
                            // Standard sitemap format
                            foreach ($xml->url as $url_entry) {
                                $page_url = (string)$url_entry->loc;
                                $post_id = url_to_postid($page_url);

                                if ($post_id) {
                                    $post = get_post($post_id);
                                    if ($post && $post->post_type === 'page') {
                                        $pages_from_sitemap[] = $post;
                                    }
                                }
                            }
                        } elseif (isset($xml->sitemap)) {
                            // Sitemap index format - get first sitemap
                            $first_sitemap = (string)$xml->sitemap[0]->loc;
                            $sub_sitemap = wp_remote_get($first_sitemap, array('timeout' => 10));

                            if (!is_wp_error($sub_sitemap) && wp_remote_retrieve_response_code($sub_sitemap) === 200) {
                                $sub_xml_content = wp_remote_retrieve_body($sub_sitemap);
                                $sub_xml = simplexml_load_string($sub_xml_content);

                                if ($sub_xml !== false && isset($sub_xml->url)) {
                                    foreach ($sub_xml->url as $url_entry) {
                                        $page_url = (string)$url_entry->loc;
                                        $post_id = url_to_postid($page_url);

                                        if ($post_id) {
                                            $post = get_post($post_id);
                                            if ($post && $post->post_type === 'page') {
                                                $pages_from_sitemap[] = $post;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // If we found pages from sitemap, use them
                        if (!empty($pages_from_sitemap)) {
                            break;
                        }
                    }
                }
            }
        }

        // If sitemap method didn't work, fallback to standard WordPress method
        if (empty($pages_from_sitemap)) {
            $pages_from_sitemap = get_posts(array(
                'post_type' => 'page',
                'numberposts' => -1, // Get all pages
                'post_status' => 'publish'
            ));
        }

        return $pages_from_sitemap;
    }

    /**
     * Detect user language from URL or browser
     */
    private function detect_user_language() {
        // Check URL for English prefix first
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, '/en/') !== false || strpos($current_url, '/en-') !== false) {
            return 'en';
        }

        // Check URL for Chinese prefix
        if (strpos($current_url, '/zh/') !== false || strpos($current_url, '/zh-') !== false) {
            return 'zh';
        }

        // Check WordPress locale for English
        $locale = get_locale();
        if ($locale === 'en_US' || $locale === 'en_GB' || strpos($locale, 'en_') === 0) {
            return 'en';
        }

        // Check WordPress locale for Chinese
        if (strpos($locale, 'zh') === 0) {
            return 'zh';
        }

        // Check browser language for English explicitly
        $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (preg_match('/\ben\b/', $accept_language) && !preg_match('/\bzh\b/', $accept_language)) {
            return 'en';
        }

        // Default to Traditional Chinese (繁體中文) for better user experience
        return 'zh';
    }

    /**
     * Get response language based on user language
     */
    private function get_response_language($user_language) {
        return $user_language === 'zh' ? 'Traditional Chinese' : 'English';
    }
    
    /**
     * Build conversation context
     */
    private function build_context($conversation_id) {
        try {
            if (empty($conversation_id)) {
                return array();
            }

            // Get conversation messages (limit based on settings)
            $max_history = intval($this->settings['max_conversation_history'] ?? 10);
            $messages = AI_Chat_Database::get_conversation_messages($conversation_id, $max_history);

            // Ensure we have an array
            if (!is_array($messages)) {
                return array();
            }

            // Exclude the last message (current user message)
            if (!empty($messages)) {
                array_pop($messages);
            }

            // Validate message format
            $validated_messages = array();
            foreach ($messages as $message) {
                if (is_object($message) && isset($message->sender) && isset($message->message)) {
                    $validated_messages[] = $message;
                }
            }

            return $validated_messages;

        } catch (Exception $e) {
            return array();
        }
    }
    
    /**
     * Get current page context
     */
    private function get_current_page_context() {
        $referer = wp_get_referer();
        
        if ($referer) {
            $post_id = url_to_postid($referer);
            
            if ($post_id) {
                $post = get_post($post_id);
                if ($post) {
                    return $post->post_title . ' (' . get_permalink($post_id) . ')';
                }
            }
        }
        
        return false;
    }
    
    /**
     * Test API connection
     */
    public function test_connection($api_provider, $api_key, $api_url = '', $model = '') {
        if (empty($api_key)) {
            return array('success' => false, 'error' => __('API key is required', 'ai-chat'));
        }

        // Use a simple test message
        $test_message = array(
            array('role' => 'user', 'content' => 'Say "Hello" in response.')
        );

        try {
            if ($api_provider === 'openrouter') {
                $url = 'https://openrouter.ai/api/v1/chat/completions';
                $body = array(
                    'model' => $model ?: 'openai/gpt-3.5-turbo',
                    'messages' => $test_message,
                    'max_tokens' => 50,
                    'temperature' => 0.1
                );
                $headers = array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => home_url(),
                    'X-Title' => get_bloginfo('name')
                );
            } else {
                if (empty($api_url)) {
                    return array('success' => false, 'error' => __('Custom API URL is required', 'ai-chat'));
                }

                $url = $api_url;
                $body = array(
                    'model' => $model ?: 'gpt-3.5-turbo',
                    'messages' => $test_message,
                    'max_tokens' => 50,
                    'temperature' => 0.1
                );
                $headers = array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                );
            }

            $result = $this->make_api_request($url, $body, $headers);

            if ($result['success']) {
                return array(
                    'success' => true,
                    'message' => __('Connection successful!', 'ai-chat') . ' Response: ' . substr($result['message'], 0, 100)
                );
            } else {
                return $result;
            }

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => __('Connection test failed: ', 'ai-chat') . $e->getMessage()
            );
        }
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get or create conversation based on IP
     */
    private function get_or_create_conversation($user_ip, $user_agent) {
        global $wpdb;

        $conversations_table = $wpdb->prefix . 'ai_chat_conversations';

        // Look for existing active conversation for this IP within last 24 hours
        $existing_conversation = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT conversation_id FROM $conversations_table
                 WHERE user_ip = %s
                 AND platform = 'ai-chat'
                 AND status = 'active'
                 AND updated_at > %s
                 ORDER BY updated_at DESC
                 LIMIT 1",
                $user_ip,
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            )
        );

        if ($existing_conversation) {
            // Update the existing conversation's activity
            AI_Chat_Database::update_conversation_activity($existing_conversation);
            return $existing_conversation;
        }

        // Create new conversation
        return AI_Chat_Database::start_conversation($user_ip, $user_agent);
    }



    /**
     * Get categories from sitemap
     */
    private function get_categories_from_sitemap() {
        $sitemap_urls = array(
            home_url('/product_cat-sitemap.xml'),
            home_url('/wp-sitemap-product_cat-1.xml'),
            home_url('/sitemap-product-cat.xml')
        );

        $categories = array();

        foreach ($sitemap_urls as $sitemap_url) {
            $sitemap_content = wp_remote_get($sitemap_url, array('timeout' => 10));

            if (!is_wp_error($sitemap_content) && wp_remote_retrieve_response_code($sitemap_content) === 200) {
                $xml_content = wp_remote_retrieve_body($sitemap_content);

                if ($xml_content) {
                    $xml = simplexml_load_string($xml_content);

                    if ($xml !== false && isset($xml->url)) {
                        foreach ($xml->url as $url_entry) {
                            $category_url = (string)$url_entry->loc;

                            // Extract category slug from URL
                            $url_parts = parse_url($category_url);
                            $path = trim($url_parts['path'], '/');
                            $path_parts = explode('/', $path);

                            // Look for product-category in URL
                            $category_slug = '';
                            foreach ($path_parts as $i => $part) {
                                if ($part === 'product-category' && isset($path_parts[$i + 1])) {
                                    $category_slug = $path_parts[$i + 1];
                                    break;
                                }
                            }

                            if ($category_slug) {
                                $term = get_term_by('slug', $category_slug, 'product_cat');
                                if ($term) {
                                    $categories[] = array(
                                        'name' => $term->name,
                                        'url' => $category_url,
                                        'description' => $term->description
                                    );
                                }
                            }
                        }

                        // If we found categories from sitemap, use them
                        if (!empty($categories)) {
                            break;
                        }
                    }
                }
            }
        }

        return $categories;
    }

    /**
     * Get popular products based on WooCommerce analytics
     */
    private function get_popular_products() {
        global $wpdb;

        $popular_products = array();

        // Try to get data from WooCommerce Analytics tables (WC 4.0+)
        $analytics_table = $wpdb->prefix . 'wc_order_product_lookup';

        if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") === $analytics_table) {
            // Get top selling products from last 30 days
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT product_id, SUM(product_qty) as total_sales
                FROM $analytics_table
                WHERE date_created >= %s
                GROUP BY product_id
                ORDER BY total_sales DESC
                LIMIT 10
            ", date('Y-m-d', strtotime('-30 days'))));

            foreach ($results as $result) {
                $product = wc_get_product($result->product_id);
                if ($product) {
                    $popular_products[] = array(
                        'id' => $result->product_id,
                        'name' => $product->get_name(),
                        'sales' => $result->total_sales,
                        'url' => get_permalink($result->product_id),
                        'price' => $product->get_price_html()
                    );
                }
            }
        } else {
            // Fallback: Get products by menu order or featured products
            $featured_products = wc_get_featured_product_ids();

            if (!empty($featured_products)) {
                foreach (array_slice($featured_products, 0, 10) as $product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $popular_products[] = array(
                            'id' => $product_id,
                            'name' => $product->get_name(),
                            'sales' => 'Featured',
                            'url' => get_permalink($product_id),
                            'price' => $product->get_price_html()
                        );
                    }
                }
            }
        }

        return $popular_products;
    }

    /**
     * Clean UTF-8 text to prevent encoding errors
     */
    private function clean_utf8_text($text) {
        if (!is_string($text)) {
            return '';
        }

        // Decode HTML entities first
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove any null bytes
        $text = str_replace("\0", '', $text);

        // Remove control characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Convert to UTF-8 and remove invalid sequences
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Fallback: use iconv if available
        if (function_exists('iconv')) {
            $text = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        }

        // Final cleanup - remove any remaining problematic characters
        $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);

        return trim($text);
    }

    /**
     * Get external data sources context
     */
    private function get_external_data_sources_context() {
        // Check cache first (5 minutes)
        $cache_key = 'ai_chat_external_sources_context';
        $cached_context = get_transient($cache_key);
        if ($cached_context !== false) {
            return $cached_context;
        }

        $database = new AI_Chat_Database();

        // Check if table exists first
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chat_data_sources';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

        if ($table_exists != $table_name) {
            return '';
        }

        $data_sources = $database->get_active_data_sources();

        if (empty($data_sources)) {
            return '';
        }

        $context = "外部數據源：\n";

        foreach ($data_sources as $source) {
            $url = $source['url'];
            $title = $source['title'] ?: '無標題';

            // Fetch fresh content from the URL
            $content = $this->fetch_url_content($url);

            if (!empty($content)) {
                $context .= "來源: {$title}\n";
                $context .= "URL: {$url}\n";
                $context .= "內容: {$content}\n\n";

                // Update fetch count and last fetched time
                $this->update_data_source_fetch_info($source['id']);
            }
        }

        // Cache for 5 minutes
        set_transient($cache_key, $context, 5 * MINUTE_IN_SECONDS);

        return $context;
    }

    /**
     * Fetch content from URL
     */
    private function fetch_url_content($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'user-agent' => 'AI Chat Plugin/1.0',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
            )
        ));

        if (is_wp_error($response)) {
            return '';
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return '';
        }

        // Extract text content from HTML with better filtering
        $content = $this->extract_meaningful_content($body);

        return $content;
    }

    /**
     * Update data source fetch information
     */
    private function update_data_source_fetch_info($source_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_chat_data_sources';

        $wpdb->update(
            $table,
            array(
                'last_fetched' => current_time('mysql')
            ),
            array('id' => $source_id),
            array('%s'),
            array('%d')
        );

        // Update fetch_count separately
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET fetch_count = fetch_count + 1 WHERE id = %d",
            $source_id
        ));
    }



    /**
     * Extract meaningful content from HTML
     */
    private function extract_meaningful_content($html) {
        // Remove script and style tags completely
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);

        // Remove common non-content elements but keep main content areas
        $html = preg_replace('/<nav\b[^>]*>.*?<\/nav>/si', '', $html);
        $html = preg_replace('/<header\b[^>]*>.*?<\/header>/si', '', $html);
        $html = preg_replace('/<footer\b[^>]*>.*?<\/footer>/si', '', $html);

        // Extract text content
        $content = strip_tags($html);

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        // Remove common unwanted patterns
        $content = preg_replace('/\(function\([^)]*\)\{[^}]*\}\)[^;]*;?/i', '', $content);
        $content = preg_replace('/img:is\([^)]*\)[^}]*\}/i', '', $content);
        $content = preg_replace('/\{"@context"[^}]*\}/i', '', $content);

        // Remove cookie notice and other common website elements
        $content = preg_replace('/為增進您的使用體驗.*?Accept/s', '', $content);
        $content = preg_replace('/Skip to content.*?購物車/s', '', $content);
        $content = preg_replace('/©\d{4}.*?保留所有權/s', '', $content);

        // Focus on store information if present
        if (strpos($content, '門市地址') !== false) {
            // Extract the store information section
            $store_start = strpos($content, '門市地址');
            if ($store_start !== false) {
                $store_content = substr($content, $store_start);
                // Find the end of store information (before footer or other sections)
                $end_markers = ['訂單追蹤', '產品分類', '我的帳戶', '©'];
                $store_end = strlen($store_content);
                foreach ($end_markers as $marker) {
                    $pos = strpos($store_content, $marker);
                    if ($pos !== false && $pos < $store_end) {
                        $store_end = $pos;
                    }
                }
                $store_content = substr($store_content, 0, $store_end);
                $content = $store_content;
            }
        }

        // Limit content length to 10000 characters (increased for more comprehensive info)
        $content = mb_substr($content, 0, 10000, 'UTF-8');

        // Clean UTF-8 text
        $content = $this->clean_utf8_text($content);

        return $content;
    }

    /**
     * Get context data using parallel processing for better performance
     */
    private function get_context_data_parallel() {
        // Initialize results array
        $results = array(
            'posts' => '',
            'pages' => '',
            'products' => '',
            'external' => ''
        );

        // Use WordPress HTTP API to simulate parallel requests
        // Since PHP doesn't have true threading, we'll optimize by reducing redundant operations

        // Get posts context
        $max_posts = intval($this->settings['max_posts_count'] ?? 50);
        $recent_posts = get_posts(array(
            'numberposts' => $max_posts,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $posts_context = '';
        foreach ($recent_posts as $post) {
            $content = wp_strip_all_tags($post->post_content);
            $content = wp_trim_words($content, 100);
            $posts_context .= "標題: {$post->post_title}\n內容: {$content}\n\n";
        }
        $results['posts'] = $posts_context;

        // Get pages context (reuse logic from main method)
        $all_pages = get_posts(array(
            'post_type' => 'page',
            'numberposts' => $max_posts,
            'post_status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));

        $pages_context = '';
        foreach (array_slice($all_pages, 0, 10) as $page) {
            $content = wp_strip_all_tags($page->post_content);
            $content = wp_trim_words($content, 200);
            $pages_context .= "頁面: {$page->post_title}\n內容: {$content}\n\n";
        }
        $results['pages'] = $pages_context;

        // Get products context
        if (class_exists('WooCommerce')) {
            $max_products = intval($this->settings['max_products_count'] ?? 50);
            $products = get_posts(array(
                'post_type' => 'product',
                'numberposts' => $max_products,
                'post_status' => 'publish',
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ));

            $products_context = '';
            foreach ($products as $product) {
                $wc_product = wc_get_product($product->ID);
                if ($wc_product) {
                    $title = $wc_product->get_name();
                    $price = $wc_product->get_price_html();
                    $stock_status = $wc_product->get_stock_status();

                    $products_context .= __('產品名稱', 'ai-chat') . ": {$title}\n";
                    $products_context .= __('價格', 'ai-chat') . ": {$price}\n";
                    $products_context .= __('庫存狀態', 'ai-chat') . ": " . ($stock_status === 'instock' ? __('有庫存', 'ai-chat') : __('缺貨', 'ai-chat')) . "\n\n";
                }
            }
            $results['products'] = $products_context;
        }

        // Get external sources (with caching)
        $results['external'] = $this->get_external_data_sources_context();

        return $results;
    }
}
