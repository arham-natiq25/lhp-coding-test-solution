<?php

namespace App\Support;

class EventLocationResolver
{
    private const FILTER_DEGREES = 0.75;

    private const MIN_FUZZY_TOKEN_LENGTH = 4;

    /**
     * @var array<int, array{city: string, country: string, region: string, timezone: string, latitude: float, longitude: float}>
     */
    private const ANCHORS = [
        ['city' => 'New York', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/New_York', 'latitude' => 40.7128, 'longitude' => -74.0060],
        ['city' => 'Los Angeles', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Los_Angeles', 'latitude' => 34.0522, 'longitude' => -118.2437],
        ['city' => 'Chicago', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Chicago', 'latitude' => 41.8781, 'longitude' => -87.6298],
        ['city' => 'Houston', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Chicago', 'latitude' => 29.7604, 'longitude' => -95.3698],
        ['city' => 'Phoenix', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Phoenix', 'latitude' => 33.4484, 'longitude' => -112.0740],
        ['city' => 'Philadelphia', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/New_York', 'latitude' => 39.9526, 'longitude' => -75.1652],
        ['city' => 'San Antonio', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Chicago', 'latitude' => 29.4241, 'longitude' => -98.4936],
        ['city' => 'San Diego', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Los_Angeles', 'latitude' => 32.7157, 'longitude' => -117.1611],
        ['city' => 'Dallas', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Chicago', 'latitude' => 32.7767, 'longitude' => -96.7970],
        ['city' => 'San Jose', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Los_Angeles', 'latitude' => 37.3382, 'longitude' => -121.8863],
        ['city' => 'Austin', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Chicago', 'latitude' => 30.2672, 'longitude' => -97.7431],
        ['city' => 'San Francisco', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Los_Angeles', 'latitude' => 37.7749, 'longitude' => -122.4194],
        ['city' => 'Seattle', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Los_Angeles', 'latitude' => 47.6062, 'longitude' => -122.3321],
        ['city' => 'Denver', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Denver', 'latitude' => 39.7392, 'longitude' => -104.9903],
        ['city' => 'Boston', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/New_York', 'latitude' => 42.3601, 'longitude' => -71.0589],
        ['city' => 'Las Vegas', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Los_Angeles', 'latitude' => 36.1699, 'longitude' => -115.1398],
        ['city' => 'Miami', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/New_York', 'latitude' => 25.7617, 'longitude' => -80.1918],
        ['city' => 'Atlanta', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/New_York', 'latitude' => 33.7490, 'longitude' => -84.3880],
        ['city' => 'Washington', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/New_York', 'latitude' => 38.9072, 'longitude' => -77.0369],
        ['city' => 'Nashville', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Chicago', 'latitude' => 36.1627, 'longitude' => -86.7816],
        ['city' => 'Portland', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Los_Angeles', 'latitude' => 45.5152, 'longitude' => -122.6784],
        ['city' => 'New Orleans', 'country' => 'United States', 'region' => 'North America', 'timezone' => 'America/Chicago', 'latitude' => 29.9511, 'longitude' => -90.0715],
        ['city' => 'Toronto', 'country' => 'Canada', 'region' => 'North America', 'timezone' => 'America/Toronto', 'latitude' => 43.6532, 'longitude' => -79.3832],
        ['city' => 'Montreal', 'country' => 'Canada', 'region' => 'North America', 'timezone' => 'America/Toronto', 'latitude' => 45.5019, 'longitude' => -73.5674],
        ['city' => 'Vancouver', 'country' => 'Canada', 'region' => 'North America', 'timezone' => 'America/Vancouver', 'latitude' => 49.2827, 'longitude' => -123.1207],
        ['city' => 'Calgary', 'country' => 'Canada', 'region' => 'North America', 'timezone' => 'America/Edmonton', 'latitude' => 51.0447, 'longitude' => -114.0719],
        ['city' => 'Ottawa', 'country' => 'Canada', 'region' => 'North America', 'timezone' => 'America/Toronto', 'latitude' => 45.4215, 'longitude' => -75.6972],
        ['city' => 'Edmonton', 'country' => 'Canada', 'region' => 'North America', 'timezone' => 'America/Edmonton', 'latitude' => 53.5461, 'longitude' => -113.4938],
        ['city' => 'Quebec City', 'country' => 'Canada', 'region' => 'North America', 'timezone' => 'America/Toronto', 'latitude' => 46.8139, 'longitude' => -71.2080],
        ['city' => 'Winnipeg', 'country' => 'Canada', 'region' => 'North America', 'timezone' => 'America/Winnipeg', 'latitude' => 49.8951, 'longitude' => -97.1384],
        ['city' => 'Mexico City', 'country' => 'Mexico', 'region' => 'North America', 'timezone' => 'America/Mexico_City', 'latitude' => 19.4326, 'longitude' => -99.1332],
        ['city' => 'Guadalajara', 'country' => 'Mexico', 'region' => 'North America', 'timezone' => 'America/Mexico_City', 'latitude' => 20.6597, 'longitude' => -103.3496],
        ['city' => 'Monterrey', 'country' => 'Mexico', 'region' => 'North America', 'timezone' => 'America/Monterrey', 'latitude' => 25.6866, 'longitude' => -100.3161],
        ['city' => 'Puebla', 'country' => 'Mexico', 'region' => 'North America', 'timezone' => 'America/Mexico_City', 'latitude' => 19.0414, 'longitude' => -98.2063],
        ['city' => 'Tijuana', 'country' => 'Mexico', 'region' => 'North America', 'timezone' => 'America/Tijuana', 'latitude' => 32.5149, 'longitude' => -117.0382],
        ['city' => 'Cancun', 'country' => 'Mexico', 'region' => 'North America', 'timezone' => 'America/Cancun', 'latitude' => 21.1619, 'longitude' => -86.8515],
        ['city' => 'Merida', 'country' => 'Mexico', 'region' => 'North America', 'timezone' => 'America/Merida', 'latitude' => 20.9674, 'longitude' => -89.5926],
        ['city' => 'London', 'country' => 'United Kingdom', 'region' => 'Europe', 'timezone' => 'Europe/London', 'latitude' => 51.5074, 'longitude' => -0.1278],
        ['city' => 'Paris', 'country' => 'France', 'region' => 'Europe', 'timezone' => 'Europe/Paris', 'latitude' => 48.8566, 'longitude' => 2.3522],
        ['city' => 'Berlin', 'country' => 'Germany', 'region' => 'Europe', 'timezone' => 'Europe/Berlin', 'latitude' => 52.5200, 'longitude' => 13.4050],
        ['city' => 'Madrid', 'country' => 'Spain', 'region' => 'Europe', 'timezone' => 'Europe/Madrid', 'latitude' => 40.4168, 'longitude' => -3.7038],
        ['city' => 'Rome', 'country' => 'Italy', 'region' => 'Europe', 'timezone' => 'Europe/Rome', 'latitude' => 41.9028, 'longitude' => 12.4964],
        ['city' => 'Amsterdam', 'country' => 'Netherlands', 'region' => 'Europe', 'timezone' => 'Europe/Amsterdam', 'latitude' => 52.3676, 'longitude' => 4.9041],
        ['city' => 'Barcelona', 'country' => 'Spain', 'region' => 'Europe', 'timezone' => 'Europe/Madrid', 'latitude' => 41.3851, 'longitude' => 2.1734],
        ['city' => 'Munich', 'country' => 'Germany', 'region' => 'Europe', 'timezone' => 'Europe/Berlin', 'latitude' => 48.1351, 'longitude' => 11.5820],
        ['city' => 'Milan', 'country' => 'Italy', 'region' => 'Europe', 'timezone' => 'Europe/Rome', 'latitude' => 45.4642, 'longitude' => 9.1900],
        ['city' => 'Vienna', 'country' => 'Austria', 'region' => 'Europe', 'timezone' => 'Europe/Vienna', 'latitude' => 48.2082, 'longitude' => 16.3738],
        ['city' => 'Prague', 'country' => 'Czechia', 'region' => 'Europe', 'timezone' => 'Europe/Prague', 'latitude' => 50.0755, 'longitude' => 14.4378],
        ['city' => 'Lisbon', 'country' => 'Portugal', 'region' => 'Europe', 'timezone' => 'Europe/Lisbon', 'latitude' => 38.7223, 'longitude' => -9.1393],
        ['city' => 'Dublin', 'country' => 'Ireland', 'region' => 'Europe', 'timezone' => 'Europe/Dublin', 'latitude' => 53.3498, 'longitude' => -6.2603],
        ['city' => 'Copenhagen', 'country' => 'Denmark', 'region' => 'Europe', 'timezone' => 'Europe/Copenhagen', 'latitude' => 55.6761, 'longitude' => 12.5683],
        ['city' => 'Stockholm', 'country' => 'Sweden', 'region' => 'Europe', 'timezone' => 'Europe/Stockholm', 'latitude' => 59.3293, 'longitude' => 18.0686],
        ['city' => 'Oslo', 'country' => 'Norway', 'region' => 'Europe', 'timezone' => 'Europe/Oslo', 'latitude' => 59.9139, 'longitude' => 10.7522],
        ['city' => 'Helsinki', 'country' => 'Finland', 'region' => 'Europe', 'timezone' => 'Europe/Helsinki', 'latitude' => 60.1699, 'longitude' => 24.9384],
        ['city' => 'Brussels', 'country' => 'Belgium', 'region' => 'Europe', 'timezone' => 'Europe/Brussels', 'latitude' => 50.8503, 'longitude' => 4.3517],
        ['city' => 'Zurich', 'country' => 'Switzerland', 'region' => 'Europe', 'timezone' => 'Europe/Zurich', 'latitude' => 47.3769, 'longitude' => 8.5417],
        ['city' => 'Warsaw', 'country' => 'Poland', 'region' => 'Europe', 'timezone' => 'Europe/Warsaw', 'latitude' => 52.2297, 'longitude' => 21.0122],
        ['city' => 'Budapest', 'country' => 'Hungary', 'region' => 'Europe', 'timezone' => 'Europe/Budapest', 'latitude' => 47.4979, 'longitude' => 19.0402],
        ['city' => 'Athens', 'country' => 'Greece', 'region' => 'Europe', 'timezone' => 'Europe/Athens', 'latitude' => 37.9838, 'longitude' => 23.7275],
        ['city' => 'Lyon', 'country' => 'France', 'region' => 'Europe', 'timezone' => 'Europe/Paris', 'latitude' => 45.7640, 'longitude' => 4.8357],
        ['city' => 'Hamburg', 'country' => 'Germany', 'region' => 'Europe', 'timezone' => 'Europe/Berlin', 'latitude' => 53.5511, 'longitude' => 9.9937],
        ['city' => 'Manchester', 'country' => 'United Kingdom', 'region' => 'Europe', 'timezone' => 'Europe/London', 'latitude' => 53.4808, 'longitude' => -2.2426],
        ['city' => 'Edinburgh', 'country' => 'United Kingdom', 'region' => 'Europe', 'timezone' => 'Europe/London', 'latitude' => 55.9533, 'longitude' => -3.1883],
        ['city' => 'Frankfurt', 'country' => 'Germany', 'region' => 'Europe', 'timezone' => 'Europe/Berlin', 'latitude' => 50.1109, 'longitude' => 8.6821],
        ['city' => 'Krakow', 'country' => 'Poland', 'region' => 'Europe', 'timezone' => 'Europe/Warsaw', 'latitude' => 50.0647, 'longitude' => 19.9450],
        ['city' => 'Porto', 'country' => 'Portugal', 'region' => 'Europe', 'timezone' => 'Europe/Lisbon', 'latitude' => 41.1579, 'longitude' => -8.6291],
        ['city' => 'Naples', 'country' => 'Italy', 'region' => 'Europe', 'timezone' => 'Europe/Rome', 'latitude' => 40.8518, 'longitude' => 14.2681],
        ['city' => 'Tokyo', 'country' => 'Japan', 'region' => 'Asia Pacific', 'timezone' => 'Asia/Tokyo', 'latitude' => 35.6762, 'longitude' => 139.6503],
        ['city' => 'Seoul', 'country' => 'South Korea', 'region' => 'Asia Pacific', 'timezone' => 'Asia/Seoul', 'latitude' => 37.5665, 'longitude' => 126.9780],
        ['city' => 'Singapore', 'country' => 'Singapore', 'region' => 'Asia Pacific', 'timezone' => 'Asia/Singapore', 'latitude' => 1.3521, 'longitude' => 103.8198],
        ['city' => 'Sydney', 'country' => 'Australia', 'region' => 'Asia Pacific', 'timezone' => 'Australia/Sydney', 'latitude' => -33.8688, 'longitude' => 151.2093],
        ['city' => 'Melbourne', 'country' => 'Australia', 'region' => 'Asia Pacific', 'timezone' => 'Australia/Melbourne', 'latitude' => -37.8136, 'longitude' => 144.9631],
        ['city' => 'Dubai', 'country' => 'United Arab Emirates', 'region' => 'Middle East', 'timezone' => 'Asia/Dubai', 'latitude' => 25.2048, 'longitude' => 55.2708],
        ['city' => 'Sao Paulo', 'country' => 'Brazil', 'region' => 'South America', 'timezone' => 'America/Sao_Paulo', 'latitude' => -23.5505, 'longitude' => -46.6333],
        ['city' => 'Buenos Aires', 'country' => 'Argentina', 'region' => 'South America', 'timezone' => 'America/Argentina/Buenos_Aires', 'latitude' => -34.6037, 'longitude' => -58.3816],
    ];

    /**
     * @return array{label: string, city: string|null, country: string|null, region: string|null, timezone: string, distance_km: float|null}
     */
    public function resolve(?float $latitude, ?float $longitude): array
    {
        if ($latitude === null || $longitude === null) {
            return [
                'label' => 'Location to be announced',
                'city' => null,
                'country' => null,
                'region' => null,
                'timezone' => 'UTC',
                'distance_km' => null,
            ];
        }

        $nearest = null;
        $nearestDistance = null;

        foreach (self::ANCHORS as $anchor) {
            $distance = $this->distance($latitude, $longitude, $anchor['latitude'], $anchor['longitude']);

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearest = $anchor;
                $nearestDistance = $distance;
            }
        }

        return [
            'label' => "{$nearest['city']}, {$nearest['country']}",
            'city' => $nearest['city'],
            'country' => $nearest['country'],
            'region' => $nearest['region'],
            'timezone' => $nearest['timezone'],
            'distance_km' => round($nearestDistance ?? 0, 1),
        ];
    }

    /**
     * @return array<int, array{city: string, country: string, region: string, timezone: string, latitude: float, longitude: float, latitude_min: float, latitude_max: float, longitude_min: float, longitude_max: float}>
     */
    public function search(string $query): array
    {
        $query = $this->normalize($query);

        if ($query === '') {
            return [];
        }

        return collect(self::ANCHORS)
            ->filter(fn (array $anchor): bool => $this->matchesAnchor($anchor, $query))
            ->map(fn (array $anchor): array => [
                ...$anchor,
                'latitude_min' => $anchor['latitude'] - self::FILTER_DEGREES,
                'latitude_max' => $anchor['latitude'] + self::FILTER_DEGREES,
                'longitude_min' => $anchor['longitude'] - self::FILTER_DEGREES,
                'longitude_max' => $anchor['longitude'] + self::FILTER_DEGREES,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{city: string, country: string, region: string, timezone: string, latitude: float, longitude: float}  $anchor
     */
    private function matchesAnchor(array $anchor, string $query): bool
    {
        $location = $this->normalize("{$anchor['city']} {$anchor['country']} {$anchor['region']}");

        if (str_contains($location, $query)) {
            return true;
        }

        $queryTokens = $this->tokens($query);
        $locationTokens = $this->tokens($location);

        foreach ($queryTokens as $token) {
            if (! $this->matchesToken($token, $location, $locationTokens)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $locationTokens
     */
    private function matchesToken(string $token, string $location, array $locationTokens): bool
    {
        if (str_contains($location, $token)) {
            return true;
        }

        if (strlen($token) < self::MIN_FUZZY_TOKEN_LENGTH) {
            return false;
        }

        foreach ($locationTokens as $candidate) {
            if ($candidate === $token) {
                return true;
            }

            if ($candidate[0] !== $token[0]) {
                continue;
            }

            $distance = levenshtein($token, $candidate);
            $maxDistance = strlen($token) >= 7 ? 2 : 1;

            if ($distance <= $maxDistance) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $value): array
    {
        return array_values(array_filter(explode(' ', $value)));
    }

    private function normalize(string $value): string
    {
        return (string) str($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish();
    }

    /**
     * @return array<int, string>
     */
    public function suggestions(): array
    {
        return collect(self::ANCHORS)
            ->flatMap(fn (array $anchor): array => [
                "{$anchor['city']}, {$anchor['country']}",
                $anchor['country'],
                $anchor['region'],
            ])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function distance(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($toLatitude - $fromLatitude);
        $lonDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($lonDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
