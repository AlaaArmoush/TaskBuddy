<?php

if (!isset($WEBSOCKET_URL)) {
    require_once 'websocket_config.php';
}

// Only show chat for logged-in users who aren't viewing their own profile
if (!isset($_SESSION['user_id']) || $isOwner) {
    return;
}



// Get user info for chat
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$recipient_id = $tasker_data['user_id'];
$recipient_name = $tasker_data['first_name'] . ' ' . $tasker_data['last_name'];
$recipient_image = $tasker_data['profile_image'];

// Check for existing conversation
$conversation_id = null;
if ($db_connected) {
    $query = "SELECT conversation_id FROM conversations 
              WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)";

    $stmt = $db->prepare($query);
    $stmt->bind_param('iiii', $current_user_id, $recipient_id, $recipient_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $conversation_id = $result->fetch_assoc()['conversation_id'];
    }
}
?>

<!-- Chat Modal -->
<div class="modal fade" id="chatModal" tabindex="-1" aria-labelledby="chatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <img src="<?php echo h($recipient_image); ?>" class="rounded-circle me-2" width="40" height="40">
                    <h5 class="modal-title" id="chatModalLabel"><?php echo h($recipient_name); ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="chat-messages" class="chat-messages p-3"></div>
                <div id="typing-indicator" class="typing-indicator p-2 d-none">
                    <?php echo h($recipient_name); ?> is typing...
                </div>
                <div class="chat-input-container p-3 border-top">
                    <form id="chatForm" class="d-flex">
                        <input type="text" id="chatInput" class="form-control me-2" placeholder="Type your message...">
                        <button type="submit" class="btn btn-primary">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .chat-messages {
        height: 300px;
        overflow-y: auto;
        background-color: #f8f9fa;
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
    }

    .message-incoming {
        background-color: #e9ecef;
        color: #212529;
        border-radius: 15px 15px 15px 0;
        padding: 8px 12px;
    }

    .message-time {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.7);
        text-align: right;
    }

    .message-incoming .message-time {
        color: #6c757d;
    }

    .typing-indicator {
        font-size: 0.8rem;
        color: #6c757d;
        font-style: italic;
        background-color: #f8f9fa;
    }
</style>

<script>
    // Simple WebSocket chat implementation
    document.addEventListener('DOMContentLoaded', function() {
        // Get WebSocket URL from PHP configuration
        const WEBSOCKET_URL = '<?php echo getWebSocketUrl(); ?>';

        // Chat elements
        const chatModal = document.getElementById('chatModal');
        const chatMessages = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chatForm');
        const chatInput = document.getElementById('chatInput');
        const typingIndicator = document.getElementById('typing-indicator');

        // Chat data
        const currentUserId = <?php echo $current_user_id; ?>;
        const recipientId = <?php echo $recipient_id; ?>;
        const conversationId = <?php echo $conversation_id ? $conversation_id : 'null'; ?>;
        let socket = null;
        let typingTimer = null;

        // Connect to WebSocket server
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
                        user_id: currentUserId,
                        conversation_id: conversationId
                    }));
                };

                socket.onmessage = function(event) {
                    const data = JSON.parse(event.data);

                    switch (data.type) {
                        case 'auth_success':
                            // Authentication successful
                            console.log('Authentication successful');
                            break;

                        case 'message_history':
                            // Load message history
                            if (data.messages && data.messages.length) {
                                chatMessages.innerHTML = '';
                                data.messages.forEach(msg => {
                                    appendMessage(msg, false);
                                });
                                chatMessages.scrollTop = chatMessages.scrollHeight;
                            }
                            break;

                        case 'message':
                            // New message received
                            appendMessage(data);
                            break;

                        case 'typing':
                            // Typing indicator
                            if (data.sender_id == recipientId) {
                                if (data.is_typing) {
                                    typingIndicator.classList.remove('d-none');
                                } else {
                                    typingIndicator.classList.add('d-none');
                                }
                            }
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

        // Rest of the functions remain the same...
        function appendMessage(data, scroll = true) {
            const isOutgoing = data.sender_id == currentUserId;
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

            if (scroll) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
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

            chatInput.value = '';
        }

        // Handle form submission
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage(chatInput.value);
        });

        // Handle typing indicators
        chatInput.addEventListener('input', function() {
            if (!socket || socket.readyState !== WebSocket.OPEN) return;

            // Clear previous typing timer
            if (typingTimer) clearTimeout(typingTimer);

            // Send typing indicator (true)
            socket.send(JSON.stringify({
                type: 'typing',
                recipient_id: recipientId,
                is_typing: true
            }));

            // Set timer to stop typing indicator
            typingTimer = setTimeout(function() {
                socket.send(JSON.stringify({
                    type: 'typing',
                    recipient_id: recipientId,
                    is_typing: false
                }));
            }, 2000);
        });

        // Connect when chat modal is shown
        chatModal.addEventListener('shown.bs.modal', function() {
            connectWebSocket();
            chatInput.focus();
        });

        // Clean up when chat modal is hidden
        chatModal.addEventListener('hidden.bs.modal', function() {
            if (socket) {
                socket.close();
            }
        });
    });
</script>