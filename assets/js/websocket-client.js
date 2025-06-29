/**
 * AI Chat WebSocket Client
 * 
 * Handles real-time WebSocket connections for live notifications
 * and instant messaging functionality
 */

class AIChatWebSocketClient {
    constructor(options = {}) {
        this.options = {
            host: options.host || 'localhost',
            port: options.port || 8080,
            secure: options.secure || false,
            reconnectInterval: options.reconnectInterval || 3000,
            maxReconnectAttempts: options.maxReconnectAttempts || 10,
            heartbeatInterval: options.heartbeatInterval || 30000,
            authToken: options.authToken || '',
            userId: options.userId || '',
            debug: false,
            ...options
        };
        
        this.ws = null;
        this.reconnectAttempts = 0;
        this.isConnected = false;
        this.heartbeatTimer = null;
        this.connectionTimer = null;
        this.messageQueue = [];
        this.eventListeners = {};
        
        // Bind methods
        this.connect = this.connect.bind(this);
        this.disconnect = this.disconnect.bind(this);
        this.reconnect = this.reconnect.bind(this);
        this.send = this.send.bind(this);
        this.handleMessage = this.handleMessage.bind(this);
        this.handleOpen = this.handleOpen.bind(this);
        this.handleClose = this.handleClose.bind(this);
        this.handleError = this.handleError.bind(this);
        
        // Initialize connection
        if (this.options.autoConnect !== false) {
            this.connect();
        }
    }
    
    /**
     * Connect to WebSocket server
     */
    connect() {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.debug('Already connected');
            return;
        }
        
        const protocol = this.options.secure ? 'wss' : 'ws';
        const url = `${protocol}://${this.options.host}:${this.options.port}`;
        
        this.debug('Connecting to:', url);
        
        try {
            this.ws = new WebSocket(url);
            this.setupEventListeners();
            
            // Set connection timeout
            this.connectionTimer = setTimeout(() => {
                if (this.ws.readyState !== WebSocket.OPEN) {
                    this.debug('Connection timeout');
                    this.ws.close();
                    this.handleReconnect();
                }
            }, 10000);
            
        } catch (error) {
            this.debug('Connection error:', error);
            this.handleReconnect();
        }
    }
    
    /**
     * Setup WebSocket event listeners
     */
    setupEventListeners() {
        if (!this.ws) return;
        
        this.ws.onopen = this.handleOpen;
        this.ws.onmessage = this.handleMessage;
        this.ws.onclose = this.handleClose;
        this.ws.onerror = this.handleError;
    }
    
    /**
     * Handle WebSocket open event
     */
    handleOpen(event) {
        this.debug('WebSocket connected');
        
        this.isConnected = true;
        this.reconnectAttempts = 0;
        
        if (this.connectionTimer) {
            clearTimeout(this.connectionTimer);
            this.connectionTimer = null;
        }
        
        // Send authentication if required
        if (this.options.authToken) {
            this.authenticate();
        }
        
        // Send any queued messages
        this.flushMessageQueue();
        
        // Start heartbeat
        this.startHeartbeat();
        
        // Emit connected event
        this.emit('connected', event);
    }
    
    /**
     * Handle WebSocket message event
     */
    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.debug('Received message:', data);
            
            switch (data.type) {
                case 'auth_response':
                    this.handleAuthResponse(data);
                    break;
                    
                case 'heartbeat':
                    this.handleHeartbeat(data);
                    break;
                    
                case 'notification':
                    this.handleNotification(data);
                    break;
                    
                case 'message':
                    this.handleChatMessage(data);
                    break;
                    
                case 'user_status':
                    this.handleUserStatus(data);
                    break;
                    
                case 'system_alert':
                    this.handleSystemAlert(data);
                    break;
                    
                case 'error':
                    this.handleServerError(data);
                    break;
                    
                default:
                    this.emit('message', data);
                    break;
            }
            
        } catch (error) {
            this.debug('Error parsing message:', error);
        }
    }
    
    /**
     * Handle WebSocket close event
     */
    handleClose(event) {
        this.debug('WebSocket disconnected:', event.code, event.reason);
        
        this.isConnected = false;
        this.stopHeartbeat();
        
        if (this.connectionTimer) {
            clearTimeout(this.connectionTimer);
            this.connectionTimer = null;
        }
        
        // Emit disconnected event
        this.emit('disconnected', event);
        
        // Attempt reconnection if not intentional
        if (event.code !== 1000 && this.options.autoReconnect !== false) {
            this.handleReconnect();
        }
    }
    
    /**
     * Handle WebSocket error event
     */
    handleError(error) {
        this.debug('WebSocket error:', error);
        this.emit('error', error);
    }
    
    /**
     * Handle reconnection logic
     */
    handleReconnect() {
        if (this.reconnectAttempts >= this.options.maxReconnectAttempts) {
            this.debug('Max reconnection attempts reached');
            this.emit('reconnect_failed');
            return;
        }
        
        this.reconnectAttempts++;
        this.debug(`Reconnection attempt ${this.reconnectAttempts}/${this.options.maxReconnectAttempts}`);
        
        setTimeout(() => {
            this.emit('reconnecting', this.reconnectAttempts);
            this.connect();
        }, this.options.reconnectInterval);
    }
    
    /**
     * Disconnect from WebSocket server
     */
    disconnect() {
        this.debug('Disconnecting...');
        
        this.stopHeartbeat();
        
        if (this.connectionTimer) {
            clearTimeout(this.connectionTimer);
            this.connectionTimer = null;
        }
        
        if (this.ws) {
            this.ws.close(1000, 'Client disconnecting');
            this.ws = null;
        }
        
        this.isConnected = false;
    }
    
    /**
     * Send message to server
     */
    send(data) {
        if (!this.isConnected || !this.ws || this.ws.readyState !== WebSocket.OPEN) {
            this.debug('Not connected, queuing message:', data);
            this.messageQueue.push(data);
            return false;
        }
        
        try {
            const message = typeof data === 'string' ? data : JSON.stringify(data);
            this.ws.send(message);
            this.debug('Sent message:', data);
            return true;
        } catch (error) {
            this.debug('Error sending message:', error);
            return false;
        }
    }
    
    /**
     * Flush queued messages
     */
    flushMessageQueue() {
        while (this.messageQueue.length > 0) {
            const message = this.messageQueue.shift();
            this.send(message);
        }
    }
    
    /**
     * Authenticate with server
     */
    authenticate() {
        this.send({
            type: 'authenticate',
            token: this.options.authToken,
            userId: this.options.userId
        });
    }
    
    /**
     * Handle authentication response
     */
    handleAuthResponse(data) {
        if (data.success) {
            this.debug('Authentication successful');
            this.emit('authenticated', data);
        } else {
            this.debug('Authentication failed:', data.error);
            this.emit('auth_failed', data);
        }
    }
    
    /**
     * Start heartbeat mechanism
     */
    startHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
        }
        
        this.heartbeatTimer = setInterval(() => {
            if (this.isConnected) {
                this.send({
                    type: 'heartbeat',
                    timestamp: Date.now()
                });
            }
        }, this.options.heartbeatInterval);
    }
    
    /**
     * Stop heartbeat mechanism
     */
    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }
    
    /**
     * Handle heartbeat response
     */
    handleHeartbeat(data) {
        this.debug('Heartbeat received');
        this.emit('heartbeat', data);
    }
    
    /**
     * Handle notification message
     */
    handleNotification(data) {
        this.debug('Notification received:', data);
        
        // Show browser notification if supported and permitted
        if ('Notification' in window && Notification.permission === 'granted') {
            this.showBrowserNotification(data);
        }
        
        // Show in-app notification
        this.showInAppNotification(data);
        
        this.emit('notification', data);
    }
    
    /**
     * Handle chat message
     */
    handleChatMessage(data) {
        this.debug('Chat message received:', data);
        
        // Update chat interface
        this.updateChatInterface(data);
        
        this.emit('chat_message', data);
    }
    
    /**
     * Handle user status change
     */
    handleUserStatus(data) {
        this.debug('User status change:', data);
        
        // Update user status in interface
        this.updateUserStatus(data);
        
        this.emit('user_status', data);
    }
    
    /**
     * Handle system alert
     */
    handleSystemAlert(data) {
        this.debug('System alert:', data);
        
        // Show system alert
        this.showSystemAlert(data);
        
        this.emit('system_alert', data);
    }
    
    /**
     * Handle server error
     */
    handleServerError(data) {
        this.debug('Server error:', data);
        this.emit('server_error', data);
    }
    
    /**
     * Show browser notification
     */
    showBrowserNotification(data) {
        const notification = new Notification(data.title || 'AI Chat', {
            body: data.message || data.content,
            icon: data.icon || '/wp-content/plugins/ai-chat/assets/images/notification-icon.png',
            tag: 'ai-chat-' + (data.id || Date.now()),
            requireInteraction: data.persistent || false
        });
        
        notification.onclick = () => {
            window.focus();
            if (data.url) {
                window.location.href = data.url;
            }
            notification.close();
        };
        
        // Auto-close after delay
        setTimeout(() => {
            notification.close();
        }, data.duration || 5000);
    }
    
    /**
     * Show in-app notification
     */
    showInAppNotification(data) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'ai-chat-notification';
        notification.innerHTML = `
            <div class="notification-icon">
                <i class="${data.icon || 'fas fa-bell'}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">${data.title || 'New Message'}</div>
                <div class="notification-message">${data.message || data.content}</div>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add to notification container
        let container = document.querySelector('.ai-chat-notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'ai-chat-notifications-container';
            document.body.appendChild(container);
        }
        
        container.appendChild(notification);
        
        // Auto-remove after delay
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, data.duration || 5000);
    }
    
    /**
     * Update chat interface with new message
     */
    updateChatInterface(data) {
        const chatMessages = document.querySelector('#chat-messages');
        if (!chatMessages) return;
        
        const messageElement = document.createElement('div');
        messageElement.className = `message ${data.sender_type}-message`;
        messageElement.innerHTML = `
            <div class="message-avatar">
                <img src="${data.avatar || '/wp-content/plugins/ai-chat/assets/images/default-avatar.png'}" alt="${data.sender_name}">
            </div>
            <div class="message-content">
                <div class="message-text">${data.content}</div>
                <div class="message-time">${new Date(data.timestamp).toLocaleTimeString()}</div>
            </div>
        `;
        
        chatMessages.appendChild(messageElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    /**
     * Update user status in interface
     */
    updateUserStatus(data) {
        const statusElements = document.querySelectorAll(`[data-user-id="${data.user_id}"]`);
        statusElements.forEach(element => {
            element.classList.remove('online', 'offline', 'away');
            element.classList.add(data.status);
            
            const statusText = element.querySelector('.status-text');
            if (statusText) {
                statusText.textContent = data.status_text || data.status;
            }
        });
    }
    
    /**
     * Show system alert
     */
    showSystemAlert(data) {
        const alert = document.createElement('div');
        alert.className = `ai-chat-system-alert alert-${data.level || 'info'}`;
        alert.innerHTML = `
            <div class="alert-icon">
                <i class="${this.getAlertIcon(data.level)}"></i>
            </div>
            <div class="alert-content">
                <div class="alert-title">${data.title || 'System Alert'}</div>
                <div class="alert-message">${data.message}</div>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto-remove for non-critical alerts
        if (data.level !== 'error' && data.level !== 'critical') {
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, data.duration || 8000);
        }
    }
    
    /**
     * Get alert icon based on level
     */
    getAlertIcon(level) {
        const icons = {
            'info': 'fas fa-info-circle',
            'success': 'fas fa-check-circle',
            'warning': 'fas fa-exclamation-triangle',
            'error': 'fas fa-exclamation-circle',
            'critical': 'fas fa-radiation'
        };
        
        return icons[level] || icons.info;
    }
    
    /**
     * Send chat message
     */
    sendChatMessage(content, recipient = null) {
        return this.send({
            type: 'chat_message',
            content: content,
            recipient: recipient,
            timestamp: Date.now()
        });
    }
    
    /**
     * Join chat room
     */
    joinRoom(roomId) {
        return this.send({
            type: 'join_room',
            room_id: roomId
        });
    }
    
    /**
     * Leave chat room
     */
    leaveRoom(roomId) {
        return this.send({
            type: 'leave_room',
            room_id: roomId
        });
    }
    
    /**
     * Update user status
     */
    updateStatus(status, message = '') {
        return this.send({
            type: 'status_update',
            status: status,
            message: message
        });
    }
    
    /**
     * Request browser notification permission
     */
    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            return Notification.requestPermission();
        }
        return Promise.resolve(Notification.permission);
    }
    
    /**
     * Add event listener
     */
    on(event, callback) {
        if (!this.eventListeners[event]) {
            this.eventListeners[event] = [];
        }
        this.eventListeners[event].push(callback);
    }
    
    /**
     * Remove event listener
     */
    off(event, callback) {
        if (!this.eventListeners[event]) return;
        
        const index = this.eventListeners[event].indexOf(callback);
        if (index > -1) {
            this.eventListeners[event].splice(index, 1);
        }
    }
    
    /**
     * Emit event to listeners
     */
    emit(event, data) {
        if (!this.eventListeners[event]) return;
        
        this.eventListeners[event].forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                // Silently handle callback errors
            }
        });
    }
    
    /**
     * Get connection status
     */
    getStatus() {
        return {
            connected: this.isConnected,
            readyState: this.ws ? this.ws.readyState : WebSocket.CLOSED,
            reconnectAttempts: this.reconnectAttempts,
            queuedMessages: this.messageQueue.length
        };
    }
    
    /**
     * Debug logging (disabled in production)
     */
    debug(...args) {
        // Debug logging disabled in production
    }
}

// CSS styles for notifications and alerts
const websocketStyles = `
<style>
.ai-chat-notifications-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    pointer-events: none;
}

.ai-chat-notification {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-bottom: 10px;
    padding: 15px;
    min-width: 300px;
    max-width: 400px;
    display: flex;
    align-items: flex-start;
    pointer-events: auto;
    animation: slideInRight 0.3s ease-out;
}

.ai-chat-notification .notification-icon {
    color: #0073aa;
    margin-right: 12px;
    font-size: 18px;
    margin-top: 2px;
}

.ai-chat-notification .notification-content {
    flex: 1;
}

.ai-chat-notification .notification-title {
    font-weight: bold;
    margin-bottom: 4px;
    color: #333;
}

.ai-chat-notification .notification-message {
    color: #666;
    font-size: 14px;
    line-height: 1.4;
}

.ai-chat-notification .notification-close {
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 0;
    margin-left: 10px;
    font-size: 16px;
}

.ai-chat-notification .notification-close:hover {
    color: #333;
}

.ai-chat-system-alert {
    position: fixed;
    top: 50px;
    left: 50%;
    transform: translateX(-50%);
    background: #fff;
    border: 2px solid;
    border-radius: 8px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    padding: 20px;
    min-width: 350px;
    max-width: 500px;
    z-index: 10001;
    display: flex;
    align-items: flex-start;
    animation: slideInDown 0.3s ease-out;
}

.ai-chat-system-alert.alert-info {
    border-color: #0073aa;
}

.ai-chat-system-alert.alert-success {
    border-color: #46b450;
}

.ai-chat-system-alert.alert-warning {
    border-color: #ffb900;
}

.ai-chat-system-alert.alert-error {
    border-color: #dc3232;
}

.ai-chat-system-alert.alert-critical {
    border-color: #d63638;
    background: #fef7f7;
}

.ai-chat-system-alert .alert-icon {
    margin-right: 15px;
    font-size: 24px;
    margin-top: 2px;
}

.ai-chat-system-alert.alert-info .alert-icon {
    color: #0073aa;
}

.ai-chat-system-alert.alert-success .alert-icon {
    color: #46b450;
}

.ai-chat-system-alert.alert-warning .alert-icon {
    color: #ffb900;
}

.ai-chat-system-alert.alert-error .alert-icon,
.ai-chat-system-alert.alert-critical .alert-icon {
    color: #dc3232;
}

.ai-chat-system-alert .alert-content {
    flex: 1;
}

.ai-chat-system-alert .alert-title {
    font-weight: bold;
    margin-bottom: 8px;
    color: #333;
    font-size: 16px;
}

.ai-chat-system-alert .alert-message {
    color: #666;
    line-height: 1.5;
}

.ai-chat-system-alert .alert-close {
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 0;
    margin-left: 15px;
    font-size: 18px;
}

.ai-chat-system-alert .alert-close:hover {
    color: #333;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideInDown {
    from {
        transform: translate(-50%, -100%);
        opacity: 0;
    }
    to {
        transform: translate(-50%, 0);
        opacity: 1;
    }
}

.user-status-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 5px;
}

.user-status-indicator.online {
    background-color: #46b450;
}

.user-status-indicator.away {
    background-color: #ffb900;
}

.user-status-indicator.offline {
    background-color: #999;
}
</style>
`;

// Inject styles
if (typeof document !== 'undefined') {
    document.head.insertAdjacentHTML('beforeend', websocketStyles);
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AIChatWebSocketClient;
} else if (typeof window !== 'undefined') {
    window.AIChatWebSocketClient = AIChatWebSocketClient;
}
