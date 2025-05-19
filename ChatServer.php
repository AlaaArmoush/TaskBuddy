<?php

require __DIR__ . '/vendor/autoload.php';

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class ChatServer implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections = []; // Maps user_id to connection
    protected $connectionUsers = []; // Maps connection resource ID to user_id

    public function __construct()
    {
        $this->clients = new SplObjectStorage;
        echo "Chat server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        if (!$data || !isset($data['type'])) {
            echo "Invalid message format\n";
            return;
        }

        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;

            case 'message':
                $this->handleChatMessage($from, $data);
                break;

            case 'typing':
                $this->handleTyping($from, $data);
                break;

            default:
                echo "Unknown message type: {$data['type']}\n";
        }
    }

    protected function handleAuth($conn, $data)
    {
        if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
            return;
        }

        $userId = (int)$data['user_id'];
        $this->userConnections[$userId] = $conn;
        $this->connectionUsers[$conn->resourceId] = $userId;

        echo "User {$userId} authenticated\n";

        // Send confirmation back to the user
        $conn->send(json_encode([
            'type' => 'auth_success',
            'user_id' => $userId
        ]));

        // Load previous messages if conversation ID is provided
        if (isset($data['conversation_id'])) {
            $this->loadMessages($conn, $data['conversation_id']);
        }
    }

    protected function handleChatMessage($from, $data)
    {
        if (!isset($this->connectionUsers[$from->resourceId])) {
            return; // Not authenticated
        }

        if (!isset($data['recipient_id']) || !isset($data['message'])) {
            return; // Missing required fields
        }

        $senderId = $this->connectionUsers[$from->resourceId];
        $recipientId = (int)$data['recipient_id'];
        $message = $data['message'];
        $conversationId = $data['conversation_id'] ?? null;

        // Store message in database and get conversation ID
        $result = $this->storeMessage($senderId, $recipientId, $message, $conversationId);

        if (!$result) {
            $from->send(json_encode([
                'type' => 'error',
                'message' => 'Failed to store message'
            ]));
            return;
        }

        $conversationId = $result['conversation_id'];
        $messageId = $result['message_id'];

        // Prepare message data
        $messageData = [
            'type' => 'message',
            'message_id' => $messageId,
            'sender_id' => $senderId,
            'message' => $message,
            'conversation_id' => $conversationId,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Send to recipient if online
        if (isset($this->userConnections[$recipientId])) {
            $this->userConnections[$recipientId]->send(json_encode($messageData));
        }

        // Send confirmation to sender
        $messageData['status'] = 'sent';
        $from->send(json_encode($messageData));

        echo "Message from {$senderId} to {$recipientId}: {$message}\n";
    }

    protected function handleTyping($from, $data)
    {
        if (!isset($this->connectionUsers[$from->resourceId])) {
            return; // Not authenticated
        }

        if (!isset($data['recipient_id']) || !isset($data['is_typing'])) {
            return; // Missing required fields
        }

        $senderId = $this->connectionUsers[$from->resourceId];
        $recipientId = (int)$data['recipient_id'];
        $isTyping = (bool)$data['is_typing'];

        // Forward typing indicator to recipient if online
        if (isset($this->userConnections[$recipientId])) {
            $this->userConnections[$recipientId]->send(json_encode([
                'type' => 'typing',
                'sender_id' => $senderId,
                'is_typing' => $isTyping
            ]));
        }
    }

    protected function loadMessages($conn, $conversationId)
    {
        // Connect to database
        $db = $this->getDbConnection();
        if (!$db) {
            return;
        }

        // Get previous messages
        $stmt = $db->prepare("
            SELECT message_id, sender_id, message, created_at
            FROM chat_messages
            WHERE conversation_id = ?
            ORDER BY created_at ASC
            LIMIT 50
        ");

        $stmt->bind_param('i', $conversationId);
        $stmt->execute();
        $result = $stmt->get_result();

        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'type' => 'message',
                'message_id' => $row['message_id'],
                'sender_id' => $row['sender_id'],
                'message' => $row['message'],
                'conversation_id' => $conversationId,
                'timestamp' => $row['created_at']
            ];
        }

        // Send message history to client
        if (!empty($messages)) {
            $conn->send(json_encode([
                'type' => 'message_history',
                'messages' => $messages
            ]));
        }

        $db->close();
    }

    protected function storeMessage($senderId, $recipientId, $message, $conversationId = null)
    {
        // Connect to database
        $db = $this->getDbConnection();
        if (!$db) {
            return false;
        }

        // Begin transaction
        $db->begin_transaction();

        try {
            // Get or create conversation
            if (!$conversationId) {
                // Check if conversation exists
                $stmt = $db->prepare("
                    SELECT conversation_id 
                    FROM conversations 
                    WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
                ");

                $stmt->bind_param('iiii', $senderId, $recipientId, $recipientId, $senderId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $conversationId = $result->fetch_assoc()['conversation_id'];
                } else {
                    // Create new conversation
                    $stmt = $db->prepare("
                        INSERT INTO conversations (user1_id, user2_id, created_at)
                        VALUES (?, ?, NOW())
                    ");

                    $stmt->bind_param('ii', $senderId, $recipientId);
                    $stmt->execute();
                    $conversationId = $db->insert_id;
                }
            }

            // Store message
            $stmt = $db->prepare("
                INSERT INTO chat_messages (conversation_id, sender_id, message, created_at)
                VALUES (?, ?, ?, NOW())
            ");

            $stmt->bind_param('iis', $conversationId, $senderId, $message);
            $stmt->execute();
            $messageId = $db->insert_id;

            // Update conversation timestamp
            $db->query("
                UPDATE conversations 
                SET updated_at = NOW() 
                WHERE conversation_id = {$conversationId}
            ");

            // Commit transaction
            $db->commit();

            $db->close();

            return [
                'conversation_id' => $conversationId,
                'message_id' => $messageId
            ];

        } catch (Exception $e) {
            // Rollback on error
            $db->rollback();
            $db->close();
            echo "Database error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    protected function getDbConnection()
    {
        try {
            $db = new mysqli("localhost", "root", "", "taskbuddy");
            if ($db->connect_error) {
                echo "Database Connection Error: " . $db->connect_error . "\n";
                return false;
            }
            return $db;
        } catch (Exception $e) {
            echo "Database Connection Error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        // Remove user mapping
        if (isset($this->connectionUsers[$conn->resourceId])) {
            $userId = $this->connectionUsers[$conn->resourceId];
            unset($this->userConnections[$userId]);
            unset($this->connectionUsers[$conn->resourceId]);
            echo "User {$userId} disconnected\n";
        }

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Run the server directly from this file
if (php_sapi_name() == 'cli') {
    echo "Starting chat server on port 8081...\n";

    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new ChatServer()
            )
        ),
        8081
    );

    echo "Server running...\n";
    $server->run();
}