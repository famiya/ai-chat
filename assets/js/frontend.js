jQuery(document).ready(function($) {
    'use strict';

    // Variables
    let isOpen = false;
    let isMinimized = false;
    let isFullscreen = false;
    let isTyping = false;
    let conversationId = null;
    let isPlatformMenuOpen = false;
    let isBubbleMode = false;

    // Elements
    const chatWidget = $('#ai-chat-widget');
    const chatToggle = $('#chat-toggle');
    const chatWindow = $('#ai-chat-window');
    const platformMenu = $('#platform-menu');

    // Initialize
    init();

    function init() {
        setupEventListeners();
        loadConversation();
        clearOldConversations();
        setupAutoSave();

        // Check if bubble mode is enabled
        isBubbleMode = chatWidget.hasClass('bubble-mode');

        if (isBubbleMode) {
            setupPlatformBubbles();
        }
    }

    function setupEventListeners() {
        // Chat toggle button click
        chatToggle.on('click', function(e) {
            e.preventDefault();

            // Check if this is a single platform button (has data-platform attribute)
            const singlePlatform = $(this).data('platform');
            if (singlePlatform) {
                console.log('Single platform click:', singlePlatform);
                handlePlatformAction(singlePlatform);
                return;
            }

            // Get enabled platforms from menu items
            const enabledPlatforms = [];
            $('.platform-item').each(function() {
                enabledPlatforms.push($(this).data('platform'));
            });

            if (enabledPlatforms.length === 1) {
                // Single platform - direct action
                handlePlatformAction(enabledPlatforms[0]);
            } else if (isBubbleMode) {
                // Bubble mode - toggle platform bubbles
                togglePlatformBubbles();
            } else {
                // Traditional mode - show platform menu
                togglePlatformMenu();
            }
        });

        // Platform menu item click (traditional mode)
        $(document).on('click', '.platform-item', function(e) {
            e.preventDefault();
            const platform = $(this).data('platform');
            handlePlatformAction(platform);
            hidePlatformMenu();
        });

        // Platform bubble click (bubble mode)
        $(document).on('click', '.platform-bubble', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const platform = $(this).data('platform');
            handlePlatformAction(platform);
            hidePlatformBubbles();
        });

        // Chat window controls
        $('.fullscreen-btn').on('click', function(e) {
            e.preventDefault();
            toggleFullscreen();
        });

        $('.minimize-btn').on('click', function(e) {
            e.preventDefault();
            toggleMinimize();
        });

        $('.close-btn').on('click', function(e) {
            e.preventDefault();
            closeChatWindow();
        });

        // Chat input handling
        $('#chat-input').on('keypress', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // ESC key to exit fullscreen
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && isFullscreen) {
                exitFullscreen();
            }
        });

        $('#send-message').on('click', function(e) {
            e.preventDefault();
            sendMessage();
        });

        // Auto-resize textarea
        $('#chat-input').on('input', function() {
            autoResizeTextarea(this);
        });

        // Click outside to close
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#ai-chat-widget').length) {
                hidePlatformMenu();
                if (isBubbleMode) {
                    hidePlatformBubbles();
                }
            }
        });

        // Prevent chat window close when clicking inside
        chatWindow.on('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Handle platform action
     */
    function handlePlatformAction(platform) {
        console.log('Platform action:', platform);
        if (platform === 'ai-chat') {
            console.log('Opening AI chat window');
            toggleChatWindow();
        } else {
            console.log('Redirecting to platform:', platform);
            redirectToPlatform(platform);
        }
    }

    /**
     * Toggle platform menu
     */
    function togglePlatformMenu() {
        if (platformMenu.hasClass('show')) {
            hidePlatformMenu();
        } else {
            showPlatformMenu();
        }
    }

    /**
     * Show platform menu
     */
    function showPlatformMenu() {
        hideChatWindow();
        platformMenu.addClass('show');
        $('#chat-toggle').addClass('active');
        isPlatformMenuOpen = true;
    }

    /**
     * Hide platform menu
     */
    function hidePlatformMenu() {
        platformMenu.removeClass('show');
        $('#chat-toggle').removeClass('active');
        isPlatformMenuOpen = false;
    }

    /**
     * Toggle chat window
     */
    function toggleChatWindow() {
        console.log('Toggle chat window - isOpen:', isOpen, 'isMinimized:', isMinimized);
        console.log('Chat window element:', chatWindow.length);

        if (isOpen) {
            if (isMinimized) {
                maximizeChatWindow();
            } else {
                closeChatWindow();
            }
        } else {
            showChatWindow();
        }
    }

    /**
     * Show chat window
     */
    function showChatWindow() {
        hidePlatformMenu();
        hidePlatformBubbles();
        chatWindow.addClass('show');
        $('#chat-toggle').addClass('active');
        isOpen = true;
        isMinimized = false;

        // Focus input
        setTimeout(function() {
            $('#chat-input').focus();
        }, 300);

        // Scroll to bottom
        scrollToBottom();
    }

    /**
     * Hide chat window
     */
    function hideChatWindow() {
        chatWindow.removeClass('show');
        $('#chat-toggle').removeClass('active');
        isOpen = false;
        isMinimized = false;
    }

    /**
     * Close chat window
     */
    function closeChatWindow() {
        hideChatWindow();
    }

    /**
     * Toggle minimize
     */
    function toggleMinimize() {
        if (isMinimized) {
            maximizeChatWindow();
        } else {
            minimizeChatWindow();
        }
    }

    /**
     * Minimize chat window
     */
    function minimizeChatWindow() {
        // Exit fullscreen first if in fullscreen mode
        if (isFullscreen) {
            exitFullscreen();
        }

        chatWindow.addClass('minimized');
        isMinimized = true;

        // Update minimize button icon
        const minimizeBtn = $('.minimize-btn');
        minimizeBtn.find('i').removeClass('fa-minus').addClass('fa-plus');
        minimizeBtn.attr('title', aiChatFrontend.strings.maximize || '最大化');

        console.log('Chat window minimized');
    }

    /**
     * Maximize chat window
     */
    function maximizeChatWindow() {
        chatWindow.removeClass('minimized');
        isMinimized = false;

        // Update minimize button icon
        const minimizeBtn = $('.minimize-btn');
        minimizeBtn.find('i').removeClass('fa-plus').addClass('fa-minus');
        minimizeBtn.attr('title', aiChatFrontend.strings.minimize || '最小化');

        // Focus input
        setTimeout(function() {
            $('#chat-input').focus();
        }, 300);

        console.log('Chat window maximized');
    }

    /**
     * Toggle fullscreen
     */
    function toggleFullscreen() {
        if (isFullscreen) {
            exitFullscreen();
        } else {
            enterFullscreen();
        }
    }

    /**
     * Enter fullscreen mode
     */
    function enterFullscreen() {
        chatWindow.addClass('fullscreen');
        isFullscreen = true;

        // Update button title and icon
        const fullscreenBtn = $('.fullscreen-btn');
        fullscreenBtn.attr('title', aiChatFrontend.strings.exit_fullscreen || '退出全螢幕');
        fullscreenBtn.find('i').removeClass('fa-expand').addClass('fa-compress');

        // Ensure window is visible and not minimized
        if (!isOpen) {
            showChatWindow();
        }
        if (isMinimized) {
            maximizeChatWindow();
        }

        // Focus input
        setTimeout(function() {
            $('#chat-input').focus();
        }, 300);

        console.log('Entered fullscreen mode');
    }

    /**
     * Exit fullscreen mode
     */
    function exitFullscreen() {
        chatWindow.removeClass('fullscreen');
        isFullscreen = false;

        // Update button title and icon
        const fullscreenBtn = $('.fullscreen-btn');
        fullscreenBtn.attr('title', aiChatFrontend.strings.fullscreen || '全螢幕');
        fullscreenBtn.find('i').removeClass('fa-compress').addClass('fa-expand');

        console.log('Exited fullscreen mode');
    }

    /**
     * Redirect to platform
     */
    function redirectToPlatform(platform) {
        console.log('Redirecting to platform:', platform);
        console.log('AJAX URL:', aiChatFrontend.ajaxUrl);
        console.log('Nonce:', aiChatFrontend.nonce);

        $.post(aiChatFrontend.ajaxUrl, {
            action: 'ai_chat_get_platform_url',
            platform: platform,
            nonce: aiChatFrontend.nonce
        }, function(response) {
            console.log('AJAX Response:', response);
            if (response.success) {
                const url = response.data.url;
                console.log('Platform URL:', url);

                // Check if it's a WeChat QR Code
                if (url.startsWith('qr:')) {
                    const qrImageUrl = url.substring(3); // Remove 'qr:' prefix
                    showWeChatQRCode(qrImageUrl);
                } else {
                    window.open(url, '_blank');
                }
            } else {
                console.error('AJAX Error:', response.data);
                showError(response.data || aiChatFrontend.strings.error);
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX Fail:', xhr, status, error);
            showError(aiChatFrontend.strings.error);
        });
    }

    /**
     * Show WeChat QR Code modal
     */
    function showWeChatQRCode(imageUrl) {
        // 創建模態框
        const modalHtml = `
            <div id="wechat-qr-modal" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 1000000;
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <div style="
                    background: white;
                    padding: 30px;
                    border-radius: 15px;
                    text-align: center;
                    max-width: 400px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                ">
                    <h3 style="margin: 0 0 20px 0; color: #333; font-size: 18px;">
                        <i class="fab fa-weixin" style="color: #7bb32e; margin-right: 10px;"></i>
                        掃描 WeChat QR Code
                    </h3>
                    <img src="${imageUrl}" alt="WeChat QR Code" style="
                        max-width: 250px;
                        max-height: 250px;
                        border: 1px solid #ddd;
                        border-radius: 10px;
                        margin-bottom: 20px;
                    ">
                    <p style="margin: 0 0 20px 0; color: #666; font-size: 14px;">
                        使用 WeChat 掃描此 QR Code 開始聊天
                    </p>
                    <button onclick="closeWeChatQRModal()" style="
                        background: #7bb32e;
                        color: white;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 5px;
                        cursor: pointer;
                        font-size: 14px;
                    ">關閉</button>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        // 點擊背景關閉
        $('#wechat-qr-modal').click(function(e) {
            if (e.target === this) {
                closeWeChatQRModal();
            }
        });
    }

    /**
     * Close WeChat QR Code modal
     */
    function closeWeChatQRModal() {
        $('#wechat-qr-modal').remove();
    }

    // 確保函數在全局作用域中可用
    window.closeWeChatQRModal = closeWeChatQRModal;

    /**
     * Send message
     */
    function sendMessage() {
        const input = $('#chat-input');
        const message = input.val().trim();

        if (!message || isTyping) return;

        // Add user message
        addMessage('user', message);
        input.val('');

        // Show typing indicator
        showTypingIndicator();

        // 先保存用戶消息
        saveConversation();

        // Send to API
        $.post(aiChatFrontend.ajaxUrl, {
            action: 'ai_chat_send_message',
            message: message,
            conversation_id: conversationId,
            nonce: aiChatFrontend.nonce
        }, function(response) {
            hideTypingIndicator();

            if (response.success) {
                addMessage('bot', response.data.message);
                conversationId = response.data.conversation_id;
                // 立即保存包含 AI 回應的完整對話
                saveConversation();
                console.log('AI response received and conversation saved');
            } else {
                showError(response.data || aiChatFrontend.strings.error);
                // 即使 AI 回應失敗，也要保存用戶消息
                saveConversation();
            }
        }).fail(function() {
            hideTypingIndicator();
            showError(aiChatFrontend.strings.error);
            // 網絡錯誤時也要保存用戶消息
            saveConversation();
        });
    }

    /**
     * Add message to chat
     */
    function addMessage(sender, content, skipSave = false) {
        const messagesContainer = $('#chat-messages');
        const messageClass = sender === 'user' ? 'user-message' : 'bot-message';
        const avatar = sender === 'user' ?
            '<i class="fas fa-user"></i>' :
            '<i class="fas fa-robot"></i>';

        const messageHtml = `
            <div class="message ${messageClass}">
                <div class="message-avatar">${avatar}</div>
                <div class="message-content">
                    <p>${processMessageContent(content)}</p>
                    <span class="message-time">${getCurrentTime()}</span>
                </div>
            </div>
        `;

        messagesContainer.append(messageHtml);
        scrollToBottom();

        // 自動保存對話（除非明確跳過）
        if (!skipSave && conversationId) {
            setTimeout(function() {
                saveConversation();
            }, 100); // 短暫延遲確保 DOM 更新完成
        }
    }

    /**
     * Show typing indicator
     */
    function showTypingIndicator() {
        if (isTyping) return;

        isTyping = true;
        const messagesContainer = $('#chat-messages');

        const typingHtml = `
            <div class="message bot-message typing-indicator" id="typing-indicator">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <div class="typing-text">${aiChatFrontend.strings.typing}</div>
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        `;

        messagesContainer.append(typingHtml);

        // Trigger animation
        setTimeout(function() {
            $('#typing-indicator').addClass('show');
        }, 50);

        scrollToBottom();
    }

    /**
     * Hide typing indicator
     */
    function hideTypingIndicator() {
        isTyping = false;
        $('#typing-indicator').remove();
    }

    // Load conversation from localStorage
    function loadConversation() {
        try {
            const stored = localStorage.getItem('ai_chat_conversation');
            if (stored) {
                const data = JSON.parse(stored);
                const now = new Date().getTime();
                const storedTime = new Date(data.timestamp).getTime();
                const hoursDiff = (now - storedTime) / (1000 * 60 * 60);

                // Keep conversation for 24 hours
                if (hoursDiff < 24 && data.conversationId) {
                    conversationId = data.conversationId;


                    // Load conversation history if available
                    if (data.messages && data.messages.length > 0) {
                        const messagesContainer = $('#chat-messages');

                        // 清空除歡迎消息外的所有消息
                        messagesContainer.find('.message').not(':first').remove();

                        // 恢復保存的對話消息（跳過自動保存避免重複）
                        data.messages.forEach(function(msg) {
                            addMessage(msg.sender, msg.message, true); // skipSave = true
                        });


                    }
                } else {
                    // Clear expired conversation
                    localStorage.removeItem('ai_chat_conversation');
                    console.log('Cleared expired conversation from localStorage');
                }
            }
        } catch (e) {
            console.error('Error loading conversation:', e);
            localStorage.removeItem('ai_chat_conversation');
        }
    }

    function setupPlatformBubbles() {
        // Setup platform bubbles if needed
    }

    function setupAutoSave() {
        // 當頁面即將卸載時保存對話
        $(window).on('beforeunload', function() {
            if (conversationId) {
                saveConversation();
                console.log('Auto-saved conversation before page unload');
            }
        });

        // 當頁面可見性改變時保存對話（例如切換標籤頁）
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && conversationId) {
                saveConversation();
                console.log('Auto-saved conversation on visibility change');
            }
        });

        // 定期自動保存（每30秒）
        setInterval(function() {
            if (conversationId) {
                saveConversation();
                console.log('Auto-saved conversation (periodic)');
            }
        }, 30000);
    }

    function clearOldConversations() {
        try {
            const stored = localStorage.getItem('ai_chat_conversation');
            if (stored) {
                const data = JSON.parse(stored);
                const now = new Date().getTime();
                const storedTime = new Date(data.timestamp).getTime();
                const hoursDiff = (now - storedTime) / (1000 * 60 * 60);

                // Clear conversations older than 24 hours
                if (hoursDiff >= 24) {
                    localStorage.removeItem('ai_chat_conversation');

                }
            }
        } catch (e) {
            localStorage.removeItem('ai_chat_conversation');
        }
    }

    function togglePlatformBubbles() {
        // Toggle platform bubbles
    }

    function hidePlatformBubbles() {
        // Hide platform bubbles
    }

    function autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    function scrollToBottom() {
        const messagesContainer = $('#chat-messages');
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }

    function showError(message) {
        addMessage('bot', 'Error: ' + message);
    }

    function saveConversation() {
        if (!conversationId) return;

        try {
            // Collect current messages (skip welcome message)
            const messages = [];
            $('#chat-messages .message').each(function(index) {
                const $msg = $(this);

                // Skip typing indicator
                if ($msg.hasClass('typing-indicator')) return;

                // Skip the first message if it's the welcome message
                if (index === 0) {
                    const content = $msg.find('.message-content p').text().trim();
                    const isWelcomeMessage = content.includes('歡迎') || content.includes('Hello') || content.includes('help');
                    if (isWelcomeMessage) return;
                }

                const sender = $msg.hasClass('user-message') ? 'user' : 'bot';
                const content = $msg.find('.message-content p').text().trim();

                if (content) {
                    messages.push({
                        sender: sender,
                        message: content,
                        timestamp: new Date().toISOString()
                    });
                }
            });

            const conversationData = {
                conversationId: conversationId,
                messages: messages,
                timestamp: new Date().toISOString(),
                lastActivity: new Date().toISOString()
            };

            localStorage.setItem('ai_chat_conversation', JSON.stringify(conversationData));
        } catch (e) {
            // Silently handle storage errors
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Process message content to make links clickable while escaping other HTML
     */
    function processMessageContent(content) {
        // Store original URLs before escaping
        const urlPlaceholders = [];
        let processed = content;

        // Extract URLs and replace with placeholders - Fix link extraction issue
        const urlRegex = /(https?:\/\/[^\s<>"{}|\\^`\[\]()（）]+)/g;
        processed = processed.replace(urlRegex, function(match) {
            // Clean URL, remove trailing punctuation
            let cleanUrl = match.replace(/[)）.,;:!?]+$/, '');
            const placeholder = `__URL_PLACEHOLDER_${urlPlaceholders.length}__`;
            urlPlaceholders.push(cleanUrl);
            return placeholder;
        });

        // Extract email addresses and replace with placeholders
        const emailPlaceholders = [];
        const emailRegex = /\b([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/g;
        processed = processed.replace(emailRegex, function(match) {
            const placeholder = `__EMAIL_PLACEHOLDER_${emailPlaceholders.length}__`;
            emailPlaceholders.push(match);
            return placeholder;
        });

        // Extract www links and replace with placeholders
        const wwwPlaceholders = [];
        const wwwRegex = /\b(www\.[^\s<>"{}|\\^`\[\]()（）]+)/g;
        processed = processed.replace(wwwRegex, function(match) {
            // Clean www link, remove trailing punctuation
            let cleanWww = match.replace(/[)）.,;:!?]+$/, '');
            const placeholder = `__WWW_PLACEHOLDER_${wwwPlaceholders.length}__`;
            wwwPlaceholders.push(cleanWww);
            return placeholder;
        });

        // Now escape HTML
        processed = escapeHtml(processed);

        // Convert line breaks to <br> tags
        processed = processed.replace(/\n/g, '<br>');

        // Restore URLs as clickable links
        urlPlaceholders.forEach((url, index) => {
            const placeholder = `__URL_PLACEHOLDER_${index}__`;
            processed = processed.replace(placeholder, `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`);
        });

        // Restore emails as mailto links
        emailPlaceholders.forEach((email, index) => {
            const placeholder = `__EMAIL_PLACEHOLDER_${index}__`;
            processed = processed.replace(placeholder, `<a href="mailto:${email}">${email}</a>`);
        });

        // Restore www links
        wwwPlaceholders.forEach((www, index) => {
            const placeholder = `__WWW_PLACEHOLDER_${index}__`;
            processed = processed.replace(placeholder, `<a href="http://${www}" target="_blank" rel="noopener noreferrer">${www}</a>`);
        });

        return processed;
    }

    function getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
});
