<?php

namespace App\Services\Otp;

use Exception;

class OtpThrottledException extends Exception
{
    public function __construct(string $message, public readonly int $retryAfterSeconds = 0)
    {
        parent::__construct($message);
    }
}
