<?php
/**
 * Log an action to the database
 */
function log_action($action, $details = '') {
    global $db; // Assuming you have a database connection
    
    try {
        $sql = "INSERT INTO logs (user_id, action, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        
        $params = [
            $_SESSION['user_id'] ?? null,
            $action,
            is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        return $db->execute($sql, $params);
    } catch (Exception $e) {
        // Fallback to error log if database logging fails
        error_log("Failed to log action: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to user(s)
 */
function notify($userId, $title, $message) {
    global $db;
    
    try {
        $sql = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
        return $db->execute($sql, [$userId, $title, $message]);
    } catch (Exception $e) {
        error_log("Failed to send notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all admin users
 */
function notify_admins($title, $message) {
    global $db;
    
    try {
        // Get all admin users
        $admins = $db->fetchAll("SELECT id FROM users WHERE role = 'admin'");
        $success = true;
        
        foreach ($admins as $admin) {
            if (!notify($admin['id'], $title, $message)) {
                $success = false;
            }
        }
        
        return $success;
    } catch (Exception $e) {
        error_log("Failed to notify admins: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's unread notifications
 */
function get_unread_notifications($userId, $limit = 5) {
    global $db;
    
    try {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = ? AND is_read = 0 
                ORDER BY created_at DESC 
                LIMIT ?";
        return $db->fetchAll($sql, [$userId, $limit]);
    } catch (Exception $e) {
        error_log("Failed to get notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 */
function mark_notification_read($notificationId, $userId) {
    global $db;
    
    try {
        $sql = "UPDATE notifications SET is_read = 1 
                WHERE id = ? AND user_id = ?";
        return $db->execute($sql, [$notificationId, $userId]);
    } catch (Exception $e) {
        error_log("Failed to mark notification as read: " . $e->getMessage());
        return false;
    }
}
