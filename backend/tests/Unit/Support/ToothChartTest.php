<?php

namespace Tests\Unit\Support;

use App\Support\ToothChart;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ToothChartTest extends TestCase
{
    public function test_all_codes_contains_exactly_52_unique_valid_fdi_codes(): void
    {
        $codes = ToothChart::allCodes();

        $this->assertCount(52, $codes);
        $this->assertCount(52, array_unique($codes));
    }

    public function test_all_codes_covers_every_expected_permanent_and_primary_range(): void
    {
        $codes = ToothChart::allCodes();

        foreach ([11, 12, 13, 14, 15, 16, 17, 18, 21, 28, 31, 38, 41, 48] as $expected) {
            $this->assertContains((string) $expected, $codes);
        }

        foreach ([51, 52, 53, 54, 55, 61, 65, 71, 75, 81, 85] as $expected) {
            $this->assertContains((string) $expected, $codes);
        }

        // Primary dentition has no premolars — quadrant 5 stops at position 5, never reaches 6-8.
        $this->assertNotContains('56', $codes);
        $this->assertNotContains('58', $codes);
    }

    #[DataProvider('validCodes')]
    public function test_is_valid_code_accepts_every_real_fdi_code(string $code): void
    {
        $this->assertTrue(ToothChart::isValidCode($code));
    }

    public static function validCodes(): array
    {
        return array_map(fn (string $code) => [$code], ToothChart::allCodes());
    }

    #[DataProvider('invalidCodes')]
    public function test_is_valid_code_rejects_out_of_range_codes(string $code): void
    {
        $this->assertFalse(ToothChart::isValidCode($code));
    }

    public static function invalidCodes(): array
    {
        return [
            ['19'], // permanent quadrant has no position 9
            ['91'], // quadrant 9 doesn't exist
            ['56'], // primary quadrant only goes to position 5
            ['00'],
            [''],
            ['1'],
        ];
    }

    public function test_is_permanent_distinguishes_permanent_from_primary(): void
    {
        $this->assertTrue(ToothChart::isPermanent('11'));
        $this->assertTrue(ToothChart::isPermanent('48'));
        $this->assertFalse(ToothChart::isPermanent('51'));
        $this->assertFalse(ToothChart::isPermanent('85'));
    }

    public function test_is_anterior_distinguishes_anterior_from_posterior_in_both_dentitions(): void
    {
        // Permanent: 1-3 anterior (incisors/canine), 4-8 posterior (premolars/molars).
        $this->assertTrue(ToothChart::isAnterior('11'));
        $this->assertTrue(ToothChart::isAnterior('13'));
        $this->assertFalse(ToothChart::isAnterior('14'));
        $this->assertFalse(ToothChart::isAnterior('18'));

        // Primary: 1-3 anterior, 4-5 posterior (molars — no premolars in primary dentition).
        $this->assertTrue(ToothChart::isAnterior('53'));
        $this->assertFalse(ToothChart::isAnterior('54'));
    }

    public function test_display_name_for_known_permanent_and_primary_codes(): void
    {
        $this->assertSame('Upper Right Central Incisor', ToothChart::displayName('11'));
        $this->assertSame('Upper Right First Molar', ToothChart::displayName('16'));
        $this->assertSame('Lower Right Third Molar', ToothChart::displayName('48'));
        $this->assertSame('Upper Right Second Molar (Primary)', ToothChart::displayName('55'));
    }

    public function test_display_name_throws_for_an_invalid_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ToothChart::displayName('99');
    }
}
