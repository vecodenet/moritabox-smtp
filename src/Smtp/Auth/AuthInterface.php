<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp\Auth;

use MoritaBox\Smtp\SmtpServer;

interface AuthInterface {

    /**
     * Get auth type
     */
    public function getType(): string;

    /**
     * Get user name
     */
    public function getUser(): string;

    /**
     * Get password
     */
    public function getPassword(): string;

    /**
     * Validate credentials
     */
    public function validate(SmtpServer $server): bool;
}