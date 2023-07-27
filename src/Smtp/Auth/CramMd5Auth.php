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
     */
    public function __construct() {
        $this->challenge = $this->generateChallenge();
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
     * Set user name
     * @param  string $user User name
     * @return $this
     */
    public function setUser(string $user) {
        $this->user = @base64_decode($user);
        return $this;
    }

    /**
     * Set password
     * @param  string $password Password
     * @return $this
     */
    public function setPassword(string $password) {
        $this->password = @base64_decode($password);
        return $this;
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
    protected function generateChallenge(): string {
        $strong = true;
        $random = openssl_random_pseudo_bytes(32, $strong);
        $challenge = '<'.bin2hex($random).'@moritabox.com>';
        return $challenge;
    }
}