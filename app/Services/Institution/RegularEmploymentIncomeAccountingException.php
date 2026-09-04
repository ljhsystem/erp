<?php

namespace App\Services\Institution;

final class RegularEmploymentIncomeAccountingException extends \InvalidArgumentException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly ?string $correlationId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }
}
