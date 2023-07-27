<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp;

use Closure;
use Exception;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class SmtpServer extends EventEmitter {

    /**
     * Socket server
     */
    protected SocketServer $socket;

    /**
     * Local flag
     */
    protected bool $local;

    /**
     * Server port
     */
    protected int $port;

    /**
     * Authentication callback
     */
    protected Closure $auth_callback;

    /**
     * Constructor
     * @param bool    $local         Bind to URI
     * @param Closure $auth_callback Authentication callback
     */
    public function __construct(Closure $auth_callback, int $port = 8025, bool $local = true) {
        $this->port = $port;
        $this->local = $local;
        $this->auth_callback = $auth_callback;
    }

    /**
     * Get the authentication callback
     */
    public function getAuthCallback(): Closure {
        return $this->auth_callback;
    }

    /**
     * Get server port
     */
    public function getPort(): int {
        return $this->port;
    }

    /**
     * Check if server is local or not
     */
    public function isLocal(): bool {
        return $this->local;
    }

    /**
     * Set the authentication callback
     * @param Closure $auth_callback Authentication callback
     */
    public function setAuthCallback(Closure $auth_callback) {
        $this->auth_callback = $auth_callback;
        return $this;
    }

    /**
     * Start server
     */
    public function start(): void {
        $address = $this->local ? '127.0.0.1' : '0.0.0.0';
        $uri = sprintf('%s:%d', $address, $this->port);
        $this->socket = new SocketServer($uri);
        $this->socket->on('connection', function (ConnectionInterface $connection) {
            $handler = new SmtpHandler($this, $connection);
            $handler->on('mail', function($mail) {
                $this->emit('mail', [$mail]);
            });
            $handler->on('error', function(Exception $e) {
                $this->emit('error', [$e]);
            });
        });
        $this->socket->on('error', function(Exception $e) {
            $this->emit('error', [$e]);
        });
        $this->emit('ready', [$this->port]);
    }

    /**
     * Stop server
     */
    public function stop(): void {
        if ($this->socket) {
            $this->socket->close();
            $this->emit('close', [$this->port]);
        }
    }

    /**
     * Get the password for the given user
     * @param  string $user User name
     */
    public function getUserPassword(string $user): mixed {
        return $this->auth_callback ? ($this->auth_callback)($user) : false;
    }
}
