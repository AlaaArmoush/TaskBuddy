<?php
namespace Ratchet\Http;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Psr\Http\Message\RequestInterface;
use UnexpectedValueException;

interface HttpServerInterface extends MessageComponentInterface {
    /**
     * @param ConnectionInterface $conn
     * @param RequestInterface $request null is default because PHP won't let me overload; don't pass null!!!
     * @throws UnexpectedValueException if a RequestInterface is not passed
     */
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null);
}
