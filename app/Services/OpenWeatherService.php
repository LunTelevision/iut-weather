<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenWeatherService
{
    private const BASE_URL = 'https://api.openweathermap.org';

    /**
     * Find the coordinates of a city from its full name.
     *
     * @return array{name: string, latitude: float, longitude: float}|null
     */
    public function geocode(string $name): ?array
    {
        $key = 'openweather:geo:' . Str::slug($name);

        return Cache::remember($key, now()->addDay(), function () use ($name) {
            $first = $this->client()
                ->get('/geo/1.0/direct', ['q' => $name, 'limit' => 1])
                ->json(0);

            if (! $first) {
                return null;
            }

            return [
                'name' => $first['name'],
                'latitude' => $first['lat'],
                'longitude' => $first['lon'],
            ];
        });
    }

    /**
     * Get the current weather for a position.
     */
    public function current(float $latitude, float $longitude, string $units = 'metric'): array
    {
        $key = sprintf('openweather:weather:%.2f:%.2f:%s', $latitude, $longitude, $units);

        return Cache::remember($key, now()->addMinutes(10), function () use ($latitude, $longitude, $units) {
            return $this->client()
                ->get('/data/2.5/weather', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'units' => $units,
                ])
                ->json();
        });
    }

    /**
     * Get the 5 day / 3 hour forecast for a position.
     */
    public function forecast(float $latitude, float $longitude, string $units = 'metric'): array
    {
        $key = sprintf('openweather:forecast:%.2f:%.2f:%s', $latitude, $longitude, $units);

        return Cache::remember($key, now()->addMinutes(30), function () use ($latitude, $longitude, $units) {
            return $this->client()
                ->get('/data/2.5/forecast', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'units' => $units,
                ])
                ->json();
        });
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withQueryParameters(['appid' => config('services.openweather.key')])
            ->timeout(10)
            ->throw();
    }
}