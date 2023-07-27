<?php

declare(strict_types = 1);

namespace MoritaBox;

use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class Server {

    protected SocketServer $socket;

    public function __construct(string $uri) {
        $this->socket = new SocketServer($uri);
        $this->socket->on('connection', function (ConnectionInterface $connection) {
            $handler = new SmtpHandler($connection);
        });
        echo "Listening on {$uri}\r\n";
    }
}
