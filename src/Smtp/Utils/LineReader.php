<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp\Utils;

use Closure;
use RuntimeException;

class LineReader {

    /**
     * Line callback
     */
    protected ?Closure $callback;

    /**
     * Max characters per-line
     */
    protected int $limit;

    /**
     * Line buffer
     */
    protected string $buffer = '';

    /**
     * Line delimiter
     */
    protected string $delimiter;

    /**
     * Constructor
     * @param Closure $callback  Line callback
     * @param int     $limit     Max characters per-line
     * @param string  $delimiter Line delimiter
     */
    public function __construct(Closure $callback = null, int $limit = 1024, string $delimiter = "\r\n") {
        $this->callback = $callback;
        $this->limit = $limit;
        $this->delimiter = $delimiter;
    }

    /**
     * Get line callback
     */
    public function getCallback(): Closure {
        return $this->callback;
    }

    /**
     * Get max characters per-line
     */
    public function getLimit(): int {
        return $this->limit;
    }

    /**
     * Get line delimiter
     */
    public function getDelimiter(): string {
        return $this->delimiter;
    }

    /**
     * Get buffer contents
     */
    public function getBuffer(): string {
        return $this->buffer;
    }

    /**
     * Set line callback
     * @param  Closure $callback Line callback
     * @return $this
     */
    public function setCallback(Closure $callback) {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Set max characters per-line
     * @param  int $limit Max characters per-line
     * @return $this
     */
    public function setLimit(int $limit) {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set line delimiter
     * @param  string  $delimiter Line delimiter
     * @return $this
     */
    public function setDelimiter(string $delimiter) {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Write to the buffer
     * @param  string $data Data to write
     */
    public function write(string $data): void {
        if ($data !== '' && $data !== false) {
            $this->buffer .= $data;
            if (strlen($this->buffer) > $this->limit) {
                $this->buffer = '';
                throw new RuntimeException('Line length limit exceeded');
            }
            while(false !== $pos = strpos($this->buffer, $this->delimiter)) {
                $line = substr($this->buffer, 0, $pos);
                $this->buffer = substr($this->buffer, $pos + strlen($this->delimiter));
                if ($this->callback) {
                    ($this->callback)($line);
                }
            }
        }
    }
}
