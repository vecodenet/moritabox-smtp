<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp\Auth;

class LoginAuth extends AbstractAuth {

    /**
     * @inheritdoc
     */
    public function getType(): string {
        return 'LOGIN';
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
}