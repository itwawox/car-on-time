<?php

namespace Tests\Unit\Search;

use App\Support\Search\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    #[DataProvider('samePhoneticKey')]
    public function test_russian_and_latin_spellings_share_a_key(string $a, string $b): void
    {
        $this->assertSame(TextNormalizer::key($a), TextNormalizer::key($b));
    }

    public static function samePhoneticKey(): array
    {
        return [
            ['camry', 'камри'],
            ['toyota', 'тойота'],
            ['mercedes', 'мерседес'],
            ['logan', 'логан'],
            ['rio', 'рио'],
            ['Polo', 'ПОЛО'],
        ];
    }

    public function test_wrong_keyboard_layout_is_swapped_both_ways(): void
    {
        $this->assertSame('mazd', TextNormalizer::swapLayout('ьфяв'));
        $this->assertSame('рено', TextNormalizer::swapLayout('htyj'));
        $this->assertSame('solaris', TextNormalizer::swapLayout('ыщдфкшы'));
    }

    public function test_tokens_fix_mixed_scripts_and_keep_compound_words(): void
    {
        // «М/T» в названиях набрано кириллической «М»
        $this->assertSame(['kia', 'rio', 'xline', '4x4', '16'], TextNormalizer::tokens('Kia Rio X-line 4x4 1.6'));
        $this->assertSame(['mt'], TextNormalizer::tokens('мt'));
    }

    public function test_damerau_distance_counts_transposition_as_one(): void
    {
        $this->assertSame(1, TextNormalizer::distance('hyudnai', 'hyundai'));
        $this->assertSame(0, TextNormalizer::distance('kia', 'kia'));
    }
}
