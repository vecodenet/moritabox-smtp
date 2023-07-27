<?php

declare(strict_types = 1);

namespace MoritaBox;

use RuntimeException;

use React\Socket\ConnectionInterface;

use MoritaBox\Message;

class SmtpHandler {

    const DELIMITER = "\r\n";

    protected string $banner = 'Welcome to ReactPHP SMTP Server';

    protected mixed $from;

    protected array $recipients = [];

    protected string $contents = '';

    protected ConnectionInterface $connection;

    protected LineReader $reader;

    protected bool $has_data = false;

    public function __construct(ConnectionInterface $connection) {
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
        // $this->reset(self::STATUS_NEW);
        $this->reply(220, $this->banner);
    }

    public function handleMessage(string $message) {
        $parser = new StringParser($message);
        $args = $parser->parse();
        $command = strtolower( array_shift($args) ?? '' );
        switch ($command) {
            case 'helo':
                $domain = $args[0] ?? '';
                $this->reply(250, "Hello {$domain} @ {$this->connection->getRemoteAddress()}");
            break;
            case 'ehlo':
                $domain = $args[0] ?? '';
                $this->reply(250, "Hello {$domain} @ {$this->connection->getRemoteAddress()}");
            break;
            // case 'auth':
            //     $domain = $args[0] ?? '';
            //     $this->reply(250, "Hello {$domain} @ {$this->connection->getRemoteAddress()}");
            // break;
            case 'mail':
                $from = $args[0];
                if (preg_match('/\<(?<email>.*)\>( .*)?/', $from, $matches) == 1) {
                    $this->from  = $matches['email'];
                    $this->reply(250, "MAIL OK");
                } else {
                    $this->reply(500, "Invalid mail argument");
                }
            break;
            case 'rcpt':
                $rcpt = $args[0];
                if (preg_match('/^(?<name>.*?)\s*?\<(?<email>.*)\>\s*$/', $rcpt, $matches) == 1) {
                    $this->recipients[$matches['email']] = $matches['name'];
                    $this->reply(250, "Accepted");
                } else {
                    $this->reply(500, "Invalid RCPT TO argument.");
                }
            break;
            case 'data':
                $this->has_data = true;
                $this->reply(354, "Enter message, end with <CRLF>.<CRLF>");
            break;
            case 'quit':
                $this->reply(221, "Goodbye.", true);
            break;
            default:
                if ($this->has_data) {
                    if ($message == '.') {
                        $this->contents = substr($this->contents, 0, -strlen(static::DELIMITER));
                        return $this->reply(250, "OK");
                    } else {
                        $this->contents .= $message . static::DELIMITER;
                    }
                }
            break;
        }
    }

    protected function reply(int $code, string $message, bool $close = false) {
        if ($close) {
            $this->connection->end("{$code} {$message}" . self::DELIMITER);
        } else {
            $this->connection->write("{$code} {$message}" . self::DELIMITER);
        }
    }
}