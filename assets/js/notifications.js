/**
 * AI Chat Notifications JavaScript
 * 
 * Handles real-time notifications using Server-Sent Events
 */

(function($) {
    'use strict';
    
    var AIChatNotifications = {
        eventSource: null,
        notificationContainer: null,
        isConnected: false,
        reconnectAttempts: 0,
        maxReconnectAttempts: 5,
        
        /**
         * Initialize notifications
         */
        init: function() {
            this.createNotificationContainer();
            this.bindEvents();
            this.connectSSE();
            this.loadUnreadNotifications();
            this.addNotificationStyles();
        },
        
        /**
         * Create notification container
         */
        createNotificationContainer: function() {
            if ($('#ai-chat-notifications-container').length === 0) {
                $('body').append(`
                    <div id="ai-chat-notifications-container">
                        <div id="ai-chat-notifications-header">
                            <h3>${aiChatNotifications.strings.newMessage}</h3>
                            <button id="ai-chat-mark-all-read">${aiChatNotifications.strings.markAllRead}</button>
                            <button id="ai-chat-close-notifications">&times;</button>
                        </div>
                        <div id="ai-chat-notifications-list"></div>
                    </div>
                `);
            }
            this.notificationContainer = $('#ai-chat-notifications-container');
        },
        
        /**
         * Add notification styles
         */
        addNotificationStyles: function() {
            if ($('#ai-chat-notification-styles').length === 0) {
                $('head').append(`
                    <style id="ai-chat-notification-styles">
                        #ai-chat-notifications-container {
                            position: fixed;
                            top: 32px;
                            right: 20px;
                            width: 350px;
                            max-height: 500px;
                            background: #fff;
                            border: 1px solid #ddd;
                            border-radius: 8px;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            z-index: 9999;
                            display: none;
                            overflow: hidden;
                        }
                        
                        #ai-chat-notifications-header {
                            background: #0073aa;
                            color: #fff;
                            padding: 15px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        
                        #ai-chat-notifications-header h3 {
                            margin: 0;
                            font-size: 16px;
                        }
                        
                        #ai-chat-mark-all-read,
                        #ai-chat-close-notifications {
                            background: transparent;
                            border: 1px solid rgba(255,255,255,0.3);
                            color: #fff;
                            padding: 5px 10px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 12px;
                        }
                        
                        #ai-chat-close-notifications {
                            padding: 5px 8px;
                            font-size: 16px;
                            margin-left: 10px;
                        }
                        
                        #ai-chat-notifications-list {
                            max-height: 400px;
                            overflow-y: auto;
                        }
                        
                        .ai-chat-notification-item {
                            border-bottom: 1px solid #eee;
                            padding: 15px;
                            position: relative;
                            cursor: pointer;
                            transition: background-color 0.2s;
                        }
                        
                        .ai-chat-notification-item:hover {
                            background: #f8f9fa;
                        }
                        
                        .ai-chat-notification-item.unread {
                            background: #f0f8ff;
                            border-left: 4px solid #0073aa;
                        }
                        
                        .ai-chat-notification-platform {
                            font-size: 12px;
                            color: #666;
                            text-transform: uppercase;
                            font-weight: bold;
                            margin-bottom: 5px;
                        }
                        
                        .ai-chat-notification-sender {
                            font-weight: bold;
                            color: #333;
                            margin-bottom: 5px;
                        }
                        
                        .ai-chat-notification-message {
                            color: #666;
                            font-size: 14px;
                            line-height: 1.4;
                            margin-bottom: 5px;
                        }
                        
                        .ai-chat-notification-time {
                            font-size: 11px;
                            color: #999;
                        }
                        
                        .ai-chat-notification-count {
                            background: #d63638;
                            color: #fff;
                            border-radius: 10px;
                            padding: 2px 6px;
                            font-size: 11px;
                            font-weight: bold;
                            margin-left: 5px;
                            min-width: 16px;
                            text-align: center;
                            display: inline-block;
                        }
                        
                        .ai-chat-notification-indicator.has-notifications .ab-icon::after {
                            content: '';
                            position: absolute;
                            top: 6px;
                            right: 6px;
                            width: 8px;
                            height: 8px;
                            background: #d63638;
                            border-radius: 50%;
                            border: 2px solid #fff;
                        }
                        
                        .ai-chat-toast-notification {
                            position: fixed;
                            top: 50px;
                            right: 20px;
                            background: #fff;
                            border: 1px solid #ddd;
                            border-left: 4px solid #0073aa;
                            border-radius: 4px;
                            padding: 15px;
                            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                            z-index: 10000;
                            max-width: 300px;
                            animation: slideIn 0.3s ease;
                        }
                        
                        @keyframes slideIn {
                            from { transform: translateX(100%); opacity: 0; }
                            to { transform: translateX(0); opacity: 1; }
                        }
                    </style>
                `);
            }
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Toggle notifications panel
            $(document).on('click', '#wp-admin-bar-ai-chat-notifications', function(e) {
                e.preventDefault();
                self.toggleNotifications();
            });
            
            // Close notifications
            $(document).on('click', '#ai-chat-close-notifications', function() {
                self.hideNotifications();
            });
            
            // Mark all as read
            $(document).on('click', '#ai-chat-mark-all-read', function() {
                self.markAllAsRead();
            });
            
            // Click on notification item
            $(document).on('click', '.ai-chat-notification-item', function() {
                var notificationId = $(this).data('notification-id');
                var conversationId = $(this).data('conversation-id');
                
                self.markAsRead(notificationId);
                self.redirectToConversation(conversationId);
            });
            
            // Close notifications when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#ai-chat-notifications-container, #wp-admin-bar-ai-chat-notifications').length) {
                    self.hideNotifications();
                }
            });
        },
        
        /**
         * Connect to Server-Sent Events
         */
        connectSSE: function() {
            var self = this;
            
            if (typeof(EventSource) === "undefined") {
                console.log('Server-Sent Events not supported');
                return;
            }
            
            if (this.eventSource) {
                this.eventSource.close();
            }
            
            this.eventSource = new EventSource(aiChatNotifications.sseUrl);
            
            this.eventSource.onopen = function() {
                self.isConnected = true;
                self.reconnectAttempts = 0;
                console.log('SSE connection opened');
            };
            
            this.eventSource.onmessage = function(event) {
                try {
                    var notification = JSON.parse(event.data);
                    self.handleNewNotification(notification);
                } catch (e) {
                    console.error('Error parsing notification data:', e);
                }
            };
            
            this.eventSource.addEventListener('heartbeat', function(event) {
                // Handle heartbeat - connection is alive
            });
            
            this.eventSource.onerror = function(event) {
                self.isConnected = false;
                console.log('SSE connection error');
                
                if (self.reconnectAttempts < self.maxReconnectAttempts) {
                    setTimeout(function() {
                        self.reconnectAttempts++;
                        console.log('Attempting to reconnect SSE... Attempt ' + self.reconnectAttempts);
                        self.connectSSE();
                    }, 5000 * self.reconnectAttempts); // Exponential backoff
                } else {
                    console.log('Max reconnection attempts reached');
                }
            };
        },
        
        /**
         * Handle new notification
         */
        handleNewNotification: function(notification) {
            this.showToastNotification(notification);
            this.updateNotificationIndicator();
            this.addNotificationToList(notification);
            
            // Play notification sound if supported
            this.playNotificationSound();
        },
        
        /**
         * Show toast notification
         */
        showToastNotification: function(notification) {
            var toast = $(`
                <div class="ai-chat-toast-notification">
                    <div class="ai-chat-notification-platform">${notification.platform}</div>
                    <div class="ai-chat-notification-sender">${aiChatNotifications.strings.from} ${notification.sender_name}</div>
                    <div class="ai-chat-notification-message">${notification.message}</div>
                </div>
            `);
            
            $('body').append(toast);
            
            // Auto remove after 5 seconds
            setTimeout(function() {
                toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Click to view
            toast.on('click', function() {
                window.location.href = 'admin.php?page=ai-chat-history&conversation=' + notification.conversation_id;
            });
        },
        
        /**
         * Play notification sound
         */
        playNotificationSound: function() {
            try {
                var audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmMeEjuR2u29YiUCMI3Z7bNaEwlPo+nrpUwRDVek6+OcWRQIQKHi8LVUDR5PolXi5L1uHQgzfNr07aZcEApToevnqlYWCTyO3+ORYh0NUq/g769dGA0ygNvq0WMiBzaL2u7NdioGLYLM7tiJOQYWa7ztzHEiBC+D0+/OdioGLILL79qIOgQYar/0xnEiBC6C0+/NdSoFLYLM7duJOQUTasPx05pWGAlGnePzxmQiBjuH2+a8+5pWGAlGnePzxmQiB/7DbGFg3uCOF');
                audio.play().catch(function(e) {
                    // Ignore audio play errors
                });
            } catch (e) {
                // Ignore audio errors
            }
        },
        
        /**
         * Load unread notifications
         */
        loadUnreadNotifications: function() {
            var self = this;
            
            $.ajax({
                url: aiChatNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chat_get_unread_notifications',
                    nonce: aiChatNotifications.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateNotificationsList(response.data);
                        self.updateNotificationIndicator();
                    }
                }
            });
        },
        
        /**
         * Update notifications list
         */
        updateNotificationsList: function(notifications) {
            var list = $('#ai-chat-notifications-list');
            list.empty();
            
            if (notifications.length === 0) {
                list.append(`<div style="padding: 20px; text-align: center; color: #666;">${aiChatNotifications.strings.noNotifications}</div>`);
                return;
            }
            
            notifications.forEach(function(notification) {
                this.addNotificationToList(notification);
            }.bind(this));
        },
        
        /**
         * Add notification to list
         */
        addNotificationToList: function(notification) {
            var timeAgo = this.timeAgo(notification.created_at);
            var item = $(`
                <div class="ai-chat-notification-item ${notification.is_read == 0 ? 'unread' : ''}" 
                     data-notification-id="${notification.id}" 
                     data-conversation-id="${notification.conversation_id}">
                    <div class="ai-chat-notification-platform">${notification.platform}</div>
                    <div class="ai-chat-notification-sender">${notification.sender_name}</div>
                    <div class="ai-chat-notification-message">${notification.message}</div>
                    <div class="ai-chat-notification-time">${timeAgo}</div>
                </div>
            `);
            
            $('#ai-chat-notifications-list').prepend(item);
        },
        
        /**
         * Toggle notifications panel
         */
        toggleNotifications: function() {
            if (this.notificationContainer.is(':visible')) {
                this.hideNotifications();
            } else {
                this.showNotifications();
            }
        },
        
        /**
         * Show notifications panel
         */
        showNotifications: function() {
            this.notificationContainer.slideDown(200);
            this.loadUnreadNotifications();
        },
        
        /**
         * Hide notifications panel
         */
        hideNotifications: function() {
            this.notificationContainer.slideUp(200);
        },
        
        /**
         * Mark notification as read
         */
        markAsRead: function(notificationId) {
            $.ajax({
                url: aiChatNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chat_mark_notification_read',
                    notification_id: notificationId,
                    nonce: aiChatNotifications.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('[data-notification-id="' + notificationId + '"]').removeClass('unread');
                        this.updateNotificationIndicator();
                    }
                }.bind(this)
            });
        },
        
        /**
         * Mark all notifications as read
         */
        markAllAsRead: function() {
            $.ajax({
                url: aiChatNotifications.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_chat_mark_all_read',
                    nonce: aiChatNotifications.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.ai-chat-notification-item').removeClass('unread');
                        this.updateNotificationIndicator();
                    }
                }.bind(this)
            });
        },
        
        /**
         * Update notification indicator
         */
        updateNotificationIndicator: function() {
            var unreadCount = $('.ai-chat-notification-item.unread').length;
            var indicator = $('#wp-admin-bar-ai-chat-notifications');
            
            if (unreadCount > 0) {
                indicator.addClass('has-notifications');
                var countElement = indicator.find('.ai-chat-notification-count');
                if (countElement.length === 0) {
                    indicator.find('.ab-item').append('<span class="ai-chat-notification-count">' + unreadCount + '</span>');
                } else {
                    countElement.text(unreadCount);
                }
            } else {
                indicator.removeClass('has-notifications');
                indicator.find('.ai-chat-notification-count').remove();
            }
        },
        
        /**
         * Redirect to conversation
         */
        redirectToConversation: function(conversationId) {
            window.location.href = 'admin.php?page=ai-chat-history&conversation=' + conversationId;
        },
        
        /**
         * Calculate time ago
         */
        timeAgo: function(dateString) {
            var now = new Date();
            var date = new Date(dateString);
            var seconds = Math.floor((now - date) / 1000);
            
            var intervals = {
                year: 31536000,
                month: 2592000,
                week: 604800,
                day: 86400,
                hour: 3600,
                minute: 60
            };
            
            for (var unit in intervals) {
                var interval = Math.floor(seconds / intervals[unit]);
                if (interval >= 1) {
                    return interval + ' ' + unit + (interval > 1 ? 's' : '') + ' ago';
                }
            }
            
            return 'just now';
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        AIChatNotifications.init();
    });
    
})(jQuery);
