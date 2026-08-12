<?php

namespace Tests\Unit;

use App\Support\ChurchPlace;
use PHPUnit\Framework\TestCase;

class ChurchPlaceTest extends TestCase
{
    public function test_place_key_requires_name_country_and_admin(): void
    {
        $this->assertNull(ChurchPlace::placeKey([
            'name' => 'St Mary',
            'place_country_code' => 'EG',
        ]));

        $this->assertNotNull(ChurchPlace::placeKey([
            'name' => 'St Mary',
            'place_country_code' => 'EG',
            'place_governorate' => 'Alexandria',
        ]));

        $this->assertNotNull(ChurchPlace::placeKey([
            'name' => 'St Mary',
            'place_country_code' => 'US',
            'place_district' => 'Brooklyn',
        ]));
    }

    public function test_same_name_different_places_produce_different_keys(): void
    {
        $a = ChurchPlace::placeKey([
            'name' => 'St Mary',
            'place_country_code' => 'EG',
            'place_governorate' => 'Alexandria',
            'place_district' => 'Smouha',
        ]);
        $b = ChurchPlace::placeKey([
            'name' => 'St Mary',
            'place_country_code' => 'EG',
            'place_governorate' => 'Cairo',
            'place_district' => 'Maadi',
        ]);

        $this->assertNotSame($a, $b);
    }

    public function test_arabic_name_normalization_unifies_place_key(): void
    {
        $a = ChurchPlace::placeKey([
            'name' => 'كنيسة العذراء',
            'place_country_code' => 'eg',
            'place_governorate' => 'الإسكندرية',
        ]);
        $b = ChurchPlace::placeKey([
            'name' => 'كنيسه العذراء',
            'place_country_code' => 'EG',
            'place_governorate' => 'الإسكندريه',
        ]);

        $this->assertSame($a, $b);
    }

    public function test_short_name_truncated_to_forty(): void
    {
        $long = str_repeat('أ', 50);
        $this->assertSame(40, mb_strlen(ChurchPlace::shortName($long, 'ignored')));
    }

    public function test_shown_name_includes_place_suffix(): void
    {
        $shown = ChurchPlace::shownName([
            'short_name' => 'العذراء',
            'name' => 'كنيسة العذراء مريم',
            'place_district' => 'Smouha',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
        ]);

        $this->assertSame('العذراء — Smouha, Alexandria, EG', $shown);
    }

    public function test_sync_into_public_settings_maps_address_and_city(): void
    {
        $settings = ChurchPlace::syncIntoPublicSettings([], [
            'place_street' => '12 Nile St',
            'place_district' => 'Smouha',
            'place_region' => 'East',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
        ]);

        $this->assertSame('12 Nile St, Smouha, East, Alexandria, EG', $settings['public']['address']);
        $this->assertSame('Smouha', $settings['public']['city']);
    }
}
