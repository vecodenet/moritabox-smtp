<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp\Utils;

class StringParser {

    /**
     * String buffer
     */
    private string $str = '';

    /**
     * Buffer length
     */
    private int $len = 0;

    /**
     * Max. arguments
     */
    private ?int $args_max;

    /**
     * Argument index
     */
    private int $args_id = -1;

    /**
     * Argument array
     */
    private array $args = [];

    /**
     * Argument count
     */
    private int $args_len = 0;

    /**
     * Constructor
     * @param string $str      The string to parse
     * @param int    $args_max Max. arguments
     */
    public function __construct(string $str, int $args_max = null) {
        $this->str = $str;
        $this->str = trim($this->str);
        $this->len = strlen($this->str);
        $this->args_max = $args_max;
    }

    /**
     * Reset parser
     */
    private function reset(): void {
        $this->args_id = -1;
        $this->args = [];
        $this->args_len = 0;
    }

    /**
     * Fix previous argument
     */
    private function fixPrev(): void {
        if ($this->args_id >= 0) {
            if ($this->args[$this->args_id]
                && $this->args[$this->args_id][0] == '"'
                && substr($this->args[$this->args_id], -1) == '"'
            ) {
                $tmp = substr(substr($this->args[$this->args_id], 1), 0, -1);
                if (strpos($tmp, '"') === false) {
                    $this->args[$this->args_id] = $tmp;
                    $this->args_len = count($this->args);
                }
            }
        }
    }

    /**
     * Process new character
     * @param  string $char Character
     */
    private function charNew(string $char = ''): void {
        if ($this->args_max === null || $this->args_len < $this->args_max) {
            $this->fixPrev();
            $this->args_id++;
            $this->args[$this->args_id] = $char;
            $this->args_len = count($this->args);
        }
    }

    /**
     * Append character
     * @param  string $char Character
     */
    private function charAppend(string $char): void {
        if ($this->args_id != -1) {
            $this->args[$this->args_id] .= $char;
        }
    }

    /**
     * Parse string
     * @return array
     */
    public function parse(): array {
        $this->reset();
        $str = $this->str;
        $in = false;
        $end_char = '';
        for ($pos = 0; $pos < $this->len; $pos++) {
            $char = $str[$pos];
            $next_char = ($pos < $this->len - 1) ? $str[$pos + 1] : '';
            if ($in) {
                if ($char == $end_char) {
                    if ($pos == $this->len - 1 || $this->args_max === null || $this->args_len < $this->args_max) {
                        if ($char == '"') {
                            $this->charAppend($char);
                        }
                        $in = false;
                    } else {
                        $this->charAppend($char);
                    }
                } else {
                    $this->charAppend($char);
                }
            } else {
                if ($this->args_max === null || $this->args_len < $this->args_max) {
                    if ($char == '"') {
                        $this->charNew($char);
                        $end_char = '"';
                        $in = true;
                    } elseif ($char == ' ') {
                        if ($next_char != ' ' && $next_char != '"') {
                            $this->charNew();
                            $end_char = ' ';
                            $in = true;
                        }
                    } else {
                        $this->charNew($char);
                        $end_char = ' ';
                        $in = true;
                    }
                }
            }
        }
        $this->fixPrev();
        return $this->args;
    }
}
