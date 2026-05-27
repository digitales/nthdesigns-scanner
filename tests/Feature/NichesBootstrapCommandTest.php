<?php

namespace Tests\Feature;

use App\Services\GooglePlacesService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NichesBootstrapCommandTest extends TestCase
{
    private const ONS_URL = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/Major_Towns_and_Cities_Dec_2015_Names_and_Codes_in_England_and_Wales_2022/FeatureServer/0/query*';

    private const TAXONOMY_URL = 'https://developers.google.com/maps/documentation/places/web-service/place-types';

    public function test_fetches_ons_cities_and_merges_supplementary_settlements(): void
    {
        Http::fake([
            self::ONS_URL => Http::response([
                'features' => [
                    ['attributes' => ['TCITY15NM' => 'Bristol']],
                    ['attributes' => ['TCITY15NM' => 'Bristol']],
                ],
            ]),
            self::TAXONOMY_URL => Http::response('<html>dentist physiotherapist</html>', 200),
        ]);

        config(['services.google_places.key' => '']);

        $this->artisan('niches:bootstrap', ['--dry-run' => true])
            ->expectsOutputToContain('Found')
            ->expectsOutputToContain('Edinburgh')
            ->assertExitCode(0);
    }

    public function test_ons_api_error_json_uses_fallback_cities(): void
    {
        Http::fake([
            self::ONS_URL => Http::response(['error' => ['code' => 400, 'message' => 'Invalid URL']], 200),
            self::TAXONOMY_URL => Http::response('<html>dentist</html>', 200),
        ]);

        config(['services.google_places.key' => '']);

        $this->artisan('niches:bootstrap', ['--dry-run' => true])
            ->expectsOutputToContain('Found 30 UK settlements')
            ->assertExitCode(0);
    }

    public function test_ons_failure_uses_fallback_cities(): void
    {
        Http::fake([
            self::ONS_URL => Http::response('', 500),
            self::TAXONOMY_URL => Http::response('<html>dentist</html>', 200),
        ]);

        config(['services.google_places.key' => '']);

        $this->artisan('niches:bootstrap', ['--dry-run' => true])
            ->expectsOutputToContain('Found 30 UK settlements')
            ->assertExitCode(0);
    }

    public function test_extracts_and_filters_place_types_from_taxonomy_html(): void
    {
        Http::fake([
            self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
            self::TAXONOMY_URL => Http::response(
                '<code>dentist</code> <code>atm</code> <code>physiotherapist</code> <code>shopping_mall</code>',
                200,
            ),
        ]);

        config(['services.google_places.key' => '']);

        $this->mock(GooglePlacesService::class);

        $this->artisan('niches:bootstrap', ['--dry-run' => true])
            ->expectsOutputToContain('Extracted')
            ->expectsOutputToContain('primary_type')
            ->doesntExpectOutputToContain("'primary_type' => 'atm'")
            ->assertExitCode(0);
    }

    public function test_taxonomy_failure_uses_fallback_niches(): void
    {
        Http::fake([
            self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
            self::TAXONOMY_URL => Http::response('', 500),
        ]);

        config(['services.google_places.key' => '']);

        $this->mock(GooglePlacesService::class);

        $this->artisan('niches:bootstrap', ['--dry-run' => true])
            ->expectsOutputToContain('Dental Practice')
            ->assertExitCode(0);
    }

    public function test_validation_drops_niches_below_min_results(): void
    {
        Http::fake([
            self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
            self::TAXONOMY_URL => Http::response('<html>dentist sparse_niche_type</html>', 200),
        ]);

        config(['services.google_places.key' => 'test-key']);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('searchByNicheAndCity')
                ->andReturnUsing(function (string $query) {
                    return $query === 'dentist' ? array_fill(0, 10, 'places/1') : [];
                });
        });

        $path = config_path('niches.php');
        $backup = file_get_contents($path);

        try {
            $this->artisan('niches:bootstrap', ['--min-results' => 5, '--no-interaction' => true])
                ->expectsOutputToContain('Kept')
                ->assertExitCode(0);

            $written = file_get_contents($path);
            $this->assertStringContainsString("'query' => 'dentist'", $written);
            $this->assertStringNotContainsString('sparse_niche_type', $written);
        } finally {
            file_put_contents($path, $backup);
        }
    }

    public function test_skips_validation_when_api_key_missing(): void
    {
        Http::fake([
            self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
            self::TAXONOMY_URL => Http::response('<html>dentist</html>', 200),
        ]);

        config(['services.google_places.key' => '']);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldNotReceive('searchByNicheAndCity');
        });

        $this->artisan('niches:bootstrap', ['--dry-run' => true])
            ->expectsOutputToContain('skipping validation')
            ->assertExitCode(0);
    }

    public function test_dry_run_does_not_write_config_file(): void
    {
        Http::fake([
            self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
            self::TAXONOMY_URL => Http::response('<html>dentist</html>', 200),
        ]);

        config(['services.google_places.key' => '']);

        $path = config_path('niches.php');
        $before = filemtime($path);

        $this->artisan('niches:bootstrap', ['--dry-run' => true])
            ->expectsOutputToContain('dry-run')
            ->assertExitCode(0);

        $this->assertSame($before, filemtime($path));
    }

    public function test_declines_overwrite_when_operator_says_no(): void
    {
        Http::fake([
            self::ONS_URL => Http::response(['features' => [['attributes' => ['TCITY15NM' => 'Leeds']]]]),
            self::TAXONOMY_URL => Http::response('<html>dentist</html>', 200),
        ]);

        config(['services.google_places.key' => '']);
        $this->mock(GooglePlacesService::class);

        $path = config_path('niches.php');
        $backup = file_get_contents($path);
        file_put_contents($path, "<?php\nreturn ['marker' => true];\n");

        try {
            $this->artisan('niches:bootstrap')
                ->expectsConfirmation('config/niches.php already exists. Overwrite?', 'no')
                ->expectsOutputToContain('skipped')
                ->assertExitCode(0);

            $this->assertStringContainsString('marker', file_get_contents($path));
        } finally {
            file_put_contents($path, $backup);
        }
    }
}
