<?php

namespace Tests\Unit;

use App\Support\ArabicNameNormalizer;
use PHPUnit\Framework\TestCase;

class ArabicNameNormalizerTest extends TestCase
{
    public function test_hala_variants_normalize_identically(): void
    {
        $this->assertSame(
            ArabicNameNormalizer::normalize('هالة'),
            ArabicNameNormalizer::normalize('هاله')
        );
        $this->assertSame('هاله', ArabicNameNormalizer::normalize('هالة'));
    }

    public function test_school_with_diacritics(): void
    {
        $this->assertSame('مدرسه', ArabicNameNormalizer::normalize('مَدْرَسَة'));
    }

    public function test_alef_taa_yaa_and_diacritics(): void
    {
        $this->assertSame('احمد', ArabicNameNormalizer::normalize('أحمد'));
        $this->assertSame('احمد', ArabicNameNormalizer::normalize('إحمـد')); // tatweel
        $this->assertSame('مصطفي', ArabicNameNormalizer::normalize('مصطفى')); // ى → ي
    }

    public function test_from_parts_and_latin_lowercase(): void
    {
        $this->assertSame(
            'john smith',
            ArabicNameNormalizer::fromParts('John', 'Smith', null)
        );
        $this->assertSame('', ArabicNameNormalizer::normalize('   '));
    }
}
