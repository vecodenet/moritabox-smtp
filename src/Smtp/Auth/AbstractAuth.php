<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp\Auth;

use MoritaBox\Smtp\SmtpHandler;
use MoritaBox\Smtp\SmtpServer;

abstract class AbstractAuth implements AuthInterface {

    /**
     * User name
     */
    protected string $user = '';

    /**
     * Password
     */
    protected string $password = '';

    /**
     * Get user name
     */
    public function getUser(): string {
        return $this->user;
    }

    /**
     * Get password
     */
    public function getPassword(): string {
        return $this->password;
    }

    /**
     * @inheritdoc
     */
    public function validate(SmtpServer $server, ?SmtpHandler $handler): bool {
        $password = $server->getUserPassword($this->user, $handler);
        if ($password !== false) {
            return $this->password == $password;
        } else {
            return false;
        }
    }
}
