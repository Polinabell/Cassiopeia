<?php

namespace App\Services;

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Http;

class RustApiService
{
    private string $base;

    public function __construct()
    {
        $this->base = rtrim(env('RUST_BASE', 'http://rust_iss:3000'), '/');
    }

    public function get(string $path): array
    {
        $url = $this->base . '/' . ltrim($path, '/');
        $resp = Http::timeout(5)
            ->retry(2, 200)
            ->withHeaders(['User-Agent' => 'php-web/1.0'])
            ->get($url);

        if ($resp->failed()) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'UPSTREAM',
                    'message' => 'rust_iss upstream failed ' . $resp->status(),
                    'trace_id' => (string) \Illuminate\Support\Str::uuid(),
                ],
            ];
        }

        $json = $resp->json();
        if (!is_array($json)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'BAD_JSON',
                    'message' => 'rust_iss returned invalid json',
                    'trace_id' => (string) \Illuminate\Support\Str::uuid(),
                ],
            ];
        }
        return $json;
    }
}

