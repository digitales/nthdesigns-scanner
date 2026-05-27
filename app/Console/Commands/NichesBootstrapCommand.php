<?php

namespace App\Console\Commands;

use App\Services\GooglePlacesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class NichesBootstrapCommand extends Command
{
    private const ONS_QUERY_URL = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/Major_Towns_and_Cities_Dec_2015_Names_and_Codes_in_England_and_Wales_2022/FeatureServer/0/query';

    private const TAXONOMY_URL = 'https://developers.google.com/maps/documentation/places/web-service/place-types';

    private const SUPPLEMENTARY_CITIES = [
        'Edinburgh', 'Glasgow', 'Aberdeen', 'Dundee', 'Inverness',
        'Cardiff', 'Swansea', 'Newport', 'Belfast', 'Derry',
    ];

    private const FALLBACK_CITIES = [
        'Birmingham', 'Manchester', 'Leeds', 'Sheffield', 'Bradford',
        'Liverpool', 'Bristol', 'Coventry', 'Leicester', 'Nottingham',
        'Newcastle', 'Southampton', 'Brighton', 'Plymouth', 'Stoke-on-Trent',
        'Wolverhampton', 'Derby', 'Swansea', 'Norwich', 'Luton',
        'Edinburgh', 'Glasgow', 'Aberdeen', 'Cardiff', 'Belfast',
        'London', 'Oxford', 'Cambridge', 'Bath', 'Exeter',
    ];

    private const TYPE_BLOCKLIST = [
        'transit', 'station', 'airport', 'parking', 'atm', 'bank',
        'finance', 'government', 'post_office', 'embassy', 'courthouse',
        'fire_station', 'police', 'prison', 'cemetery', 'funeral',
        'storage', 'moving', 'laundry', 'car_wash', 'car_repair',
        'gas_station', 'electric_vehicle', 'lodging', 'campground',
        'rv_park', 'grocery', 'supermarket', 'convenience', 'liquor',
        'hardware', 'home_goods', 'furniture', 'electronics', 'clothing',
        'shoe', 'jewelry', 'book_store', 'bicycle', 'department_store',
        'shopping_mall', 'wholesale', 'florist', 'gift', 'toy',
        'pet_store', 'aquarium', 'zoo', 'museum', 'art_gallery',
        'amusement', 'casino', 'movie', 'stadium', 'bowling',
        'night_club', 'bar', 'cafe', 'bakery', 'meal_takeaway',
    ];

    private const TYPE_ALLOWLIST = [
        'doctor', 'dentist', 'health', 'medical', 'hospital', 'clinic',
        'lawyer', 'legal', 'accountant', 'finance_advisor', 'insurance',
        'real_estate', 'physiotherapist', 'veterinary', 'optician',
        'beauty', 'hair', 'spa', 'gym', 'fitness', 'plumber', 'electrician',
        'contractor', 'architect', 'tutor', 'school', 'consultant',
    ];

    private const FALLBACK_NICHES = [
        ['label' => 'Dental Practice', 'query' => 'dental practice', 'primary_type' => 'dentist'],
        ['label' => 'Physiotherapist', 'query' => 'physiotherapist', 'primary_type' => 'physiotherapist'],
        ['label' => 'Solicitor', 'query' => 'solicitor', 'primary_type' => 'lawyer'],
        ['label' => 'Accountant', 'query' => 'accountant', 'primary_type' => 'accounting'],
        ['label' => 'Estate Agent', 'query' => 'estate agent', 'primary_type' => 'real_estate_agency'],
        ['label' => 'Independent Hotel', 'query' => 'independent hotel', 'primary_type' => 'lodging'],
        ['label' => 'Restaurant', 'query' => 'restaurant', 'primary_type' => 'restaurant'],
        ['label' => 'Optician', 'query' => 'optician', 'primary_type' => 'optician'],
        ['label' => 'Veterinary Practice', 'query' => 'vet practice', 'primary_type' => 'veterinary_care'],
        ['label' => 'Private GP', 'query' => 'private GP', 'primary_type' => 'doctor'],
        ['label' => 'Osteopath', 'query' => 'osteopath', 'primary_type' => 'physiotherapist'],
        ['label' => 'Chiropractor', 'query' => 'chiropractor', 'primary_type' => 'physiotherapist'],
        ['label' => 'Beauty Salon', 'query' => 'beauty salon', 'primary_type' => 'beauty_salon'],
        ['label' => 'Barbershop', 'query' => 'barbershop', 'primary_type' => 'hair_care'],
        ['label' => 'Plumber', 'query' => 'plumber', 'primary_type' => 'plumber'],
        ['label' => 'Electrician', 'query' => 'electrician', 'primary_type' => 'electrician'],
        ['label' => 'Architect', 'query' => 'architect', 'primary_type' => 'architect'],
        ['label' => 'Financial Adviser', 'query' => 'financial adviser', 'primary_type' => 'finance'],
        ['label' => 'Mortgage Broker', 'query' => 'mortgage broker', 'primary_type' => 'finance'],
        ['label' => 'Private Tutor', 'query' => 'private tutor', 'primary_type' => 'tutoring_center'],
    ];

    protected $signature = 'niches:bootstrap
        {--min-results=5}
        {--dry-run}';

    protected $description = 'Bootstrap config/niches.php from ONS cities and Places taxonomy';

    public function __construct(private GooglePlacesService $places)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $cities = $this->fetchCities();
            $this->info('Found '.count($cities).' UK settlements');

            $candidates = $this->fetchNicheCandidates();
            $this->info('Extracted '.count($candidates).' candidate niches from Places taxonomy');

            $validation = $this->validateNiches($candidates);
            $niches = $validation['niches'];
            $apiCalls = $validation['apiCalls'];

            $writtenPath = $this->writeConfig($niches, $cities);

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Niches', (string) count($niches)],
                    ['Cities', (string) count($cities)],
                    ['API calls used', $apiCalls.' (validation)'],
                    ['Config written', $writtenPath],
                ],
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return list<string>
     */
    private function fetchCities(): array
    {
        $response = Http::timeout(15)->get(self::ONS_QUERY_URL, [
            'where'              => '1=1',
            'outFields'          => 'TCITY15NM',
            'returnGeometry'     => 'false',
            'resultRecordCount'  => 2000,
            'f'                  => 'json',
        ]);

        if ($this->onsFetchFailed($response)) {
            $this->warn('ONS settlement fetch failed; using hardcoded city list.');

            return $this->sortCities(self::FALLBACK_CITIES);
        }

        $englishCities = collect($response->json('features', []))
            ->map(fn (array $feature) => $feature['attributes']['TCITY15NM'] ?? null)
            ->filter()
            ->values();

        if ($englishCities->isEmpty()) {
            $this->warn('ONS response contained no settlements; using hardcoded city list.');

            return $this->sortCities(self::FALLBACK_CITIES);
        }

        $names = $englishCities
            ->merge(self::SUPPLEMENTARY_CITIES)
            ->unique()
            ->values()
            ->all();

        return $this->sortCities($names);
    }

    private function onsFetchFailed(\Illuminate\Http\Client\Response $response): bool
    {
        if ($response->failed()) {
            return true;
        }

        $body = $response->json();

        return is_array($body) && isset($body['error']);
    }

    /**
     * @param  list<string>  $cities
     * @return list<string>
     */
    private function sortCities(array $cities): array
    {
        $sorted = $cities;
        sort($sorted);

        return $sorted;
    }

    /**
     * @return list<array{label: string, query: string, primary_type: string}>
     */
    private function fetchNicheCandidates(): array
    {
        $response = Http::timeout(15)->get(self::TAXONOMY_URL);

        if ($response->failed()) {
            $this->warn('Places taxonomy fetch failed; using hardcoded niche list.');

            return self::FALLBACK_NICHES;
        }

        preg_match_all('/\b[a-z][a-z_]{3,40}\b/', $response->body(), $matches);

        $filtered = collect($matches[0] ?? [])
            ->unique()
            ->filter(fn (string $type) => $this->typePassesFilter($type))
            ->map(fn (string $type) => [
                'primary_type' => $type,
                'label'        => Str::title(str_replace('_', ' ', $type)),
                'query'        => str_replace('_', ' ', $type),
            ])
            ->values()
            ->all();

        if ($filtered === []) {
            $this->warn('No types extracted from taxonomy; using hardcoded niche list.');

            return self::FALLBACK_NICHES;
        }

        return $filtered;
    }

    private function typePassesFilter(string $type): bool
    {
        foreach (self::TYPE_ALLOWLIST as $signal) {
            if (str_contains($type, $signal)) {
                return true;
            }
        }

        foreach (self::TYPE_BLOCKLIST as $signal) {
            if (str_contains($type, $signal)) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param  list<array{label: string, query: string, primary_type: string}>  $niches
     * @return array{niches: list<array{label: string, query: string, primary_type: string}>, apiCalls: int, dropped: int}
     */
    private function validateNiches(array $niches): array
    {
        $key = config('services.google_places.key');

        if ($key === null || $key === '') {
            $this->warn('GOOGLE_PLACES_API_KEY is not set; skipping validation pass.');

            return ['niches' => $niches, 'apiCalls' => 0, 'dropped' => 0];
        }

        $minResults = max(1, (int) $this->option('min-results'));
        $kept = [];
        $dropped = 0;
        $apiCalls = 0;
        $zeroResultCount = 0;

        $bar = $this->output->createProgressBar(count($niches));
        $bar->start();

        foreach ($niches as $niche) {
            $apiCalls++;
            $placeIds = $this->places->searchByNicheAndCity($niche['query'], 'Birmingham', 'GB');
            $count = count($placeIds);

            if ($count === 0) {
                $zeroResultCount++;
            }

            if ($count < $minResults) {
                $dropped++;
                $this->warn("Dropped {$niche['label']}: {$count} results in Birmingham");
            } else {
                $kept[] = $niche;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($zeroResultCount === count($niches) && count($niches) > 0) {
            $this->warn('Places API may have failed — keeping unvalidated niche list.');

            return ['niches' => $niches, 'apiCalls' => $apiCalls, 'dropped' => 0];
        }

        $this->info('Kept '.count($kept)." niches after validation pass ({$dropped} dropped)");

        return ['niches' => $kept, 'apiCalls' => $apiCalls, 'dropped' => $dropped];
    }

    /**
     * @param  list<array{label: string, query: string, primary_type: string}>  $niches
     * @param  list<string>  $cities
     */
    private function writeConfig(array $niches, array $cities): string
    {
        $this->info('Niches: '.count($niches).', Cities: '.count($cities));

        $contents = $this->renderConfigPhp($niches, $cities);
        $path = config_path('niches.php');

        if ($this->option('dry-run')) {
            $this->line($contents);

            return 'dry-run';
        }

        if (file_exists($path) && ! $this->option('no-interaction')) {
            if (! $this->confirm('config/niches.php already exists. Overwrite?', false)) {
                $this->warn('Config write skipped (declined overwrite).');

                return 'skipped (declined overwrite)';
            }
        }

        file_put_contents($path, $contents, LOCK_EX);

        return $path;
    }

    /**
     * @param  list<array{label: string, query: string, primary_type: string}>  $niches
     * @param  list<string>  $cities
     */
    private function renderConfigPhp(array $niches, array $cities): string
    {
        $date = now()->toDateString();
        $export = var_export(['niches' => $niches, 'cities' => $cities], true);

        return <<<PHP
<?php

// Generated by niches:bootstrap on {$date}
// Edit this file manually to add, remove, or rename niches.
// Re-run niches:bootstrap only if you need to expand from scratch.

return {$export};

PHP;
    }
}
