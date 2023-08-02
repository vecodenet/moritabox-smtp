<?php

declare(strict_types = 1);

namespace MoritaBox\Tests;

use PHPUnit\Framework\TestCase;

use MoritaBox\Smtp\Auth\CramMd5Auth;
use MoritaBox\Smtp\Auth\LoginAuth;
use MoritaBox\Smtp\Auth\PlainAuth;
use MoritaBox\Smtp\SmtpServer;

class AuthTest extends TestCase {

    public function testPlainAuth() {
        $auth = new PlainAuth();
        $this->assertEquals('PLAIN', $auth->getType());
        $token = base64_encode("\000user\000password");
        $auth->decode($token);
        $this->assertEquals('user', $auth->getUser());
        $this->assertEquals('password', $auth->getPassword());
    }

    public function testLoginAuth() {
        $auth = new LoginAuth();
        $server = new SmtpServer(function(string $user) {
            return $user == 'user' ? 'password' : false;
        });
        $this->assertEquals('LOGIN', $auth->getType());
        # Valid credentials
        $auth->setUser(base64_encode('user'));
        $auth->setPassword(base64_encode('password'));
        $this->assertEquals('user', $auth->getUser());
        $this->assertEquals('password', $auth->getPassword());
        $this->assertTrue($auth->validate($server));
        # Invalid credentials
        $auth->setUser(base64_encode('foo'));
        $auth->setPassword(base64_encode('bar'));
        $this->assertFalse($auth->validate($server));
    }

    public function testCramMd5Auth() {
        $auth = new CramMd5Auth('localhost');
        $server = new SmtpServer(function(string $user) {
            return $user == 'user' ? 'password' : false;
        });
        $this->assertEquals('CRAM-MD5', $auth->getType());
        # Valid credentials
        $challenge = $auth->getChallenge();
        $this->assertMatchesRegularExpression('/<[0-9a-f]{64}@localhost>/', $challenge);
        $this->assertNotEmpty($challenge);
        $data = base64_decode($challenge);
        $hash = hash_hmac('md5', $data, 'password');
        $token = base64_encode('user ' . $hash);
        $auth->decode($token);
        $this->assertEquals('user', $auth->getUser());
        $this->assertEquals($hash, $auth->getPassword());
        $this->assertTrue($auth->validate($server));
        # Invalid credentials
        $challenge = $auth->getChallenge();
        $this->assertNotEmpty($challenge);
        $data = base64_decode($challenge);
        $hash = hash_hmac('md5', $data, 'bar');
        $token = base64_encode('foo ' . $hash);
        $auth->decode($token);
        $this->assertFalse($auth->validate($server));
    }
}
