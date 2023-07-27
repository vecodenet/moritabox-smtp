<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp\Auth;

class PlainAuth extends AbstractAuth {

    /**
     * @inheritdoc
     */
    public function getType(): string {
        return 'PLAIN';
    }

    /**
     * Decode auth token
     * @param  string $token Auth token
     */
    public function decode(string $token): void {
        $data = @base64_decode($token);
        $parts = $data ? explode("\000", $data) : [];
        $this->user = $parts[1] ?? '';
        $this->password = $parts[2] ?? '';
    }
}