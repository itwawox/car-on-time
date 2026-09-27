<?php

namespace App\Services\Bitrix24;

use RuntimeException;

class Bitrix24Exception extends RuntimeException
{
    /** Временный сбой (сеть, лимит запросов, 5xx) — есть смысл повторить позже. */
    public function __construct(string $message, public readonly bool $transient = false, public readonly ?array $response = null)
    {
        parent::__construct($message);
    }
}
