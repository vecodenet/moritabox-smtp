<?php

declare(strict_types = 1);

namespace MoritaBox\Tests;

use PHPUnit\Framework\TestCase;

use React\Socket\ConnectionInterface;

use MoritaBox\Smtp\Auth\CramMd5Auth;
use MoritaBox\Smtp\SmtpHandler;
use MoritaBox\Smtp\SmtpServer;

class SmtpHandlerTest extends TestCase {

    public function testHandleMessage() {
        /**
         * @var ConnectionInterface|MockObject
         */
        $connection = $this->getMockBuilder(ConnectionInterface::class)->getMock();
        $server = new SmtpServer(function(string $user) {
            return $user == 'user' ? 'password' : false;
        });
        $handler = new SmtpHandler($server, $connection);

        $this->assertInstanceOf(ConnectionInterface::class, $handler->getConnection());
        $this->assertMatchesRegularExpression('/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i', $handler->getUniqueId());

        # HELO
        $this->assertEquals(500, $handler->handleMessage('HELO'));
        $this->assertEquals(250, $handler->handleMessage('HELO localhost'));

        # EHLO
        $this->assertEquals(500, $handler->handleMessage('EHLO'));
        $this->assertEquals(250, $handler->handleMessage('EHLO localhost'));

        # HELP
        $this->assertEquals(250, $handler->handleMessage('HELP'));

        # NOOP
        $this->assertEquals(250, $handler->handleMessage('NOOP'));

        # AUTH
        $this->assertEquals(501, $handler->handleMessage('AUTH'));
        # Unsupported AUTH
        $this->assertEquals(504, $handler->handleMessage('AUTH DIGEST-MD5'));

        # Invalid without auth
        $this->assertEquals(500, $handler->handleMessage('MAIL'));
        $this->assertEquals(500, $handler->handleMessage('RCPT'));
        $this->assertEquals(500, $handler->handleMessage('DATA'));

        # PLAIN AUTH
        $token = base64_encode("\000user\000password");
        $handler = new SmtpHandler($server, $connection);
        $this->assertEquals(334, $handler->handleMessage('AUTH PLAIN'));
        # Direct, invalid token
        $this->assertEquals(535, $handler->handleMessage('AUTH PLAIN NTQ4M2JmMjZkZWM0ODZlYzAxNzVlMmEzY2E4MTZhMGE='));
        # Direct, valid token
        $this->assertEquals(235, $handler->handleMessage("AUTH PLAIN {$token}"));
        # Multistep, invalid token
        $handler = new SmtpHandler($server, $connection);
        $this->assertEquals(334, $handler->handleMessage("AUTH PLAIN"));
        $this->assertEquals(500, $handler->handleMessage(""));
        $handler = new SmtpHandler($server, $connection);
        $this->assertEquals(334, $handler->handleMessage("AUTH PLAIN"));
        $this->assertEquals(535, $handler->handleMessage("NTQ4M2JmMjZkZWM0ODZlYzAxNzVlMmEzY2E4MTZhMGE="));
        # Multistep, valid token
        $handler = new SmtpHandler($server, $connection);
        $this->assertEquals(334, $handler->handleMessage("AUTH PLAIN"));
        $this->assertEquals(235, $handler->handleMessage("{$token}"));

        # LOGIN AUTH
        $handler = new SmtpHandler($server, $connection);
        $this->assertEquals(334, $handler->handleMessage('AUTH LOGIN'));
        $this->assertEquals(500, $handler->handleMessage(""));
        $handler = new SmtpHandler($server, $connection);
        $this->assertEquals(334, $handler->handleMessage('AUTH LOGIN'));
        $this->assertEquals(334, $handler->handleMessage(base64_encode('user')));
        $this->assertEquals(235, $handler->handleMessage(base64_encode('password')));

        # CRAM-MD5 AUTH
        $handler = new SmtpHandler($server, $connection);
        $this->assertEquals(334, $handler->handleMessage('AUTH CRAM-MD5'));
        $this->assertEquals(500, $handler->handleMessage(""));
        $handler = new SmtpHandler($server, $connection);
        $this->assertEquals(334, $handler->handleMessage('AUTH CRAM-MD5'));
        $auth = $handler->getAuth();
        $this->assertInstanceOf(CramMd5Auth::class, $auth);
        if ($auth instanceof CramMd5Auth) {
            $challenge = $auth->getChallenge();
            $data = base64_decode($challenge);
            $hash = hash_hmac('md5', $data, 'password');
            $this->assertEquals(235, $handler->handleMessage(base64_encode("user {$hash}")));
        } else {
            $this->fail('Unexpected auth class');
        }

        # MAIL
        $this->assertEquals(500, $handler->handleMessage('MAIL'));
        $this->assertEquals(250, $handler->handleMessage('MAIL <test@example.org>'));

        # RCPT
        $this->assertEquals(500, $handler->handleMessage('RCPT'));
        $this->assertEquals(250, $handler->handleMessage('RCPT <foo@example.org>'));
        $this->assertEquals(250, $handler->handleMessage('RCPT <bar@example.org>'));

        # DATA
        $this->assertEquals(354, $handler->handleMessage('DATA'));
        $this->assertEquals(250, $handler->handleMessage('This is a data line'));
        $this->assertEquals(250, $handler->handleMessage('.'));

        # QUIT
        $this->assertEquals(221, $handler->handleMessage('QUIT'));

        # Check message
        $this->assertEquals('test@example.org', $handler->getFrom());
        $this->assertIsArray($handler->getRecipients());
        $this->assertCount(2, $handler->getRecipients());
        $this->assertEquals('This is a data line', $handler->getContents());
    }
}
