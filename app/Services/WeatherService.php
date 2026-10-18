<?php

namespace App\Services;

use App\Support\Jalali;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Current weather + 7-day forecast for Maragheh from Open-Meteo (free, no API key). */
class WeatherService
{
    private const CACHE_KEY = 'weather.maragheh';
    private const STALE_KEY = 'weather.maragheh.stale';

    public function get(): ?array
    {
        $minutes = config('maragheh.weather.cache_minutes', 30);

        return Cache::remember(self::CACHE_KEY, now()->addMinutes($minutes), function () {
            try {
                $data = $this->fetch();
                Cache::forever(self::STALE_KEY, $data);

                return $data;
            } catch (Throwable $e) {
                Log::warning('Weather fetch failed: '.$e->getMessage());

                // serve the last good answer rather than nothing
                return Cache::get(self::STALE_KEY);
            }
        });
    }

    private function fetch(): array
    {
        $res = Http::timeout(15)->retry(2, 500)->get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => config('maragheh.weather.lat'),
            'longitude' => config('maragheh.weather.lon'),
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,is_day,weather_code,wind_speed_10m',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,sunrise,sunset',
            'timezone' => 'Asia/Tehran',
            'forecast_days' => 7,
        ])->throw()->json();

        $c = $res['current'];
        $d = $res['daily'];
        $daily = [];
        foreach ($d['time'] as $i => $date) {
            $day = Carbon::parse($date, 'Asia/Tehran');
            [$text, $icon] = self::describe((int) $d['weather_code'][$i]);
            $daily[] = [
                'date' => $date,
                'day_name' => $i === 0 ? 'امروز' : Jalali::weekday($day),
                'date_fa' => Jalali::date($day),
                'code' => (int) $d['weather_code'][$i],
                'text' => $text,
                'icon' => $icon,
                'max' => round((float) $d['temperature_2m_max'][$i], 1),
                'min' => round((float) $d['temperature_2m_min'][$i], 1),
                'rain_chance' => isset($d['precipitation_probability_max'][$i]) ? (int) $d['precipitation_probability_max'][$i] : null,
            ];
        }
        [$text, $icon] = self::describe((int) $c['weather_code']);

        return [
            'current' => [
                'temp' => round((float) $c['temperature_2m'], 1),
                'feels_like' => round((float) $c['apparent_temperature'], 1),
                'humidity' => (int) $c['relative_humidity_2m'],
                'wind' => round((float) $c['wind_speed_10m'], 1),
                'code' => (int) $c['weather_code'],
                'text' => $text,
                'icon' => $icon,
                'is_day' => (bool) $c['is_day'],
            ],
            'daily' => $daily,
            'sunrise' => isset($d['sunrise'][0]) ? substr($d['sunrise'][0], 11, 5) : null,
            'sunset' => isset($d['sunset'][0]) ? substr($d['sunset'][0], 11, 5) : null,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** WMO weather code -> [Persian text, icon key]. */
    public static function describe(int $code): array
    {
        return match (true) {
            $code === 0 => ['صاف', 'clear'],
            $code === 1 => ['عمدتاً صاف', 'clear'],
            $code === 2 => ['نیمه‌ابری', 'partly'],
            $code === 3 => ['ابری', 'cloudy'],
            in_array($code, [45, 48]) => ['مه‌آلود', 'fog'],
            in_array($code, [51, 53, 55, 56, 57]) => ['نم‌نم باران', 'drizzle'],
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82]) => ['بارانی', 'rain'],
            in_array($code, [71, 73, 75, 77, 85, 86]) => ['برفی', 'snow'],
            in_array($code, [95, 96, 99]) => ['رعد و برق', 'storm'],
            default => ['نامشخص', 'cloudy'],
        };
    }
}
