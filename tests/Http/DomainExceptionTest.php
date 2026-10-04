<?php

namespace App\Tests\Http;

use App\Http\DomainException;
use PHPUnit\Framework\TestCase;

class DomainExceptionTest extends TestCase
{
    public function testCarriesCodeMessageAndStatus(): void
    {
        $error = new DomainException('NOT_FOUND', 'Animal not found', 404);

        $this->assertSame('NOT_FOUND', $error->errorCode());
        $this->assertSame('Animal not found', $error->getMessage());
        $this->assertSame(404, $error->httpStatus());
    }
}
