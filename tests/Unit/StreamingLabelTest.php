<?php

namespace Tests\Unit;

use App\Support\StreamingLabel;
use PHPUnit\Framework\TestCase;

class StreamingLabelTest extends TestCase
{
    public function test_decodes_percent_encoded_colon(): void
    {
        $this->assertSame('Sharper: Un Plan Perfecto', StreamingLabel::decode('Sharper%3A Un Plan Perfecto'));
    }

    public function test_decodes_plus_as_space(): void
    {
        $this->assertSame('Foo Bar', StreamingLabel::decode('Foo+Bar'));
    }

    public function test_trims_whitespace(): void
    {
        $this->assertSame('Hola', StreamingLabel::decode('  Hola  '));
    }

    public function test_normalize_library_path_is_case_insensitive_ready(): void
    {
        $this->assertSame('peliculas/apple tv/foo', StreamingLabel::normalizeLibraryPath('PELICULAS\\APPLE TV\\foo\\'));
    }
}
