<?php
/**
 * Database functionality for AI Chat Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Database {
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
          // Conversations table
        $conversations_table = $wpdb->prefix . 'ai_chat_conversations';
        $conversations_sql = "CREATE TABLE $conversations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            conversation_id varchar(100) NOT NULL,
            user_ip varchar(45) NOT NULL,
            user_agent text,
            platform varchar(50) DEFAULT 'ai-chat',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY conversation_id (conversation_id),
            KEY user_ip (user_ip),
            KEY created_at (created_at),
            KEY platform (platform)
        ) $charset_collate;";
        
        // Messages table
        $messages_table = $wpdb->prefix . 'ai_chat_messages';
        $messages_sql = "CREATE TABLE $messages_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            conversation_id varchar(100) NOT NULL,
            sender varchar(20) NOT NULL,
            message LONGTEXT NOT NULL,
            message_type varchar(20) DEFAULT 'text',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Chat analytics table
        $analytics_table = $wpdb->prefix . 'ai_chat_analytics';
        $analytics_sql = "CREATE TABLE $analytics_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            platform varchar(50) NOT NULL,
            total_conversations int DEFAULT 0,
            total_messages int DEFAULT 0,
            avg_conversation_length decimal(10,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date_platform (date, platform),
            KEY date (date)
        ) $charset_collate;";
          // Notifications table
        $notifications_table = $wpdb->prefix . 'ai_chat_notifications';
        $notifications_sql = "CREATE TABLE $notifications_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            conversation_id varchar(100) NOT NULL,
            platform varchar(50) NOT NULL,
            message text NOT NULL,
            sender_name varchar(100) DEFAULT '',
            sender_id varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_read tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY created_at (created_at),
            KEY is_read (is_read)
        ) $charset_collate;";
        
        // Test results table for automated testing
        $test_results_table = $wpdb->prefix . 'ai_chat_test_results';
        $test_results_sql = "CREATE TABLE $test_results_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            test_type varchar(50) NOT NULL,
            test_suite varchar(50) DEFAULT '',
            results longtext,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            total_tests int DEFAULT 0,
            passed_tests int DEFAULT 0,
            failed_tests int DEFAULT 0,
            execution_time decimal(10,3) DEFAULT 0,
            status varchar(20) DEFAULT 'completed',
            PRIMARY KEY (id),
            KEY test_type (test_type),
            KEY test_suite (test_suite),
            KEY timestamp (timestamp),
            KEY status (status)
        ) $charset_collate;";

        // AI data sources table
        $data_sources_table = $wpdb->prefix . 'ai_chat_data_sources';
        $data_sources_sql = "CREATE TABLE $data_sources_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            title varchar(200) DEFAULT '',
            content_preview text,
            status varchar(20) DEFAULT 'active',
            last_fetched datetime DEFAULT NULL,
            fetch_count int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

          require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
          dbDelta($conversations_sql);
        dbDelta($messages_sql);
        dbDelta($analytics_sql);
        dbDelta($notifications_sql);
        dbDelta($test_results_sql);
        dbDelta($data_sources_sql);
        
        // Update database version
        update_option('ai_chat_db_version', '1.1.0');
    }
    
    /**
     * Start a new conversation
     */
    public static function start_conversation($user_ip, $user_agent = '') {
        global $wpdb;
        
        $conversation_id = self::generate_conversation_id();
        $table = $wpdb->prefix . 'ai_chat_conversations';
        
        $result = $wpdb->insert(
            $table,
            array(
                'conversation_id' => $conversation_id,
                'user_ip' => $user_ip,
                'user_agent' => $user_agent,
                'platform' => 'ai-chat',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            return $conversation_id;
        }
        
        return false;
    }
    
    /**
     * Get conversation by ID
     */
    public static function get_conversation($conversation_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ai_chat_conversations';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE conversation_id = %s",
                $conversation_id
            )
        );
    }
    
    /**
     * Update conversation activity
     */
    public static function update_conversation_activity($conversation_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ai_chat_conversations';
        
        return $wpdb->update(
            $table,
            array('updated_at' => current_time('mysql')),
            array('conversation_id' => $conversation_id),
            array('%s'),
            array('%s')
        );
    }
    
    /**
     * Save message
     */
    public static function save_message($conversation_id, $sender, $message, $message_type = 'text') {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_chat_messages';

        $result = $wpdb->insert(
            $table,
            array(
                'conversation_id' => $conversation_id,
                'sender' => $sender,
                'message' => $message,
                'message_type' => $message_type,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            // Update conversation activity
            self::update_conversation_activity($conversation_id);
        }
        
        return $result;
    }
    
    /**
     * Get conversation messages
     */
    public static function get_conversation_messages($conversation_id, $limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ai_chat_messages';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE conversation_id = %s ORDER BY created_at ASC LIMIT %d",
                $conversation_id,
                $limit
            )
        );
    }
    
    /**
     * Update analytics
     */
    public static function update_analytics($platform, $conversation_started = false, $message_sent = false) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ai_chat_analytics';
        $date = current_time('Y-m-d');
        
        // Check if record exists for today
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE date = %s AND platform = %s",
                $date,
                $platform
            )
        );
        
        if ($existing) {
            // Update existing record
            $updates = array();
            $formats = array();
            
            if ($conversation_started) {
                $updates['total_conversations'] = $existing->total_conversations + 1;
                $formats[] = '%d';
            }
            
            if ($message_sent) {
                $updates['total_messages'] = $existing->total_messages + 1;
                $formats[] = '%d';
            }
            
            if (!empty($updates)) {
                $wpdb->update(
                    $table,
                    $updates,
                    array('date' => $date, 'platform' => $platform),
                    $formats,
                    array('%s', '%s')
                );
            }
        } else {
            // Create new record
            $wpdb->insert(
                $table,
                array(
                    'date' => $date,
                    'platform' => $platform,
                    'total_conversations' => $conversation_started ? 1 : 0,
                    'total_messages' => $message_sent ? 1 : 0,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%d', '%d', '%s')
            );
        }
    }
    
    /**
     * Get analytics data
     */
    public static function get_analytics($platform = '', $days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ai_chat_analytics';
        $date_from = date('Y-m-d', strtotime("-$days days"));
        
        $sql = "SELECT * FROM $table WHERE date >= %s";
        $params = array($date_from);
        
        if ($platform) {
            $sql .= " AND platform = %s";
            $params[] = $platform;
        }
        
        $sql .= " ORDER BY date DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Clean old conversations
     */
    public static function cleanup_old_conversations($days = 30) {
        global $wpdb;
        
        $conversations_table = $wpdb->prefix . 'ai_chat_conversations';
        $messages_table = $wpdb->prefix . 'ai_chat_messages';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Get old conversation IDs
        $old_conversations = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT conversation_id FROM $conversations_table WHERE updated_at < %s",
                $cutoff_date
            )
        );
        
        if (!empty($old_conversations)) {
            $placeholders = implode(',', array_fill(0, count($old_conversations), '%s'));
            
            // Delete old messages
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $messages_table WHERE conversation_id IN ($placeholders)",
                    $old_conversations
                )
            );
            
            // Delete old conversations
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $conversations_table WHERE conversation_id IN ($placeholders)",
                    $old_conversations
                )
            );
        }
        
        return count($old_conversations);
    }
    
    /**
     * Generate unique conversation ID
     */
    private static function generate_conversation_id() {
        return 'chat_' . wp_generate_uuid4();
    }
    
    /**
     * Get table name with prefix
     */
    public function get_table_name($table_type) {
        global $wpdb;
        
        $table_names = array(
            'conversations' => $wpdb->prefix . 'ai_chat_conversations',
            'messages' => $wpdb->prefix . 'ai_chat_messages',
            'analytics' => $wpdb->prefix . 'ai_chat_analytics',
            'notifications' => $wpdb->prefix . 'ai_chat_notifications',
            'test_results' => $wpdb->prefix . 'ai_chat_test_results'
        );
        
        return isset($table_names[$table_type]) ? $table_names[$table_type] : false;
    }
    
    /**
     * Get conversations with pagination
     */    public function get_conversations($limit = 20, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_chat_conversations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY updated_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A);
    }
    
    /**
     * Get total conversation count
     */
    public function get_conversation_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_chat_conversations';
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
    
    /**
     * Get total message count
     */
    public function get_message_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_chat_messages';
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
    
    /**
     * Get active conversations count
     */
    public function get_active_conversations_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_chat_conversations';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'active' AND updated_at > %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
    }
      /**
     * Delete conversation and its messages
     */
    public function delete_conversation($conversation_id) {
        global $wpdb;
        
        $conversations_table = $wpdb->prefix . 'ai_chat_conversations';
        $messages_table = $wpdb->prefix . 'ai_chat_messages';
        
        // Delete messages first
        $wpdb->delete($messages_table, array('conversation_id' => $conversation_id));
        
        // Delete conversation
        return $wpdb->delete($conversations_table, array('id' => $conversation_id));
    }
      /**
     * Get analytics data for date range
     */
    public function get_analytics_by_date_range($date_from, $date_to) {
        global $wpdb;
        
        $conversations_table = $wpdb->prefix . 'ai_chat_conversations';
        $messages_table = $wpdb->prefix . 'ai_chat_messages';
        
        // Total conversations in date range
        $total_conversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $conversations_table WHERE created_at BETWEEN %s AND %s",
            $date_from . ' 00:00:00', $date_to . ' 23:59:59'
        ));
        
        // Total messages in date range
        $total_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $messages_table m 
             JOIN $conversations_table c ON m.conversation_id = c.conversation_id
             WHERE c.created_at BETWEEN %s AND %s",
            $date_from . ' 00:00:00', $date_to . ' 23:59:59'
        ));
        
        // Average messages per conversation
        $avg_messages_per_conversation = $total_conversations > 0 ? $total_messages / $total_conversations : 0;
        
        // Unique users (based on session_id)
        $unique_users = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_ip) FROM $conversations_table WHERE created_at BETWEEN %s AND %s",
            $date_from . ' 00:00:00', $date_to . ' 23:59:59'
        ));
        
        // Return users (users with more than one conversation)
        $return_users = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT user_ip FROM $conversations_table
                WHERE created_at BETWEEN %s AND %s
                GROUP BY user_ip HAVING COUNT(*) > 1
            ) as return_users",
            $date_from . ' 00:00:00', $date_to . ' 23:59:59'
        ));
        
        $return_user_rate = $unique_users > 0 ? ($return_users / $unique_users) * 100 : 0;
        
        // Average conversation duration
        $avg_duration = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) FROM $conversations_table
             WHERE created_at BETWEEN %s AND %s AND status = 'completed'",
            $date_from . ' 00:00:00', $date_to . ' 23:59:59'
        ));
        
        return array(
            'total_conversations' => intval($total_conversations),
            'total_messages' => intval($total_messages),
            'avg_messages_per_conversation' => round($avg_messages_per_conversation, 1),
            'unique_users' => intval($unique_users),
            'return_user_rate' => round($return_user_rate, 1),
            'avg_conversation_duration' => intval($avg_duration)
        );
    }
    
    /**
     * Get platform statistics
     */
    public function get_platform_statistics($date_from, $date_to) {
        global $wpdb;
        
        $conversations_table = $wpdb->prefix . 'ai_chat_conversations';
        $messages_table = $wpdb->prefix . 'ai_chat_messages';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c.platform,
                COUNT(DISTINCT c.id) as conversations,
                COUNT(m.id) as messages,
                AVG(TIMESTAMPDIFF(SECOND, c.created_at, c.updated_at)) as avg_duration,
                (COUNT(CASE WHEN c.status = 'completed' THEN 1 END) / COUNT(c.id)) * 100 as success_rate
             FROM $conversations_table c
             LEFT JOIN $messages_table m ON c.conversation_id = m.conversation_id
             WHERE c.created_at BETWEEN %s AND %s
             GROUP BY c.platform
             ORDER BY conversations DESC",
            $date_from . ' 00:00:00', $date_to . ' 23:59:59'
        ), ARRAY_A);
        
        $platform_stats = array();
        foreach ($results as $row) {
            $platform_stats[$row['platform']] = array(
                'conversations' => intval($row['conversations']),
                'messages' => intval($row['messages']),
                'avg_duration' => intval($row['avg_duration']),
                'success_rate' => round(floatval($row['success_rate']), 1)
            );
        }
        
        return $platform_stats;
    }
    
    /**
     * Get daily statistics
     */
    public function get_daily_statistics($date_from, $date_to) {
        global $wpdb;
        
        $conversations_table = $wpdb->prefix . 'ai_chat_conversations';
        $messages_table = $wpdb->prefix . 'ai_chat_messages';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(c.created_at) as date,
                COUNT(DISTINCT c.id) as conversations,
                COUNT(m.id) as messages
             FROM $conversations_table c
             LEFT JOIN $messages_table m ON c.conversation_id = m.conversation_id
             WHERE c.created_at BETWEEN %s AND %s
             GROUP BY DATE(c.created_at)
             ORDER BY date ASC",
            $date_from . ' 00:00:00', $date_to . ' 23:59:59'
        ), ARRAY_A);
        
        $daily_stats = array();
        foreach ($results as $row) {
            $daily_stats[] = array(
                'date' => $row['date'],
                'conversations' => intval($row['conversations']),
                'messages' => intval($row['messages'])
            );
        }        
        return $daily_stats;
    }
      /**
     * Get table names
     */
    public function get_conversations_table() {
        global $wpdb;
        return $wpdb->prefix . 'ai_chat_conversations';
    }
    
    public function get_messages_table() {
        global $wpdb;
        return $wpdb->prefix . 'ai_chat_messages';
    }
    
    /**
     * Clear old conversations
     */
    public function clear_old_conversations($days = 30) {
        global $wpdb;
        
        $conversations_table = $this->get_conversations_table();
        $messages_table = $this->get_messages_table();
        
        // Get conversations older than specified days
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $old_conversations = $wpdb->get_col($wpdb->prepare(
            "SELECT conversation_id FROM {$conversations_table} WHERE updated_at < %s",
            $cutoff_date
        ));
        
        if (empty($old_conversations)) {
            return 0;
        }
        
        $conversation_ids = "'" . implode("', '", array_map('esc_sql', $old_conversations)) . "'";
        
        // Delete messages first (due to foreign key relationship)
        $wpdb->query("DELETE FROM {$messages_table} WHERE conversation_id IN ({$conversation_ids})");
        
        // Delete conversations
        $deleted = $wpdb->query("DELETE FROM {$conversations_table} WHERE conversation_id IN ({$conversation_ids})");
        
        return $deleted;
    }
    
    /**
     * Clear all conversations
     */
    public function clear_all_conversations() {
        global $wpdb;
        
        $conversations_table = $this->get_conversations_table();
        $messages_table = $this->get_messages_table();
        
        // Get total count before deletion
        $total_conversations = $wpdb->get_var("SELECT COUNT(*) FROM {$conversations_table}");
        
        // Delete all messages first (due to foreign key relationship)
        $wpdb->query("DELETE FROM {$messages_table}");
        
        // Delete all conversations
        $wpdb->query("DELETE FROM {$conversations_table}");
        
        // Reset auto increment
        $wpdb->query("ALTER TABLE {$conversations_table} AUTO_INCREMENT = 1");
        $wpdb->query("ALTER TABLE {$messages_table} AUTO_INCREMENT = 1");
        
        return $total_conversations;
    }
    
    /**
     * Get message count by conversation
     */
    public function get_message_count_by_conversation($conversation_id) {
        global $wpdb;
        
        $messages_table = $this->get_messages_table();
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$messages_table} WHERE conversation_id = %s",
            $conversation_id
        ));
    }
      /**
     * Get conversation messages with details for display
     */
    public function get_conversation_details($conversation_id) {
        global $wpdb;

        $messages_table = $this->get_messages_table();
        $conversations_table = $this->get_conversations_table();

        // Check if conversation_id is numeric (backend) or string (frontend)
        if (is_numeric($conversation_id)) {
            // Backend: search by ID
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$conversations_table} WHERE id = %d",
                $conversation_id
            ));
        } else {
            // Frontend: search by conversation_id string
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$conversations_table} WHERE conversation_id = %s",
                $conversation_id
            ));
        }

        if (!$conversation) {
            return null;
        }

        // Get messages using the conversation_id string
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$messages_table} WHERE conversation_id = %s ORDER BY created_at ASC",
            $conversation->conversation_id
        ));

        return array(
            'conversation' => $conversation,
            'messages' => $messages
        );
    }
    
    /**
     * Schedule cleanup task
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('ai_chat_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ai_chat_cleanup');
        }
    }
    
    /**
     * Unschedule cleanup task
     */
    public static function unschedule_cleanup() {
        wp_clear_scheduled_hook('ai_chat_cleanup');
    }
    
    /**
     * Cleanup task callback
     */
    public static function cleanup_old_data() {
        $database = new self();
        $days = get_option('ai_chat_cleanup_days', 30);
        $database->clear_old_conversations($days);
    }
    
    /**
     * Force create missing tables
     */
    public static function force_create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Check which tables are missing and create them
        $required_tables = array(
            'ai_chat_conversations',
            'ai_chat_messages',
            'ai_chat_analytics',
            'ai_chat_notifications',
            'ai_chat_test_results',
            'ai_chat_data_sources'
        );
        
        $missing_tables = array();
        
        foreach ($required_tables as $table_name) {
            $full_table_name = $wpdb->prefix . $table_name;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            
            if ($table_exists != $full_table_name) {
                $missing_tables[] = $table_name;
            }
        }
        
        if (!empty($missing_tables)) {
            // Force create all missing tables
            self::create_tables();
            

        }
        
        return $missing_tables;
    }

    /**
     * Get message count for a specific conversation
     */
    public function get_conversation_message_count($conversation_id) {
        global $wpdb;
        $messages_table = $this->get_messages_table();

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$messages_table} WHERE conversation_id = %s",
            $conversation_id
        ));
    }

    /**
     * Get total message count across all conversations
     */
    public function get_total_message_count() {
        global $wpdb;
        $messages_table = $this->get_messages_table();

        return $wpdb->get_var("SELECT COUNT(*) FROM {$messages_table}");
    }

    /**
     * Get active conversation count (conversations with activity in last 24 hours)
     */
    public function get_active_conversation_count() {
        global $wpdb;
        $conversations_table = $this->get_conversations_table();

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$conversations_table} WHERE updated_at > %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
    }

    /**
     * Check if all required tables exist
     */
    public function check_tables() {
        global $wpdb;

        $required_tables = array(
            'ai_chat_conversations',
            'ai_chat_messages',
            'ai_chat_analytics',
            'ai_chat_notifications',
            'ai_chat_test_results',
            'ai_chat_data_sources'
        );

        $missing_tables = array();

        foreach ($required_tables as $table_name) {
            $full_table_name = $wpdb->prefix . $table_name;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");

            if ($table_exists != $full_table_name) {
                $missing_tables[] = $table_name;
            }
        }

        return $missing_tables;
    }

    /**
     * Upgrade database schema to support larger messages
     */
    public static function upgrade_message_table() {
        global $wpdb;

        $messages_table = $wpdb->prefix . 'ai_chat_messages';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$messages_table'") != $messages_table) {
            return false;
        }

        // Check current column type
        $column_info = $wpdb->get_row("SHOW COLUMNS FROM $messages_table LIKE 'message'");

        if ($column_info && strtoupper($column_info->Type) !== 'LONGTEXT') {
            // Upgrade message column to LONGTEXT (supports up to 4GB, well over 2,000,000 characters)
            $result = $wpdb->query("ALTER TABLE $messages_table MODIFY COLUMN message LONGTEXT NOT NULL");

            return $result !== false;
        }

        return true; // Already LONGTEXT
    }


    /**
     * Add data source URL
     */
    public function add_data_source($url, $title = '') {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_chat_data_sources';

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if URL already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE url = %s",
            $url
        ));

        if ($existing) {
            return false; // URL already exists
        }

        // Fetch content preview
        $content_preview = $this->fetch_url_preview($url);
        if (!$title && $content_preview) {
            $title = $content_preview['title'] ?? '';
        }

        $result = $wpdb->insert(
            $table,
            array(
                'url' => $url,
                'title' => $title,
                'content_preview' => $content_preview['content'] ?? '',
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get data sources with pagination
     */
    public function get_data_sources($limit = 20, $offset = 0) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_chat_data_sources';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A);
    }

    /**
     * Get total data sources count
     */
    public function get_data_sources_count() {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_chat_data_sources';

        return $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Delete data source
     */
    public function delete_data_source($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_chat_data_sources';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    /**
     * Clear all data sources
     */
    public function clear_all_data_sources() {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_chat_data_sources';

        $count = $this->get_data_sources_count();
        $wpdb->query("TRUNCATE TABLE $table");

        return $count;
    }

    /**
     * Get all active data sources for AI
     */
    public function get_active_data_sources() {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_chat_data_sources';

        return $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'active' ORDER BY created_at ASC",
            ARRAY_A
        );
    }

    /**
     * Fetch URL preview
     */
    private function fetch_url_preview($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'AI Chat Plugin/1.0'
        ));

        if (is_wp_error($response)) {
            return array('title' => '', 'content' => '');
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return array('title' => '', 'content' => '');
        }

        // Extract title
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            $title = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8');
            $title = trim($title);
        }

        // Extract content preview (first 500 characters of text)
        $content = strip_tags($body);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        $content = mb_substr($content, 0, 500, 'UTF-8');

        return array(
            'title' => $title,
            'content' => $content
        );
    }
}
