<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp;

use Closure;
use Exception;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use React\Socket\SecureServer;
use React\Socket\ServerInterface;
use React\Socket\SocketServer;

class SmtpServer extends EventEmitter {

    /**
     * Socket server
     */
    protected ServerInterface $socket;

    /**
     * Local flag
     */
    protected bool $local;

    /**
     * Server domain
     */
    protected string $domain;

    /**
     * Server port
     */
    protected int $port;

    /**
     * Authentication callback
     */
    protected ?Closure $auth_callback;

    /**
     * Mail validation callback
     */
    protected ?Closure $mail_callback = null;

    /**
     * Recipient validation callback
     */
    protected ?Closure $rcpt_callback = null;

    /**
     * Constructor
     * @param Closure $auth_callback Authentication callback
     * @param string  $domain        Fully-qualified domain name
     * @param int     $port          Server port
     * @param bool    $local         Local flag
     */
    public function __construct(?Closure $auth_callback = null, string $domain = 'localhost', int $port = 8025, bool $local = true) {
        $this->local = $local;
        $this->port = $port;
        $this->domain = $domain;
        $this->auth_callback = $auth_callback;
    }

    /**
     * Check if there's an authentication callback
     */
    public function hasAuthCallback(): bool {
        return $this->auth_callback != null;
    }

    /**
     * Check if there's a mail callback
     */
    public function hasMailCallback(): bool {
        return $this->mail_callback != null;
    }

    /**
     * Check if there's a recipient callback
     */
    public function hasRecipientCallback(): bool {
        return $this->rcpt_callback != null;
    }

    /**
     * Get the authentication callback
     */
    public function getAuthCallback(): ?Closure {
        return $this->auth_callback;
    }

    /**
     * Get the mail callback
     */
    public function getMailCallback(): ?Closure {
        return $this->mail_callback;
    }

    /**
     * Get the recipient callback
     */
    public function getRecipientCallback(): ?Closure {
        return $this->rcpt_callback;
    }

    /**
     * Get server port
     */
    public function getPort(): int {
        return $this->port;
    }

    /**
     * Get server domain
     */
    public function getDomain(): string {
        return $this->domain;
    }

    /**
     * Check if server is local or not
     */
    public function isLocal(): bool {
        return $this->local;
    }

    /**
     * Set the authentication callback
     * @param  Closure|null $auth_callback Authentication callback
     * @return $this
     */
    public function setAuthCallback(?Closure $auth_callback) {
        $this->auth_callback = $auth_callback;
        return $this;
    }

    /**
     * Set the mail callback
     * @param  Closure|null $mail_callback Mail callback
     * @return $this
     */
    public function setMailCallback(?Closure $mail_callback) {
        $this->mail_callback = $mail_callback;
        return $this;
    }

    /**
     * Set the recipient callback
     * @param  Closure|null $rcpt_callback Recipient callback
     * @return $this
     */
    public function setRecipientCallback(?Closure $rcpt_callback) {
        $this->rcpt_callback = $rcpt_callback;
        return $this;
    }

    /**
     * Start server
     */
    public function start(bool $secure = false, array $context = []): void {
        $address = $this->local ? '127.0.0.1' : '0.0.0.0';
        $uri = sprintf('%s:%d', $address, $this->port);
        $this->socket = new SocketServer($uri);
        if ($secure) {
            // @codeCoverageIgnoreStart
            $this->socket = new SecureServer($this->socket, null, $context);
            // @codeCoverageIgnoreEnd
        }
        $this->socket->on('connection', function (ConnectionInterface $connection) {
            // @codeCoverageIgnoreStart
            $handler = new SmtpHandler($this, $connection);
            $handler->on('mail', function($mail) use ($handler) {
                $this->emit('mail', [$mail, $handler]);
            });
            $handler->on('error', function(Exception $e) {
                $this->emit('error', [$e]);
            });
            // @codeCoverageIgnoreEnd
        });
        $this->socket->on('error', function(Exception $e) {
            // @codeCoverageIgnoreStart
            $this->emit('error', [$e]);
            // @codeCoverageIgnoreEnd
        });
        $this->emit('ready', [$this->port]);
    }

    /**
     * Stop server
     */
    public function stop(): void {
        $this->socket->close();
        $this->emit('close', [$this->port]);
    }

    /**
     * Get the password for the given user
     * @param  string       $user    User name
     * @param  ?SmtpHandler $handler Handler instance
     */
    public function getUserPassword(string $user, ?SmtpHandler $handler): mixed {
        return $this->auth_callback ? ($this->auth_callback)($user, $handler) : false;
    }
}
