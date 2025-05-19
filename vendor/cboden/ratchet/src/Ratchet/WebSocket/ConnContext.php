<?php
namespace Ratchet\WebSocket;
use Ratchet\RFC6455\Messaging\MessageBuffer;

class ConnContext {
    /**
     * @var WsConnection
     */
    public $connection;

    /**
     * @var MessageBuffer;
     */
    public $buffer;

    public function __construct(WsConnection $conn, MessageBuffer $buffer) {
        $this->connection = $conn;
        $this->buffer = $buffer;
    }
}
