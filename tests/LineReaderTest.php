<?php

declare(strict_types = 1);

namespace MoritaBox\Tests;

use Closure;
use RuntimeException;

use PHPUnit\Framework\TestCase;

use MoritaBox\LineReader;

class LineReaderTest extends TestCase {

    public function testSetCallback() {
        $reader = new LineReader(function() {});
        $this->assertInstanceOf(Closure::class, $reader->getCallback());
        #
        $reader = new LineReader();
        $reader->setCallback(function() {});
        $this->assertInstanceOf(Closure::class, $reader->getCallback());
    }

    public function testSetLimit() {
        $reader = new LineReader(limit: 1500);
        $this->assertEquals(1500, $reader->getLimit());
        #
        $reader = new LineReader();
        $reader->setLimit(1500);
        $this->assertEquals(1500, $reader->getLimit());
    }

    public function testSetDelimiter() {
        $delimiter = "\n";
        $reader = new LineReader(delimiter: $delimiter);
        $this->assertEquals($delimiter, $reader->getDelimiter());
        #
        $reader = new LineReader();
        $reader->setDelimiter($delimiter);
        $this->assertEquals($delimiter, $reader->getDelimiter());
    }

    public function testWrite() {
        $data = 'Lorem ipsum dolor sit amet consectetur adipisicing elit';
        $reader = new LineReader();
        $reader->write($data);
        $this->assertEquals($data, $reader->getBuffer());
    }

    public function testCallback() {
        $feed = [
            "Lorem ipsum dolor sit, amet consectetur adipisicing elit.\r\n",
            "Cum mollitia harum odit!\r\n",
            "Officiis blanditiis vel fuga obcaecati eaque eius magnam.\r\n",
            "Commodi saepe sint repellat reiciendis ea eos autem quod cupiditate.\r\n",
        ];
        $lines = [];
        $reader = new LineReader(function(string $line) use (&$lines) {
            $lines[] = $line;
        });
        foreach ($feed as $line) {
            $reader->write($line);
        }
        $this->assertCount(4, $lines);
    }

    public function testLimit() {
        $feed = [
            "Lorem ipsum dolor sit, amet consectetur adipisicing elit.\r\n",
            "Cum mollitia harum odit! Officiis blanditiis vel fuga obcaecati eaque eius magnam.\r\n",
            "Commodi saepe sint repellat reiciendis ea eos autem quod cupiditate.\r\n",
        ];
        $lines = [];
        $reader = new LineReader(function(string $line) use (&$lines) {
            $lines[] = $line;
        }, 72);
        $this->expectException(RuntimeException::class);
        foreach ($feed as $line) {
            $reader->write($line);
        }
    }
}