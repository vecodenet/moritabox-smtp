<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp;

class Mail {

    /**
     * From address
     */
    protected string $from;

    /**
     * Recipients addresses
     */
    protected array $recipients;

    /**
     * Mail contents
     */
    protected string $contents;

    /**
     * Constructor
     * @param string $from       From address
     * @param array  $recipients Recipients addresses
     * @param string $contents   Mail contents
     */
    public function __construct(string $from  = '', array $recipients  = [], string $contents  = '') {
        $this->from = $from;
        $this->recipients = $recipients;
        $this->contents = $contents;
    }

    /**
     * Get from address
     */
    public function getFrom(): string {
        return $this->from;
    }

    /**
     * Get recipients addresses
     */
    public function getRecipients(): array {
        return $this->recipients;
    }

    /**
     * Get mail contents
     */
    public function getContents(): string {
        return $this->contents;
    }

    /**
     * Set from address
     * @return $this
     */
    public function setFrom(string $from) {
        $this->from = $from;
        return $this;
    }

    /**
     * Set recipients addresses
     * @return $this
     */
    public function setRecipients(array $recipients) {
        $this->recipients = $recipients;
        return $this;
    }

    /**
     * Set mail contents
     * @return $this
     */
    public function setContents(string $contents) {
        $this->contents = $contents;
        return $this;
    }
}
