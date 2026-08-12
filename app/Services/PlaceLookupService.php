<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxied Nominatim (OSM) place lookup — free/open-source with usage-policy constraints.
 * Always call from the server; never expose unbounded browser traffic to public Nominatim.
 */
class PlaceLookupService
{
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @return list<array{
     *     label: string,
     *     place_street: string,
     *     place_district: string,
     *     place_region: string,
     *     place_governorate: string,
     *     place_country_code: string,
     *     lat: float|null,
     *     lng: float|null,
     * }>
     */
    public function search(string $query, ?string $countryCode = null, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $countryCode = $countryCode ? strtoupper(trim($countryCode)) : null;
        $cacheKey = 'place_lookup:'.md5($query.'|'.($countryCode ?? '').'|'.$limit);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($query, $countryCode, $limit) {
            $params = [
                'q' => $query,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => $limit,
            ];
            if ($countryCode) {
                $params['countrycodes'] = strtolower($countryCode);
            }

            try {
                $response = Http::timeout(8)
                    ->withHeaders([
                        'User-Agent' => config('services.nominatim.user_agent', 'KhedmaChurchPlatform/1.0 (church-place-lookup)'),
                        'Accept-Language' => 'en,ar',
                    ])
                    ->get(config('services.nominatim.base_url', 'https://nominatim.openstreetmap.org/search'), $params);

                if (! $response->successful()) {
                    Log::warning('Nominatim search failed', ['status' => $response->status()]);

                    return [];
                }

                $rows = $response->json();
                if (! is_array($rows)) {
                    return [];
                }

                $out = [];
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $mapped = $this->mapNominatimRow($row);
                    if ($mapped !== null) {
                        $out[] = $mapped;
                    }
                }

                return $out;
            } catch (\Throwable $e) {
                Log::warning('Nominatim search exception', ['message' => $e->getMessage()]);

                return [];
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     label: string,
     *     place_street: string,
     *     place_district: string,
     *     place_region: string,
     *     place_governorate: string,
     *     place_country_code: string,
     *     lat: float|null,
     *     lng: float|null,
     * }|null
     */
    public function mapNominatimRow(array $row): ?array
    {
        $address = is_array($row['address'] ?? null) ? $row['address'] : [];
        $country = strtoupper(trim((string) ($address['country_code'] ?? '')));
        if ($country === '') {
            return null;
        }

        $street = trim(implode(' ', array_filter([
            $address['house_number'] ?? null,
            $address['road'] ?? $address['pedestrian'] ?? null,
        ])));

        $district = (string) (
            $address['suburb']
            ?? $address['neighbourhood']
            ?? $address['city_district']
            ?? $address['quarter']
            ?? $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['hamlet']
            ?? ''
        );

        $region = (string) (
            $address['county']
            ?? $address['state_district']
            ?? $address['municipality']
            ?? ''
        );

        $governorate = (string) (
            $address['state']
            ?? $address['province']
            ?? $address['region']
            ?? ''
        );

        return [
            'label' => (string) ($row['display_name'] ?? $district),
            'place_street' => $street,
            'place_district' => trim($district),
            'place_region' => trim($region),
            'place_governorate' => trim($governorate),
            'place_country_code' => $country,
            'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
            'lng' => isset($row['lon']) ? (float) $row['lon'] : null,
        ];
    }
}
