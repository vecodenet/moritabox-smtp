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
use MoritaBox\Smtp\SmtpHandler;

$server = new SmtpServer(function(string $user, SmtpHandler $handler) {
    return $user == 'user@example.com' ? 'secret' : false;
});

$server->on('ready', function(int $port) use ($output) {
    echo "Listening on port {$port}";
});

$server->on('mail', function(Mail $mail, SmtpHandler $handler) use ($output) {
    $filename = 'mail-'.microtime(true).'.eml';
    echo "Mail received, saving as '{$filename}'";
    file_put_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . $filename, $mail->getContents());
});

$server->on('error', function(Exception $e) use ($output) {
    $message = sprintf('%s @ %s:%d', $e->getMessage(), $e->getFile(), $e->getLine());
    echo $message;
});

$server->start();
```

The `ready` event is fired when the server is initialized and listening, the `error` event signals ReactPHP's `Socket` errors.

Finally, the `mail` event is fired when a complete mail message has been received, with a `Mail` instance which contains the from address, recipient addresses and all its contents in plain-text form so that you can store it or parse with a MIME message parser (for example by using the `mailparse` extension).

Note that you can change the listening port (defaults to `8025`) from the `SmtpServer` constructor and also you can specify the fully-qualified domain for the `CRAM-MD5` challenge and also set a flag so that the port is bound only locally (that is, allow connections from `127.0.0.1` only).

### Authentication

Each of the supported authentication methods require you to provide an authentication callback that will receive the user name and must return the password for that user or `false` for non-existing users. As you can see, the returned password must be in plain-text, a limitation on the SMTP protocol, so it is advised to encrypt the passwords if you store them (in a database for example).

### Using PHPMailer to send mail

The most-common use case is testing email sent from other PHP projects, commonly implemented with PHPMailer or similar libraries.

In the following example you can see a basic setup; the most important bit is the authentication parameters (`SMTPAuth`, `Username` and `Password`, the later two will be checked via the authentication callback described in the previous section):

```php
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer();

$mail->isSMTP();
$mail->Host = 'localhost';
$mail->Port = 8025;
$mail->SMTPAuth = true;
$mail->Username = 'user@example.com';
$mail->Password = 'secret';
$mail->SMTPDebug = true;
$mail->Timeout = 15;

$mail->setFrom('from@example.com', 'Mailer');
$mail->addAddress('joe@example.net', 'Joe User');
$mail->addAddress('ellen@example.com');
$mail->addReplyTo('info@example.com', 'Information');
$mail->addCC('cc@example.com');
$mail->addBCC('bcc@example.com');

$mail->Subject = 'Here is the subject';
$mail->Body    = 'This is the HTML message body <b>in bold!</b>';
$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

if(!$mail->send()) {
    echo 'Message could not be sent.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
} else {
    echo 'Message has been sent';
}
```

## License

MIT licensed

Copyright &copy; 2023 Vecode. All rights reserved.
