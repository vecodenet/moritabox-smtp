<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp;

use RuntimeException;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;

use MoritaBox\Smtp\Mail;
use MoritaBox\Smtp\SmtpServer;
use MoritaBox\Smtp\Auth\AuthInterface;
use MoritaBox\Smtp\Auth\CramMd5Auth;
use MoritaBox\Smtp\Auth\LoginAuth;
use MoritaBox\Smtp\Auth\PlainAuth;
use MoritaBox\Smtp\Utils\LineReader;
use MoritaBox\Smtp\Utils\StringParser;

class SmtpHandler extends EventEmitter {

    /**
     * Line delimiter
     */
    const DELIMITER = "\r\n";

    /**
     * Unique ID
     */
    protected string $uuid;

    /**
     * Welcome banner
     */
    protected string $banner = 'Welcome to MoritaBox SMTP Server';

    /**
     * From address
     */
    protected mixed $from;

    /**
     * Recipient addresses
     */
    protected array $recipients = [];

    /**
     * Message contents
     */
    protected string $contents = '';

    /**
     * The server instance
     */
    protected SmtpServer $server;

    /**
     * ConnectionInterface implementation
     */
    protected ConnectionInterface $connection;

    /**
     * Line reader
     */
    protected LineReader $reader;

    /**
     * AuthInterface implementation
     */
    protected AuthInterface $auth;

    /**
     * EHLO/HELO flag
     */
    protected bool $has_ehlo = false;

    /**
     * AUTH flag
     */
    protected bool $has_auth = false;

    /**
     * MAIL flag
     */
    protected bool $has_mail = false;

    /**
     * RCPT FLAG
     */
    protected bool $has_rcpt = false;

    /**
     * DATA flag
     */
    protected bool $has_data = false;

    /**
     * Whether a valid authentication flow has occurred or not
     */
    protected bool $has_valid_auth = false;

    /**
     * Constructor
     * @param ConnectionInterface $connection ConnectionInterface implementation
     */
    public function __construct(SmtpServer $server, ConnectionInterface $connection) {
        $this->uuid = $this->generateUniqueId();
        $this->server = $server;
        $this->connection = $connection;
        $this->reader = new LineReader(function(string $line) {
            // @codeCoverageIgnoreStart
            $this->handleMessage($line);
            // @codeCoverageIgnoreEnd
        }, 1000, self::DELIMITER);
        $this->connection->on('data', function(string $data) {
            // @codeCoverageIgnoreStart
            try {
                # Get data length
                $length = strlen($data);
                if ( $length <= 500 ) {
                    # Less or equal 500 chars, write directly to the reader
                    $this->reader->write($data);
                } else {
                    # More than 500 chars, chunk it to avoid filling up the buffer
                    $offset = 0;
                    while ( $offset < $length ) {
                        $chunk = substr($data, $offset, 500);
                        $this->reader->write($chunk);
                        $offset += strlen($chunk);
                    }
                }
            } catch (RuntimeException $e) {
                $this->reply(500, $e->getMessage());
            }
            // @codeCoverageIgnoreEnd
        });
        $this->reply(220, $this->banner);
    }

    /**
     * Get unique ID
     */
    public function getUniqueId(): string {
        return $this->uuid;
    }

    /**
     * Get current auth provider
     */
    public function getAuth(): AuthInterface {
        return $this->auth;
    }

    /**
     * Get from address
     */
    public function getFrom(): string {
        return $this->from;
    }

    /**
     * Get recipient addresses
     */
    public function getRecipients(): array {
        return $this->recipients;
    }

    /**
     * Get message contents
     */
    public function getContents(): string {
        return $this->contents;
    }

    /**
     * Get the connection
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface {
        return $this->connection;
    }

    /**
     * Handle client message
     * @param  string $message Raw message
     */
    public function handleMessage(string $message): int {
        $ret = 500;
        $parser = new StringParser($message);
        $args = $parser->parse();
        $command = strtolower( array_shift($args) ?? '' );
        switch ($command) {
            case 'helo':
                $domain = $args[0] ?? '';
                if ($domain) {
                    $this->has_ehlo = true;
                    $ret = $this->reply(250, "Hello {$domain} @ {$this->connection->getRemoteAddress()}");
                } else {
                    $ret = $this->reply(500, "Invalid helo argument");
                }
            break;
            case 'ehlo':
                $domain = $args[0] ?? '';
                if ($domain) {
                    $this->has_ehlo = true;
                    $ret = $this->reply(250, ["Hello {$domain} @ {$this->connection->getRemoteAddress()}", 'AUTH PLAIN LOGIN CRAM-MD5', 'HELP']);
                } else {
                    $ret = $this->reply(500, "Invalid ehlo argument");
                }
            break;
            case 'help':
                $ret = $this->reply(250, "HELO, EHLO, MAIL FROM, RCPT TO, DATA, NOOP, QUIT");
            break;
            case 'noop':
                $ret = $this->reply(250, "OK");
            break;
            // case 'starttls':
            //     if (! empty($args) ) {
            //         $ret = $this->reply(501, "Syntax error in parameters or arguments");
            //     }
            //     $ret = $this->reply(220, "Ready to start TLS");
            // break;
            case 'auth':
                if ( empty($args) ) {
                    $ret = $this->reply(501, "Syntax error in parameters or arguments");
                } else {
                    $this->has_auth = true;
                    $auth_type = strtolower( $args[0] ?? '' );
                    switch ($auth_type) {
                        case 'plain':
                            $this->auth = new PlainAuth();
                            $token = $args[1] ?? '';
                            if (! $token ) {
                                $ret = $this->reply(334);
                            } else {
                                $this->auth->decode($token);
                                if ( $this->auth->validate($this->server, $this) ) {
                                    $this->has_valid_auth = true;
                                    $ret = $this->reply(235, '2.7.0 Authentication successful');
                                } else {
                                    $ret = $this->reply(535, 'Authentication credentials invalid');
                                }
                            }
                        break;
                        case 'login':
                            $this->auth = new LoginAuth();
                            $ret = $this->reply(334, 'VXNlcm5hbWU6');
                        break;
                        case 'cram-md5':
                            $this->auth = new CramMd5Auth( $this->server->getDomain() );
                            $ret = $this->reply(334, $this->auth->getChallenge());
                        break;
                        default:
                            $ret = $this->reply(504, 'Unrecognized authentication type');
                        break;
                    }
                }
            break;
            case 'mail':
                if ($this->has_valid_auth) {
                    $from = $args[0] ?? '';
                    if (preg_match('/\<(?<email>.*)\>( .*)?/', $from, $matches) == 1) {
                        $this->from  = $matches['email'];
                        $this->has_mail = true;
                        $ret = $this->reply(250, "MAIL OK");
                    } else {
                        $ret = $this->reply(500, "Invalid mail argument");
                    }
                } else {
                    $ret = $this->reply(500, "Syntax error, command unrecognized");
                }
            break;
            case 'rcpt':
                if ($this->has_valid_auth) {
                    $rcpt = $args[0] ?? '';
                    if (preg_match('/^(?<name>.*?)\s*?\<(?<email>.*)\>\s*$/', $rcpt, $matches) == 1) {
                        $this->recipients[$matches['email']] = $matches['name'];
                        $this->has_rcpt = true;
                        $ret = $this->reply(250, "Accepted");
                    } else {
                        $ret = $this->reply(500, "Invalid RCPT TO argument.");
                    }
                } else {
                    $ret = $this->reply(500, "Syntax error, command unrecognized");
                }
            break;
            case 'data':
                if ($this->has_valid_auth) {
                    $this->has_data = true;
                    $ret = $this->reply(354, "Enter message, end with <CRLF>.<CRLF>");
                } else {
                    $ret = $this->reply(500, "Syntax error, command unrecognized");
                }
            break;
            case 'quit':
                $ret = $this->reply(221, "Goodbye.", true);
            break;
            default:
                if ($this->has_auth && !$this->has_data) {
                    $auth_type = strtolower( $this->auth->getType() );
                    switch ($auth_type) {
                        case 'plain':
                            if (! $message ) {
                                $ret = $this->reply(500, "Invalid auth argument");
                            } else {
                                if ($this->auth instanceof PlainAuth) {
                                    $this->auth->decode($message);
                                    if ( $this->auth->validate($this->server, $this) ) {
                                        $this->has_valid_auth = true;
                                        $ret = $this->reply(235, '2.7.0 Authentication successful');
                                    } else {
                                        $ret = $this->reply(535, 'Authentication credentials invalid');
                                    }
                                } else {
                                    // @codeCoverageIgnoreStart
                                    throw new RuntimeException( sprintf( "Unexpected Auth class '%s'", get_class($this->auth) ) );
                                    // @codeCoverageIgnoreEnd
                                }
                            }
                        break;
                        case 'login':
                            if (! $message ) {
                                $ret = $this->reply(500, "Invalid auth argument");
                            } else {
                                if ($this->auth instanceof LoginAuth) {
                                    if (! $this->auth->getUser() ) {
                                        $this->auth->setUser($message);
                                        $ret = $this->reply(334, 'UGFzc3dvcmQ6');
                                    } else {
                                        $this->auth->setPassword($message);
                                        if ( $this->auth->validate($this->server, $this) ) {
                                            $this->has_valid_auth = true;
                                            $ret = $this->reply(235, '2.7.0 Authentication successful');
                                        } else {
                                            $ret = $this->reply(535, 'Authentication credentials invalid');
                                        }
                                    }
                                } else {
                                    // @codeCoverageIgnoreStart
                                    throw new RuntimeException( sprintf( "Unexpected Auth class '%s'", get_class($this->auth) ) );
                                    // @codeCoverageIgnoreEnd
                                }
                            }
                        break;
                        case 'cram-md5':
                            if (! $message ) {
                                $ret = $this->reply(500, "Invalid auth argument");
                            } else {
                                if ($this->auth instanceof CramMd5Auth) {
                                    $this->auth->decode($message);
                                    if ( $this->auth->validate($this->server, $this) ) {
                                        $this->has_valid_auth = true;
                                        $ret = $this->reply(235, '2.7.0 Authentication successful');
                                    } else {
                                        $ret = $this->reply(535, 'Authentication credentials invalid');
                                    }
                                } else {
                                    // @codeCoverageIgnoreStart
                                    throw new RuntimeException( sprintf( "Unexpected Auth class '%s'", get_class($this->auth) ) );
                                    // @codeCoverageIgnoreEnd
                                }
                            }
                        break;
                    }
                } elseif ($this->has_data) {
                    if ($message == '.') {
                        $this->contents = substr($this->contents, 0, -strlen(static::DELIMITER));
                        # Emit mail event
                        $mail = new Mail($this->from, $this->recipients, $this->contents);
                        $this->emit('mail', [$mail]);
                        $ret = $this->reply(250, "OK");
                    } else {
                        $this->contents .= $message . static::DELIMITER;
                        $ret = 250;
                    }
                }
            break;
        }
        return $ret;
    }

    /**
     * Send server reply to client
     * @param  int   $code    Status code
     * @param  mixed $message Message
     * @param  bool  $close   Whether to close the connection or not
     */
    protected function reply(int $code, mixed $message = '', bool $close = false): int {
        $out = '';
        if ( is_array($message) ) {
            $last = array_pop($message);
            foreach($message as $line) {
                $out .= "$code-$line\r\n";
            }
            $this->connection->write($out);
            $message = $last;
        }
        $response = "{$code} {$message}" . self::DELIMITER;
        if ($close) {
            $this->connection->end($response);
        } else {
            $this->connection->write($response);
        }
        return $code;
    }

    /**
     * Generate a (reasonably) unique ID
     */
    protected function generateUniqueId(): string {
        $hex = bin2hex($bytes = random_bytes(18));
        $hex[8] = '-';
        $hex[13] = '-';
        $hex[14] = '4';
        $hex[18] = '-';
        $hex[19] = '89ab'[ord($bytes[9]) % 4];
        $hex[23] = '-';
        return $hex;
    }
}
