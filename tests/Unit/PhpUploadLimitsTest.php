<?php

namespace Tests\Unit;

use App\Support\PhpUploadLimits;
use PHPUnit\Framework\TestCase;

class PhpUploadLimitsTest extends TestCase
{
    public function test_ini_shorthand_to_bytes(): void
    {
        $this->assertSame(100 * 1024 * 1024, PhpUploadLimits::iniShorthandToBytes('100M'));
        $this->assertSame(2 * 1024 * 1024 * 1024, PhpUploadLimits::iniShorthandToBytes('2G'));
        $this->assertSame(512 * 1024, PhpUploadLimits::iniShorthandToBytes('512K'));
        $this->assertSame(0, PhpUploadLimits::iniShorthandToBytes(''));
    }

    public function test_human_bytes(): void
    {
        $this->assertStringContainsString('MB', PhpUploadLimits::humanBytes(10 * 1024 * 1024));
    }
}
