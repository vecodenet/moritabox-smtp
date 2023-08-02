<?php

declare(strict_types = 1);

namespace MoritaBox\Tests;

use PHPUnit\Framework\TestCase;

use MoritaBox\Smtp\Mail;

class MailTest extends TestCase {

    public function testConstructor() {
        $mail = new Mail('test@example.org', ['foo@example.org', 'bar@example.org'], 'Test');
        $this->assertEquals('test@example.org', $mail->getFrom());
        $this->assertIsArray($mail->getRecipients());
        $this->assertCount(2, $mail->getRecipients());
        $this->assertEquals('Test', $mail->getContents());
    }

    public function testSetters() {
        $mail = new Mail();
        $mail->setFrom('test@example.org');
        $mail->setRecipients(['foo@example.org', 'bar@example.org']);
        $mail->setContents('Test');
        $this->assertEquals('test@example.org', $mail->getFrom());
        $this->assertIsArray($mail->getRecipients());
        $this->assertCount(2, $mail->getRecipients());
        $this->assertEquals('Test', $mail->getContents());
    }
}
