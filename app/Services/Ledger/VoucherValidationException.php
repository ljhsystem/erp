<?php

namespace App\Services\Ledger;

class VoucherValidationException extends \RuntimeException
{
    public function __construct(string $message, private readonly string $validationType)
    {
        parent::__construct($message);
    }

    public function getValidationType(): string
    {
        return $this->validationType;
    }
}
