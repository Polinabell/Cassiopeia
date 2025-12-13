<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Support\ApiResponse;
use App\Services\AstronomyService;

class AstroController extends Controller
{
    public function events(Request $r, AstronomyService $astro)
    {
        $lat  = (float) $r->query('lat', 55.7558);
        $lon  = (float) $r->query('lon', 37.6176);
        $days = max(1, min(366, (int) $r->query('days', 7)));
        $body = (string) $r->query('body', env('ASTRO_BODY', 'sun'));

        $resp = $astro->events($body, $lat, $lon, $days);
        if (($resp['ok'] ?? false) === false) {
            $err = $resp['error'] ?? ['code' => 'UPSTREAM', 'message' => 'unknown'];
            return ApiResponse::error($err['code'] ?? 'UPSTREAM', $err['message'] ?? 'upstream error');
        }
        return ApiResponse::ok($resp['data'] ?? []);
    }

    public function positions(Request $r, AstronomyService $astro)
    {
        $lat  = (float) $r->query('lat', 55.7558);
        $lon  = (float) $r->query('lon', 37.6176);
        $days = max(1, min(366, (int) $r->query('days', 7)));

        $resp = $astro->positions($lat, $lon, $days);
        if (($resp['ok'] ?? false) === false) {
            $err = $resp['error'] ?? ['code' => 'UPSTREAM', 'message' => 'unknown'];
            return ApiResponse::error($err['code'] ?? 'UPSTREAM', $err['message'] ?? 'upstream error');
        }
        return ApiResponse::ok($resp['data'] ?? []);
    }

    /**
     * Render the Astro page.
     */
    public function page()
    {
        return view('astro');
    }
}
