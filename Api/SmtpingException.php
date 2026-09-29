<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Api;

class SmtpingException extends \RuntimeException
{
    public function __construct(string $message, private int $status = 0)
    {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isAuth(): bool
    {
        return in_array($this->status, [401, 403], true);
    }

    public function isNoCredits(): bool
    {
        return 402 === $this->status;
    }
}
