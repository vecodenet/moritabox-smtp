# moritabox-smtp

## Overview

SMTP server built with ReactPHP, supports PLAIN, LOGIN and CRAM-MD5 authentication.

An easy way to run a local SMTP server for testing mail implementations.

## Requirements

- `php`: `>=8.1`
- `react/socket`: `^1.13`

## Installation

The easiest way to install it is to use Composer:

```shell
  composer require vecode/moritabox-smtp
```

## Basic usage

Create an `SmtpServer` instance by passing an authentication callback; then subscribe to the `ready`, `mail` and/or `error` events:

```php
use MoritaBox\Smtp\Mail;
use MoritaBox\Smtp\SmtpServer;

$server = new SmtpServer(function(string $user) {
    return $user == 'user@example.com' ? 'secret' : false;
});

$server->on('ready', function(int $port) use ($output) {
    echo "Listening on port {$port}";
});

$server->on('mail', function(Mail $mail) use ($output) {
    $filename = 'mail-'.microtime(true).'.eml';
    echo "Mail received, saving as '{$filename}'";
    file_put_contents(BASE_DIR . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'incoming' . DIRECTORY_SEPARATOR . $filename, $mail->getContents());
});

$server->on('error', function(Exception $e) use ($output) {
    $message = sprintf('%s @ %s:%d', $e->getMessage(), $e->getFile(), $e->getLine());
    echo $message;
});

$server->start();
```

The `ready` event is fired when the server is initialized and listening, the `error` event signals ReactPHP's `Socket` errors.

Finally, the `mail` event is fired when a complete mail message has been received, with a `Mail` instance which contains the from address, recipient addresses and all its contents in plain-text form so that you can store it or parse with a MIME message parser (for example by using the `mailparse` extension).

### Authentication

Each of the supported authentication methods require you to provide an authentication callback that will receive the user name and must return the password for that user or `false` for non-existing users. As you can see, the returned password must be in plain-text, a limitation on the SMTP protocol, so it is advised to encrypt the passwords if you store them (in a database for example).

## License

MIT licensed

Copyright &copy; 2023 Vecode. All rights reserved.
