<?php

namespace Tests\Unit;

use App\Services\CompaniesHouseAccountsParser;
use Tests\TestCase;

class CompaniesHouseAccountsParserTest extends TestCase
{
    private CompaniesHouseAccountsParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CompaniesHouseAccountsParser;
    }

    public function test_parse_extracts_full_accounts_figures(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/companies-house/full-accounts.xhtml'));

        $result = $this->parser->parse($html);

        $this->assertSame(450_000, $result['turnover']);
        $this->assertSame(62_000, $result['profit_before_tax']);
        $this->assertSame(120_000, $result['net_assets']);
        $this->assertSame(8, $result['employees']);
        $this->assertSame('2025-03-31', $result['period_end']);
    }

    public function test_parse_returns_null_profit_for_micro_entity_without_p_and_l(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/companies-house/micro-entity.xhtml'));

        $result = $this->parser->parse($html);

        $this->assertNull($result['turnover']);
        $this->assertNull($result['profit_before_tax']);
        $this->assertSame(5000, $result['net_assets']);
    }

    public function test_find_latest_electronic_accounts_filing_skips_paper(): void
    {
        $filings = [
            [
                'category' => 'accounts',
                'paper_filed' => true,
                'links' => ['document_metadata' => 'https://example.test/paper'],
            ],
            [
                'category' => 'accounts',
                'paper_filed' => false,
                'transaction_id' => 'abc123',
                'links' => ['document_metadata' => 'https://example.test/electronic'],
            ],
        ];

        $result = $this->parser->findLatestElectronicAccountsFiling($filings);

        $this->assertSame('abc123', $result['transaction_id']);
    }
}
