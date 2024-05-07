<?php

declare(strict_types = 1);

namespace MoritaBox\Tests;

use Closure;
use Exception;

use PHPUnit\Framework\TestCase;

use MoritaBox\Smtp\SmtpServer;

class SmtpServerTest extends TestCase {

    public function testServer() {
        $server = new SmtpServer(function(string $user) {
            return $user == 'user' ? 'password' : false;
        });
        $this->assertEquals('localhost', $server->getDomain());
        $this->assertEquals(8025, $server->getPort());
        $this->assertTrue($server->isLocal());
        $this->assertInstanceOf(Closure::class, $server->getAuthCallback());
        #
        $server->setAuthCallback(function() {
            return false;
        });
        $server->setMailCallback(function() {
            return false;
        });
        $server->setRecipientCallback(function() {
            return false;
        });
        $this->assertInstanceOf(Closure::class, $server->getAuthCallback());
        $this->assertInstanceOf(Closure::class, $server->getMailCallback());
        $this->assertInstanceOf(Closure::class, $server->getRecipientCallback());
        #
        try {
            $server->start();
            $server->stop();
        } catch (Exception $e) {
            $this->fail();
        }
    }
}
