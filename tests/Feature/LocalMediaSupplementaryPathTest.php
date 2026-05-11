<?php

namespace Tests\Feature;

use App\Services\LocalMediaService;
use Tests\TestCase;

class LocalMediaSupplementaryPathTest extends TestCase
{
    public function test_skips_bdmv_stream_paths(): void
    {
        $s = app(LocalMediaService::class);

        $this->assertTrue($s->shouldSkipSupplementaryVideoPath('D:\\Pelis\\Film\\BDMV\\STREAM\\00001.m2ts'));
        $this->assertTrue($s->shouldSkipSupplementaryVideoPath('D:/Pelis/Film/BDMV/STREAM/foo.ts'));
        $this->assertFalse($s->shouldSkipSupplementaryVideoPath('D:\\Pelis\\Film\\Film 2024.mkv'));
    }

    public function test_skips_extras_and_similar_segments(): void
    {
        $s = app(LocalMediaService::class);

        $this->assertTrue($s->shouldSkipSupplementaryVideoPath('R:\\Cine\\Matrix\\extras\\making_of.mkv'));
        $this->assertTrue($s->shouldSkipSupplementaryVideoPath('R:/Cine/Matrix/featurettes/foo.mp4'));
        $this->assertFalse($s->shouldSkipSupplementaryVideoPath('R:\\Cine\\Matrix\\Matrix.mkv'));
    }

    public function test_skips_sample_and_trailer_filename_prefixes(): void
    {
        $s = app(LocalMediaService::class);

        $this->assertTrue($s->shouldSkipSupplementaryVideoPath('R:\\Cine\\sample.mkv'));
        $this->assertTrue($s->shouldSkipSupplementaryVideoPath('R:\\Cine\\trailer_oficial.mp4'));
        $this->assertFalse($s->shouldSkipSupplementaryVideoPath('R:\\Cine\\The_Trailer_Park_Boys.mkv'));
    }
}
