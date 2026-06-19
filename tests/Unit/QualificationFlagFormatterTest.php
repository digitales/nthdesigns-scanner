<?php

namespace Tests\Unit;

use App\Support\QualificationFlagFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QualificationFlagFormatterTest extends TestCase
{
    #[DataProvider('snakeCaseFlagProvider')]
    public function test_formats_snake_case_flags(string $input, string $expected): void
    {
        $this->assertSame($expected, QualificationFlagFormatter::format($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function snakeCaseFlagProvider(): array
    {
        return [
            'single location' => ['single_location_london', 'Single Location London'],
            'direct email' => ['direct_email_contact', 'Direct Email Contact'],
            'local business schema' => ['local_business_schema', 'Local Business Schema'],
            'no corporate branding' => ['no_corporate_branding', 'No Corporate Branding'],
            'independent booking' => ['independent_booking_system', 'Independent Booking System'],
        ];
    }

    public function test_formats_known_skip_flags(): void
    {
        $this->assertSame('Wrong niche', QualificationFlagFormatter::format('wrong_niche'));
        $this->assertSame('Corporate chain or franchise', QualificationFlagFormatter::format('corporate_chain'));
    }

    public function test_leaves_plain_english_flags_unchanged(): void
    {
        $flag = 'Named dentist visible on About page';

        $this->assertSame($flag, QualificationFlagFormatter::format($flag));
    }

    public function test_format_many_preserves_order(): void
    {
        $this->assertSame(
            ['Direct Email Contact', 'Wrong niche', 'Named owner visible'],
            QualificationFlagFormatter::formatMany([
                'direct_email_contact',
                'wrong_niche',
                'Named owner visible',
            ]),
        );
    }
}
