<?php
session_start();
require_once 'websocket_config.php';

// Redirect if not logged in or not a tasker
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_tasker']) || $_SESSION['is_tasker'] != 1) {
    header("Location: signIn.php");
    exit;
}

// Database connection
$db_connected = false;
$db_error_message = "";
try {
    $db = new mysqli("localhost", "root", "", "taskbuddy");
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    $db_connected = true;
} catch (Exception $e) {
    $db_error_message = $e->getMessage();
    error_log("Database Error: " . $db_error_message);
}

// Function to safely escape output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Get current user info
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Fetch all conversations for this tasker
$conversations = [];
$active_conversation = null;
$active_client = null;

if ($db_connected) {
    // Get all conversations
    $query = "SELECT c.conversation_id, c.updated_at, 
                u.user_id, u.first_name, u.last_name, u.profile_image,
                (SELECT COUNT(*) FROM chat_messages 
                WHERE conversation_id = c.conversation_id 
                AND sender_id != ? AND is_read = 0) AS unread_count
              FROM conversations c
              JOIN users u ON (c.user1_id = u.user_id OR c.user2_id = u.user_id)
              WHERE (c.user1_id = ? OR c.user2_id = ?)
              AND u.user_id != ?
              ORDER BY c.updated_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bind_param('iiii', $current_user_id, $current_user_id, $current_user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $conversations = $result->fetch_all(MYSQLI_ASSOC);
    }

    // If conversation_id is specified in URL, get that conversation's details
    if (isset($_GET['conversation_id']) && is_numeric($_GET['conversation_id'])) {
        $conversation_id = (int)$_GET['conversation_id'];

        // Check if conversation belongs to current user
        $check_query = "SELECT 1 FROM conversations 
                       WHERE conversation_id = ? 
                       AND (user1_id = ? OR user2_id = ?)";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param('iii', $conversation_id, $current_user_id, $current_user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            // Set as active conversation
            $active_conversation = $conversation_id;

            // Get client details for this conversation
            $client_query = "SELECT u.user_id, u.first_name, u.last_name, u.profile_image
                            FROM conversations c
                            JOIN users u ON (c.user1_id = u.user_id OR c.user2_id = u.user_id)
                            WHERE c.conversation_id = ? AND u.user_id != ?";
            $client_stmt = $db->prepare($client_query);
            $client_stmt->bind_param('ii', $conversation_id, $current_user_id);
            $client_stmt->execute();
            $client_result = $client_stmt->get_result();

            if ($client_result && $client_result->num_rows > 0) {
                $active_client = $client_result->fetch_assoc();
            }

            // Mark messages as read
            $update_query = "UPDATE chat_messages 
                           SET is_read = 1 
                           WHERE conversation_id = ? AND sender_id != ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bind_param('ii', $conversation_id, $current_user_id);
            $update_stmt->execute();
        }
    }
}

// Get total unread messages count for display in navigation
$total_unread = 0;
foreach ($conversations as $conv) {
    $total_unread += $conv['unread_count'];
}

// Home link
$homeLink = 'TaskerTemplate.php?id=' . intval($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Inbox</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="TaskerTemplate.css" rel="stylesheet">
    <style>
        .conversation-list {
            max-height: 70vh;
            overflow-y: auto;
            border-right: 1px solid #dee2e6;
        }

        .conversation-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .conversation-item:hover {
            background-color: #f8f9fa;
        }

        .conversation-item.active {
            background-color: #e9ecef;
        }

        .profile-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
        }

        .unread-badge {
            background-color: #2D7C7C;
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 10px;
        }

        .timestamp {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: 70vh;
        }

        .chat-messages {
            flex-grow: 1;
            overflow-y: auto;
            padding: 15px;
            background-color: #f8f9fa;
        }

        .chat-input {
            border-top: 1px solid #dee2e6;
            padding: 15px;
            background-color: white;
        }

        .message {
            margin-bottom: 10px;
            max-width: 80%;
        }

        .message-outgoing {
            margin-left: auto;
            background-color: #2D7C7C;
            color: white;
            border-radius: 15px 15px 0 15px;
            padding: 8px 12px;
            margin-right: 5px;
        }

        .message-incoming {
            background-color: #e9ecef;
            color: #212529;
            border-radius: 15px 15px 15px 0;
            padding: 8px 12px;
            margin-left: 5px;
        }

        .message-time {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.7);
            text-align: right;
        }

        .message-incoming .message-time {
            color: #6c757d;
        }

        .no-conversation {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            color: #6c757d;
        }

        .no-messages {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            color: #6c757d;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<section class="navigation-bar">
    <div class="container">
        <header class="d-flex flex-wrap justify-content-center py-3 mb-0">
            <a href="<?php echo h($homeLink); ?>"
               class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
                <span class="fs-3">Task<span class="buddy">Buddy</span></span>
            </a>
            <ul class="nav nav-pills">
                <li class="nav-item nav-notification">
                    <a href="tasker_requests.php" class="nav-link">Requests</a>
                </li>
                <li class="nav-item"><a href="TaskerTemplate.php?id=<?php echo h($_SESSION['user_id']); ?>" class="nav-link">My Profile</a></li>

                <li class="nav-item">
                    <a href="logout.php" class="nav-link">Sign Out</a>
                </li>
            </ul>
        </header>
    </div>
    <div class="border-container">
        <div class="border-line"></div>
    </div>
</section>

<!-- Main Content -->
<div class="container mt-4">
    <h2 class="mb-4">Message Inbox</h2>

    <?php if (!$db_connected): ?>
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading">Unable to connect to database</h4>
            <p>We're currently experiencing technical difficulties. Please try again later.</p>
            <?php if (!empty($db_error_message)): ?>
                <hr>
                <p class="mb-0 small text-muted"><?php echo h($db_error_message); ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Conversation List -->
            <div class="col-md-4">
                <div class="conversation-list">
                    <?php if (count($conversations) > 0): ?>
                        <?php foreach ($conversations as $conversation): ?>
                            <div class="conversation-item d-flex align-items-center <?php echo ($active_conversation == $conversation['conversation_id']) ? 'active' : ''; ?>"
                                 onclick="window.location.href='tasker_inbox.php?conversation_id=<?php echo $conversation['conversation_id']; ?>'">
                                <img src="<?php echo h($conversation['profile_image']); ?>" alt="Profile" class="profile-img me-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><?php echo h($conversation['first_name'] . ' ' . $conversation['last_name']); ?></h5>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timestamp">
                                        <?php
                                        $date = new DateTime($conversation['updated_at']);
                                        echo $date->format('M j, g:i a');
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p>No conversations yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Window -->
            <div class="col-md-8">
                <div class="chat-container">
                    <?php if ($active_conversation && $active_client): ?>
                        <!-- Chat Header -->
                        <div class="d-flex align-items-center p-3 border-bottom">
                            <img src="<?php echo h($active_client['profile_image']); ?>" alt="Profile" class="profile-img me-3">
                            <h5 class="mb-0"><?php echo h($active_client['first_name'] . ' ' . $active_client['last_name']); ?></h5>
                        </div>

                        <!-- Messages Area -->
                        <div id="chat-messages" class="chat-messages">
                            <?php
                            // Load messages for active conversation
                            $message_query = "SELECT message_id, sender_id, message, created_at 
                                            FROM chat_messages 
                                            WHERE conversation_id = ? 
                                            ORDER BY created_at ASC";
                            $message_stmt = $db->prepare($message_query);
                            $message_stmt->bind_param('i', $active_conversation);
                            $message_stmt->execute();
                            $message_result = $message_stmt->get_result();

                            $has_messages = false;
                            if ($message_result && $message_result->num_rows > 0) {
                                $has_messages = true;
                                while ($message = $message_result->fetch_assoc()):
                                    $is_outgoing = ($message['sender_id'] == $current_user_id);
                                    $message_class = $is_outgoing ? 'message-outgoing' : 'message-incoming';
                                    $timestamp = new DateTime($message['created_at']);
                                    $time_str = $timestamp->format('g:i a');
                                    ?>
                                    <div class="message <?php echo $message_class; ?>">
                                        <div class="message-text"><?php echo h($message['message']); ?></div>
                                        <div class="message-time"><?php echo $time_str; ?></div>
                                    </div>
                                <?php
                                endwhile;
                            }

                            if (!$has_messages):
                                ?>
                                <div class="no-messages">
                                    <i class="fas fa-comments fa-3x mb-3"></i>
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Chat Input -->
                        <div class="chat-input">
                            <form id="message-form" class="d-flex">
                                <input type="hidden" id="conversation-id" value="<?php echo $active_conversation; ?>">
                                <input type="hidden" id="recipient-id" value="<?php echo $active_client['user_id']; ?>">
                                <input type="text" id="message-input" class="form-control me-2" placeholder="Type your message...">
                                <button type="submit" class="btn btn-primary">Send</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="no-conversation">
                            <i class="fas fa-comments fa-4x mb-3"></i>
                            <h4>Select a conversation</h4>
                            <p>Choose a conversation from the list to view messages</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get WebSocket URL from PHP configuration
        const WEBSOCKET_URL = '<?php echo getWebSocketUrl(); ?>';

        // Elements
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const chatMessages = document.getElementById('chat-messages');
        const conversationId = document.getElementById('conversation-id')?.value;
        const recipientId = document.getElementById('recipient-id')?.value;

        // WebSocket connection
        let socket = null;

        if (conversationId && recipientId) {
            // Initialize socket connection
            function connectWebSocket() {
                if (socket && socket.readyState !== WebSocket.CLOSED) return;

                console.log('Attempting to connect to WebSocket:', WEBSOCKET_URL);

                try {
                    socket = new WebSocket(WEBSOCKET_URL);

                    socket.onopen = function() {
                        console.log('WebSocket connected successfully');

                        // Authenticate after connection
                        socket.send(JSON.stringify({
                            type: 'auth',
                            user_id: <?php echo $current_user_id; ?>,
                            conversation_id: conversationId
                        }));
                    };

                    socket.onmessage = function(event) {
                        const data = JSON.parse(event.data);

                        switch (data.type) {
                            case 'auth_success':
                                console.log('Authentication successful');
                                break;

                            case 'message_history':
                                // We already loaded the messages from PHP
                                break;

                            case 'message':
                                // New message received
                                appendMessage(data);
                                break;
                        }
                    };

                    socket.onclose = function(event) {
                        console.log('WebSocket disconnected. Code:', event.code, 'Reason:', event.reason);
                        // Try to reconnect after 3 seconds
                        setTimeout(connectWebSocket, 3000);
                    };

                    socket.onerror = function(error) {
                        console.error('WebSocket error:', error);
                        console.error('Failed URL:', WEBSOCKET_URL);
                    };
                } catch (error) {
                    console.error('Error creating WebSocket:', error);
                }
            }

            // Add a message to the chat
            function appendMessage(data) {
                const isOutgoing = data.sender_id == <?php echo $current_user_id; ?>;
                const msgDiv = document.createElement('div');

                msgDiv.className = isOutgoing ? 'message message-outgoing' : 'message message-incoming';

                // Format the timestamp
                const timestamp = new Date(data.timestamp);
                const timeStr = timestamp.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

                msgDiv.innerHTML = `
                    <div class="message-text">${data.message}</div>
                    <div class="message-time">${timeStr}</div>
                `;

                chatMessages.appendChild(msgDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;

                // If this is first message, remove the "no messages" placeholder
                const noMessages = document.querySelector('.no-messages');
                if (noMessages) {
                    noMessages.remove();
                }
            }

            // Send a message
            function sendMessage(text) {
                if (!socket || socket.readyState !== WebSocket.OPEN || !text.trim()) return;

                socket.send(JSON.stringify({
                    type: 'message',
                    recipient_id: recipientId,
                    conversation_id: conversationId,
                    message: text.trim()
                }));

                messageInput.value = '';
            }

            // Handle form submission
            if (messageForm) {
                messageForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    sendMessage(messageInput.value);
                });
            }

            // Connect to WebSocket
            connectWebSocket();

            // Scroll to bottom of chat
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Focus input field
            if (messageInput) {
                messageInput.focus();
            }
        }
    });
</script>

</body>
</html>