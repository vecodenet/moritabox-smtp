<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp\Auth;

use MoritaBox\Smtp\SmtpServer;

class CramMd5Auth extends AbstractAuth {

    /**
     * Challenge
     */
    protected string $challenge;

    /**
     * Constructor
     * @param string $domain Fully-qualified domain name
     */
    public function __construct(string $domain) {
        $this->challenge = $this->generateChallenge($domain);
    }

    /**
     * @inheritdoc
     */
    public function getType(): string {
        return 'CRAM-MD5';
    }

    /**
     * Get challenge
     */
    public function getChallenge(): string {
        return $this->challenge;
    }

    /**
     * Decode auth token
     * @param  string $token Auth token
     */
    public function decode(string $token): void {
        $parts = explode(' ', @base64_decode($token));
        $this->user = $parts[0] ?? '';
        $this->password = $parts[1] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function validate(SmtpServer $server): bool {
        $password = $server->getUserPassword($this->user);
        if ($password !== false) {
            $key = base64_decode($this->challenge);
            $check = hash_hmac('md5', $key, $password);
            return hash_equals($check, $this->password);
        } else {
            return false;
        }
    }

    /**
     * Generate challenge
     */
    protected function generateChallenge(string $domain): string {
        $strong = true;
        $random = openssl_random_pseudo_bytes(32, $strong);
        $challenge = '<'.bin2hex($random)."@{$domain}>'";
        return $challenge;
    }
}
