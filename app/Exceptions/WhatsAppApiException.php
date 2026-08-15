<?php

namespace App\Exceptions;

use Exception;

class WhatsAppApiException extends Exception
{
    protected array $details;

    public function __construct(string $message, int $code = 0, ?Exception $previous = null, array $details = [])
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
