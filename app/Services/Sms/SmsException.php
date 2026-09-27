<?php

namespace App\Services\Sms;

use RuntimeException;

class SmsException extends RuntimeException
{
    public function __construct(string $message, public readonly ?array $response = null)
    {
        parent::__construct($message);
    }
}
