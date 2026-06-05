<?php

namespace App\Console\Commands;

use App\Services\NichesBootstrapSteps;
use Illuminate\Console\Command;
use Throwable;

class NichesBootstrapCommand extends Command
{
    protected $signature = 'niches:bootstrap
        {--min-results=5}
        {--dry-run}
        {--force : Overwrite config/niches.php without confirmation}';

    protected $description = 'Bootstrap config/niches.php from ONS cities and Places taxonomy';

    public function __construct(private NichesBootstrapSteps $steps)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $warn = fn (string $message) => $this->warn($message);

            $cities = $this->steps->fetchCities($warn);
            $this->info('Found '.count($cities).' UK settlements');

            $candidates = $this->steps->fetchNicheCandidates($warn);
            $this->info('Extracted '.count($candidates).' candidate niches from Places taxonomy');

            $validation = $this->validateNiches($candidates, $warn);
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
     * @param  list<array{label: string, query: string, primary_type: string}>  $candidates
     * @param  callable(string): void  $warn
     * @return array{niches: list<array{label: string, query: string, primary_type: string}>, apiCalls: int, dropped: int, keptUnvalidated: bool}
     */
    private function validateNiches(array $candidates, callable $warn): array
    {
        $minResults = max(1, (int) $this->option('min-results'));

        $bar = $this->output->createProgressBar(count($candidates));
        $bar->start();

        $validation = $this->steps->validateNiches(
            $candidates,
            $minResults,
            $warn,
            fn () => $bar->advance(),
        );

        $bar->finish();
        $this->newLine();

        if ($validation['apiCalls'] > 0 && ! $validation['keptUnvalidated']) {
            $this->info('Kept '.count($validation['niches'])." niches after validation pass ({$validation['dropped']} dropped)");
        }

        return $validation;
    }

    /**
     * @param  list<array{label: string, query: string, primary_type: string}>  $niches
     * @param  list<string>  $cities
     */
    private function writeConfig(array $niches, array $cities): string
    {
        $this->info('Niches: '.count($niches).', Cities: '.count($cities));

        $contents = $this->steps->renderConfigPhp($niches, $cities);
        $path = config_path('niches.php');

        if ($this->option('dry-run')) {
            $this->line($contents);

            return 'dry-run';
        }

        if (file_exists($path) && ! $this->option('force') && ! $this->option('no-interaction')) {
            if (! $this->confirm('config/niches.php already exists. Overwrite?', false)) {
                $this->warn('Config write skipped (declined overwrite).');

                return 'skipped (declined overwrite)';
            }
        }

        file_put_contents($path, $contents, LOCK_EX);

        return $path;
    }
}
