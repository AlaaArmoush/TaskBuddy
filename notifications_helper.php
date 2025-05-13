<?php
function getUnreadNotificationCount($db, $user_id, $type = 'all') {
    if (!$db || !$user_id) {
        return 0;
    }

    try {
        $query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";

        // You can add type-specific filtering if needed later
        if ($type === 'tasker') {
            $query .= " AND related_to = 'booking'";
        } elseif ($type === 'client') {
            $query .= " AND related_to = 'booking'";
        }

        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['unread_count'];
        }
    } catch (Exception $e) {
        error_log("Error getting notification count: " . $e->getMessage());
    }

    return 0;
}


function markNotificationsAsRead($db, $user_id, $related_to = null, $related_id = null) {
    if (!$db || !$user_id) {
        return false;
    }

    try {
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
        $params = array($user_id);
        $types = "i";

        if ($related_to) {
            $query .= " AND related_to = ?";
            $params[] = $related_to;
            $types .= "s";
        }

        if ($related_id) {
            $query .= " AND related_id = ?";
            $params[] = $related_id;
            $types .= "i";
        }

        $stmt = $db->prepare($query);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
        return false;
    }
}

function notificationBadgeHtml($count) {
    if ($count <= 0) {
        return '';
    }

    return '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">' .
        $count .
        '<span class="visually-hidden">unread notifications</span>' .
        '</span>';
}
?>