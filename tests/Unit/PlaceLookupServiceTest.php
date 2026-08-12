<?php

namespace Tests\Unit;

use App\Services\PlaceLookupService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceLookupServiceTest extends TestCase
{
    public function test_maps_nominatim_row_to_place_fields(): void
    {
        $mapped = app(PlaceLookupService::class)->mapNominatimRow([
            'display_name' => 'Smouha, Alexandria, Egypt',
            'lat' => '31.21',
            'lon' => '29.94',
            'address' => [
                'road' => 'Street 5',
                'house_number' => '10',
                'suburb' => 'Smouha',
                'county' => 'Montaza',
                'state' => 'Alexandria',
                'country_code' => 'eg',
            ],
        ]);

        $this->assertSame('10 Street 5', $mapped['place_street']);
        $this->assertSame('Smouha', $mapped['place_district']);
        $this->assertSame('Montaza', $mapped['place_region']);
        $this->assertSame('Alexandria', $mapped['place_governorate']);
        $this->assertSame('EG', $mapped['place_country_code']);
    }

    public function test_search_uses_http_fake_and_returns_mapped_results(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => 'Maadi, Cairo, Egypt',
                    'lat' => '29.96',
                    'lon' => '31.25',
                    'address' => [
                        'city' => 'Maadi',
                        'state' => 'Cairo',
                        'country_code' => 'eg',
                    ],
                ],
            ], 200),
        ]);

        $results = app(PlaceLookupService::class)->search('Maadi Cairo', 'EG', 3);

        $this->assertCount(1, $results);
        $this->assertSame('Maadi', $results[0]['place_district']);
        $this->assertSame('Cairo', $results[0]['place_governorate']);
        $this->assertSame('EG', $results[0]['place_country_code']);
    }
}
