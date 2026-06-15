<?php

namespace Tests\Unit;

use App\Services\KeywordPlanner\KeywordPlannerCsvImporter;
use App\Services\KeywordPlanner\KeywordPlannerImportException;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KeywordPlannerCsvImporterTest extends TestCase
{
    private KeywordPlannerCsvImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new KeywordPlannerCsvImporter;
    }

    public function test_parses_valid_keyword_planner_export(): void
    {
        $result = $this->importer->import($this->fixture('keyword-planner-export.csv'));

        $this->assertSame(7.00, $result->benchmark);
        $this->assertSame(12, $result->totalCount);
        $this->assertSame(9, $result->commercialCount);
        $this->assertContains('business consultant uk', $result->keywords);
        $this->assertContains('graduate consulting jobs', $result->keywords);
    }

    public function test_excludes_non_commercial_keywords_from_median(): void
    {
        $result = $this->importer->import($this->fixture('keyword-planner-export.csv'));

        $this->assertContains('graduate consulting jobs', $result->keywords);
        $this->assertContains('consulting careers', $result->keywords);
        $this->assertSame(7.00, $result->benchmark);
    }

    public function test_rounds_median_to_nearest_fifty_pence(): void
    {
        $csv = <<<'CSV'
Keyword Stats
"June 1, 2025 - May 31, 2026"
Keyword	Currency	Avg. monthly searches	Three month change	YoY change	Competition	Competition (indexed value)	Top of page bid (low range)	Top of page bid (high range)
private dental clinic near me	GBP	500	0%	0%	Medium	50	5.10	6.75
dentist private near me	GBP	500	0%	0%	Medium	48	4.90	6.37
private dental practices	GBP	500	0%	0%	Medium	46	4.80	6.28
private dental clinic	GBP	500	0%	0%	Medium	47	5.00	6.38
CSV;

        $result = $this->importer->import($this->csvUpload($csv));

        $this->assertSame(6.50, $result->benchmark);
        $this->assertSame(4, $result->commercialCount);
    }

    public function test_skips_rows_without_high_bid(): void
    {
        $result = $this->importer->import($this->fixture('keyword-planner-export.csv'));

        $this->assertNotContains('csr consulting', $result->keywords);
    }

    public function test_rejects_unrecognised_format(): void
    {
        $csv = "Keyword,High bid\nfoo,1.23\n";

        $this->expectException(KeywordPlannerImportException::class);
        $this->expectExceptionMessage('Keyword Planner export');

        $this->importer->import($this->csvUpload($csv));
    }

    public function test_rejects_file_without_bid_data(): void
    {
        $csv = <<<'CSV'
Keyword Stats
"June 1, 2025 - May 31, 2026"
Keyword	Currency	Avg. monthly searches	Three month change	YoY change	Competition	Competition (indexed value)	Top of page bid (low range)	Top of page bid (high range)
csr consulting	GBP	50	0%	0%	Low	10
CSV;

        $this->expectException(KeywordPlannerImportException::class);
        $this->expectExceptionMessage('No keywords with bid data');

        $this->importer->import($this->csvUpload($csv));
    }

    public function test_rejects_when_all_rows_are_non_commercial(): void
    {
        $this->expectException(KeywordPlannerImportException::class);
        $this->expectExceptionMessage('No commercial keywords');

        $this->importer->import($this->fixture('keyword-planner-non-commercial-only.csv'));
    }

    private function fixture(string $filename): UploadedFile
    {
        return new UploadedFile(
            base_path("tests/fixtures/{$filename}"),
            $filename,
            'text/csv',
            null,
            true,
        );
    }

    private function csvUpload(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'keyword-planner-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'export.csv', 'text/csv', null, true);
    }
}
