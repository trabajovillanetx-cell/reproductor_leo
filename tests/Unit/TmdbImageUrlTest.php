<?php

namespace Tests\Unit;

use App\Support\TmdbImageUrl;
use PHPUnit\Framework\TestCase;

final class TmdbImageUrlTest extends TestCase
{
    public function test_upgrades_small_tmdb_sizes(): void
    {
        $u = 'https://image.tmdb.org/t/p/w500/AbCdE.jpg';
        $this->assertSame('https://image.tmdb.org/t/p/w1280/AbCdE.jpg', TmdbImageUrl::upsizePosterForHero($u));
    }

    public function test_leaves_original_and_large_sizes(): void
    {
        $this->assertSame(
            'https://image.tmdb.org/t/p/original/x.jpg',
            TmdbImageUrl::upsizePosterForHero('https://image.tmdb.org/t/p/original/x.jpg')
        );
        $this->assertSame(
            'https://image.tmdb.org/t/p/w1280/x.jpg',
            TmdbImageUrl::upsizePosterForHero('https://image.tmdb.org/t/p/w1280/x.jpg')
        );
    }

    public function test_leaves_non_tmdb_urls(): void
    {
        $local = 'https://cdn.example.com/posters/foo.png';
        $this->assertSame($local, TmdbImageUrl::upsizePosterForHero($local));
    }
}
