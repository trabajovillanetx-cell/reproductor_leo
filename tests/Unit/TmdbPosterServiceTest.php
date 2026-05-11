<?php

namespace Tests\Unit;

use App\Enums\ContentType;
use App\Services\TmdbPosterService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmdbPosterServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Config::set('services.tmdb.key', 'unit-test-key');
        Config::set('services.tmdb.language', 'es-ES');
        Config::set('services.tmdb.image_base', 'https://image.tmdb.org/t/p/w500');
        Config::set('services.tmdb.delay_ms_between_requests', 0);
    }

    public function test_skips_first_result_without_poster_path(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response([
                'results' => [
                    ['id' => 1, 'poster_path' => null, 'popularity' => 100.0],
                    ['id' => 2, 'poster_path' => '/from-second.jpg', 'popularity' => 1.0],
                ],
            ], 200),
            'api.themoviedb.org/3/search/tv*' => Http::response(['results' => []], 200),
        ]);

        $svc = new TmdbPosterService;
        $url = $svc->posterUrlForTitle('Alguna película', ContentType::Vod);

        $this->assertSame('https://image.tmdb.org/t/p/w500/from-second.jpg', $url);
    }

    public function test_prefers_higher_popularity_when_several_have_posters(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response([
                'results' => [
                    ['id' => 1, 'poster_path' => '/low.jpg', 'popularity' => 3.0],
                    ['id' => 2, 'poster_path' => '/high.jpg', 'popularity' => 80.0],
                ],
            ], 200),
            'api.themoviedb.org/3/search/tv*' => Http::response(['results' => []], 200),
        ]);

        $svc = new TmdbPosterService;
        $url = $svc->posterUrlForTitle('Alguna película', ContentType::Vod);

        $this->assertSame('https://image.tmdb.org/t/p/w500/high.jpg', $url);
    }

    public function test_falls_back_to_tv_when_movie_has_no_posters(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response([
                'results' => [
                    ['id' => 1, 'poster_path' => null],
                ],
            ], 200),
            'api.themoviedb.org/3/search/tv*' => Http::response([
                'results' => [
                    ['id' => 9, 'poster_path' => '/tv-poster.jpg'],
                ],
            ], 200),
        ]);

        $svc = new TmdbPosterService;
        $url = $svc->posterUrlForTitle('Nombre de serie', ContentType::Vod);

        $this->assertSame('https://image.tmdb.org/t/p/w500/tv-poster.jpg', $url);
    }

    public function test_verify_api_key_accepts_configuration_200(): void
    {
        Http::fake([
            'api.themoviedb.org/3/configuration*' => Http::response(['images' => ['base_url' => 'http://example.test/']], 200),
        ]);

        $svc = new TmdbPosterService;
        $r = $svc->verifyApiKey();

        $this->assertTrue($r['ok']);
        $this->assertSame(200, $r['status']);
    }

    public function test_verify_api_key_reports_401(): void
    {
        Http::fake([
            'api.themoviedb.org/3/configuration*' => Http::response(['status_message' => 'Invalid API key'], 401),
        ]);

        $svc = new TmdbPosterService;
        $r = $svc->verifyApiKey();

        $this->assertFalse($r['ok']);
        $this->assertSame(401, $r['status']);
        $this->assertStringContainsString('Invalid', $r['message']);
    }
}
