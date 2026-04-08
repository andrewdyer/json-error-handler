<?php

declare(strict_types=1);

namespace YourVendor\YourPackage\Tests;

use PHPUnit\Framework\TestCase;
use YourVendor\YourPackage\JsonErrorHandler;

class JsonErrorHandlerTest extends TestCase
{
    public function testSayHello(): void
    {
        $pkg = new JsonErrorHandler();
        $this->assertSame('Hello, John!', $pkg->sayHello('John'));
    }
}
