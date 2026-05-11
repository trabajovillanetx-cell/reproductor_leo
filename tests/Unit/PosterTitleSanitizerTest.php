<?php

namespace Tests\Unit;

use App\Support\PosterTitleSanitizer;
use PHPUnit\Framework\TestCase;

class PosterTitleSanitizerTest extends TestCase
{
    public function test_decodes_url_encoding_and_strips_technical_suffix(): void
    {
        $raw = 'Margrete%3A Reina del Norte (2021) WEB-DL 1080p HBOMAX';
        $out = PosterTitleSanitizer::forSearch($raw);
        $this->assertStringContainsString('Margrete', $out);
        $this->assertStringContainsString('Reina del Norte', $out);
        $this->assertStringNotContainsString('WEB-DL', $out);
        $this->assertStringNotContainsString('HBOMAX', $out);
    }

    public function test_strips_bracket_release_tags(): void
    {
        $raw = 'Interestelar [1080p BluRay x264]';
        $out = PosterTitleSanitizer::forSearch($raw);
        $this->assertStringContainsString('Interestelar', $out);
        $this->assertStringNotContainsString('1080p', $out);
    }

    public function test_plus_becomes_space(): void
    {
        $out = PosterTitleSanitizer::forSearch('Foo+Bar+Baz');
        $this->assertStringContainsString('Foo Bar Baz', $out);
    }

    public function test_strips_hd_latino_and_similar_tags(): void
    {
        $out = PosterTitleSanitizer::forSearch('Mi Película HD Latino Castellano');
        $this->assertStringContainsString('Mi Película', $out);
        $this->assertStringNotContainsString('Latino', $out);
        $this->assertStringNotContainsString('Castellano', $out);
    }
}
