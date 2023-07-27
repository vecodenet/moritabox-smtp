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
        $this->server = $server;
        $this->connection = $connection;
        $this->reader = new LineReader(function(string $line) {
            $this->handleMessage($line);
        }, 1000, self::DELIMITER);
        $this->connection->on('data', function(string $data) {
            try {
                $this->reader->write($data);
            } catch (RuntimeException $e) {
                $this->reply(500, $e->getMessage());
            }
        });
        $this->reply(220, $this->banner);
    }

    /**
     * Handle client message
     * @param  string $message Raw message
     */
    public function handleMessage(string $message): void {
        $parser = new StringParser($message);
        $args = $parser->parse();
        $command = strtolower( array_shift($args) ?? '' );
        switch ($command) {
            case 'helo':
                $domain = $args[0] ?? '';
                if ($domain) {
                    $this->has_ehlo = true;
                    $this->reply(250, "Hello {$domain} @ {$this->connection->getRemoteAddress()}");
                } else {
                    $this->reply(500, "Invalid helo argument");
                }
            break;
            case 'ehlo':
                $domain = $args[0] ?? '';
                if ($domain) {
                    $this->has_ehlo = true;
                    $this->reply(250, ["Hello {$domain} @ {$this->connection->getRemoteAddress()}", 'AUTH PLAIN LOGIN CRAM-MD5', 'HELP']);
                } else {
                    $this->reply(500, "Invalid ehlo argument");
                }
            break;
            case 'help':
                $this->reply(250, "HELO, EHLO, MAIL FROM, RCPT TO, DATA, NOOP, QUIT");
            break;
            case 'noop':
                $this->reply(250, "OK");
            break;
            // case 'starttls':
            //     if (! empty($args) ) {
            //         $this->reply(501, "Syntax error in parameters or arguments");
            //     }
            //     $this->reply(220, "Ready to start TLS");
            // break;
            case 'auth':
                if ( empty($args) ) {
                    $this->reply(501, "Syntax error in parameters or arguments");
                }
                $this->has_auth = true;
                $auth_type = strtolower( $args[0] );
                switch ($auth_type) {
                    case 'plain':
                        $this->auth = new PlainAuth();
                        $token = $args[1] ?? '';
                        if (! $token ) {
                            $this->reply(334);
                        } else {
                            $this->auth->decode($token);
                            if ( $this->auth->validate($this->server) ) {
                                $this->has_valid_auth = true;
                                $this->reply(235, '2.7.0 Authentication successful');
                            } else {
                                $this->reply(535, 'Authentication credentials invalid');
                            }
                        }
                    break;
                    case 'login':
                        $this->auth = new LoginAuth();
                        $this->reply(334, 'VXNlcm5hbWU6');
                    break;
                    case 'cram-md5':
                        $this->auth = new CramMd5Auth();
                        $this->reply(334, $this->auth->getChallenge());
                    break;
                    default:
                        $this->reply(504, 'Unrecognized authentication type');
                    break;
                }
            break;
            case 'mail':
                if ($this->has_valid_auth) {
                    $from = $args[0];
                    if (preg_match('/\<(?<email>.*)\>( .*)?/', $from, $matches) == 1) {
                        $this->from  = $matches['email'];
                        $this->has_mail = true;
                        $this->reply(250, "MAIL OK");
                    } else {
                        $this->reply(500, "Invalid mail argument");
                    }
                } else {
                    $this->reply(500, "Syntax error, command unrecognized");
                }
            break;
            case 'rcpt':
                if ($this->has_valid_auth) {
                    $rcpt = $args[0];
                    if (preg_match('/^(?<name>.*?)\s*?\<(?<email>.*)\>\s*$/', $rcpt, $matches) == 1) {
                        $this->recipients[$matches['email']] = $matches['name'];
                        $this->has_rcpt = true;
                        $this->reply(250, "Accepted");
                    } else {
                        $this->reply(500, "Invalid RCPT TO argument.");
                    }
                } else {
                    $this->reply(500, "Syntax error, command unrecognized");
                }
            break;
            case 'data':
                if ($this->has_valid_auth) {
                    $this->has_data = true;
                    $this->reply(354, "Enter message, end with <CRLF>.<CRLF>");
                } else {
                    $this->reply(500, "Syntax error, command unrecognized");
                }
            break;
            case 'quit':
                $this->reply(221, "Goodbye.", true);
            break;
            default:
                if ($this->has_auth && !$this->has_data) {
                    $auth_type = strtolower( $this->auth->getType() );
                    switch ($auth_type) {
                        case 'plain':
                            if (! $message ) {
                                $this->reply(500, "Invalid auth argument");
                            }
                            if ($this->auth instanceof PlainAuth) {
                                $this->auth->decode($message);
                                if ( $this->auth->validate($this->server) ) {
                                    $this->has_valid_auth = true;
                                    $this->reply(235, '2.7.0 Authentication successful');
                                } else {
                                    $this->reply(535, 'Authentication credentials invalid');
                                }
                            } else {
                                throw new RuntimeException( sprintf( "Unexpected Auth class '%s'", get_class($this->auth) ) );
                            }
                        break;
                        case 'login':
                            if (! $message ) {
                                $this->reply(500, "Invalid auth argument");
                            }
                            if ($this->auth instanceof LoginAuth) {
                                if (! $this->auth->getUser() ) {
                                    $this->auth->setUser($message);
                                    $this->reply(334, 'UGFzc3dvcmQ6');
                                } else {
                                    $this->auth->setPassword($message);
                                    if ( $this->auth->validate($this->server) ) {
                                        $this->has_valid_auth = true;
                                        $this->reply(235, '2.7.0 Authentication successful');
                                    } else {
                                        $this->reply(535, 'Authentication credentials invalid');
                                    }
                                }
                            } else {
                                throw new RuntimeException( sprintf( "Unexpected Auth class '%s'", get_class($this->auth) ) );
                            }
                        break;
                        case 'cram-md5':
                            if ($this->auth instanceof CramMd5Auth) {
                                $this->auth->decode($message);
                                if ( $this->auth->validate($this->server) ) {
                                    $this->has_valid_auth = true;
                                    $this->reply(235, '2.7.0 Authentication successful');
                                } else {
                                    $this->reply(535, 'Authentication credentials invalid');
                                }
                            } else {
                                throw new RuntimeException( sprintf( "Unexpected Auth class '%s'", get_class($this->auth) ) );
                            }
                        break;
                    }
                } elseif ($this->has_data) {
                    if ($message == '.') {
                        $this->contents = substr($this->contents, 0, -strlen(static::DELIMITER));
                        $this->reply(250, "OK");
                        # Emit mail event
                        $mail = new Mail($this->from, $this->recipients, $this->contents);
                        $this->emit('mail', [$mail]);
                    } else {
                        $this->contents .= $message . static::DELIMITER;
                    }
                }
            break;
        }
    }

    /**
     * Send server reply to client
     * @param  int   $code    Status code
     * @param  mixed $message Message
     * @param  bool  $close   Whether to close the connection or not
     */
    protected function reply(int $code, mixed $message = '', bool $close = false): void {
        $out = '';
        if ( is_array($message) ) {
            $last = array_pop($message);
            foreach($message as $line) {
                $out .= "$code-$line\r\n";
            }
            $this->connection->write($out);
            $message = $last;
        }
        if ($close) {
            $this->connection->end("{$code} {$message}" . self::DELIMITER);
        } else {
            $this->connection->write("{$code} {$message}" . self::DELIMITER);
        }
    }
}
