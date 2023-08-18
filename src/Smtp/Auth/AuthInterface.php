<?php

declare(strict_types = 1);

namespace MoritaBox\Smtp\Auth;

use MoritaBox\Smtp\SmtpHandler;
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
     * @param SmtpServer   $server  Server instance
     * @param ?SmtpHandler $handler Handler instance
     */
    public function validate(SmtpServer $server, ?SmtpHandler $handler): bool;
}
