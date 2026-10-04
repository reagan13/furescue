<?php

namespace App\Http;

class DomainException extends \RuntimeException
{
    public function __construct(
        private string $errorCode,
        string $message,
        private int $httpStatus = 400
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function publicMessage(): string
    {
        return $this->getMessage();
    }
}
