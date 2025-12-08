<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AstronomyService
{
    protected string $base = 'https://api.astronomyapi.com/api/v2';

    public function events(string $body, float $lat, float $lon, int $days): array
    {
        $appId = preg_replace('/\s+/', '', (string) env('ASTRO_APP_ID', ''));
        $secret = preg_replace('/\s+/', '', (string) env('ASTRO_APP_SECRET', ''));
        $elevation = (int) env('ASTRO_ELEVATION', 0);

        if ($appId === '' || $secret === '') {
            return $this->error('CONFIG_MISSING', 'ASTRO_APP_ID/ASTRO_APP_SECRET are not set');
        }

        $fromDate = now('UTC')->toDateString();
        $toDate = now('UTC')->addDays($days)->toDateString();
        $time = now('UTC')->format('H:i:s');

        try {
            $headers = [
                'User-Agent' => 'php-web/1.0',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($appId . ':' . $secret),
            ];

            $resp = Http::withHeaders($headers)->get(
                $this->base . '/bodies/events/' . rawurlencode($body),
                [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'elevation' => $elevation,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'time' => $time,
                    'output' => 'rows',
                ]
            );

            if ($resp->successful()) {
                $data = $resp->json();

                // Если событий нет, пробуем получить позиции как запасной канал данных
                $hasEvents = false;
                if (!empty($data['data']['rows'])) {
                    foreach ($data['data']['rows'] as $r) {
                        if (!empty($r['events'])) { $hasEvents = true; break; }
                    }
                }

                if ($hasEvents) {
                    return ['ok' => true, 'data' => $data, 'error' => null];
                }

                // fallback: позиции для указанного тела
                $posResp = Http::withHeaders($headers)->get(
                    $this->base . '/bodies/positions/' . rawurlencode($body),
                    [
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'elevation' => $elevation,
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'time' => $time,
                        'output' => 'rows',
                    ]
                );
                if ($posResp->successful()) {
                    $positions = $posResp->json();
                    $data['data']['positions_rows'] = $positions['data']['rows'] ?? [];
                    $data['data']['fallback'] = 'positions';
                    return ['ok' => true, 'data' => $data, 'error' => null];
                }

                return ['ok' => true, 'data' => $data, 'error' => null];
            }

            return $this->error(
                'UPSTREAM_ASTRO',
                'Astronomy API status ' . $resp->status() . ' body: ' . $resp->body()
            );
        } catch (\Throwable $e) {
            return $this->error('UPSTREAM_ASTRO', $e->getMessage());
        }
    }

    public function positions(float $lat, float $lon, int $days): array
    {
        $appId = preg_replace('/\s+/', '', (string) env('ASTRO_APP_ID', ''));
        $secret = preg_replace('/\s+/', '', (string) env('ASTRO_APP_SECRET', ''));
        $body = preg_replace('/\s+/', '', (string) env('ASTRO_BODY', 'Sun'));
        $bodies = preg_replace('/\s+/', '', (string) env('ASTRO_BODIES', 'sun,moon'));
        $elevation = (int) env('ASTRO_ELEVATION', 0);

        if ($appId === '' || $secret === '') {
            return $this->error('CONFIG_MISSING', 'ASTRO_APP_ID/ASTRO_APP_SECRET are not set');
        }

        // Параметры по доке: body в path, обязательные QS
        $fromDate = now('UTC')->toDateString();
        $toDate = now('UTC')->addDays($days)->toDateString();
        $time = now('UTC')->format('H:i:s');

        try {
            $headers = [
                'User-Agent' => 'php-web/1.0',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($appId . ':' . $secret),
            ];

            $resp = Http::withHeaders($headers)->get($this->base . '/bodies/positions', [
                'latitude' => $lat,
                'longitude' => $lon,
                'elevation' => $elevation,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'time' => $time,
                'output' => 'table',
                'bodies' => $bodies,
            ]);

            if ($resp->successful()) {
                return [
                    'ok' => true,
                    'data' => $resp->json(),
                    'error' => null,
                ];
            }

            return $this->error(
                'UPSTREAM_ASTRO',
                'Astronomy API status ' . $resp->status() . ' body: ' . $resp->body()
            );
        } catch (\Throwable $e) {
            return $this->error('UPSTREAM_ASTRO', $e->getMessage());
        }
    }

    protected function error(string $code, string $message): array
    {
        return [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'trace_id' => (string) Str::uuid(),
            ],
        ];
    }
}
