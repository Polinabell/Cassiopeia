<?php

namespace App\Http\Controllers;

use App\Services\RustApiService;
use App\Support\ApiResponse;

class ProxyController extends Controller
{
    public function last(RustApiService $svc)  { return $this->pipe($svc, '/last'); }

    public function trend(RustApiService $svc) {
        $q = request()->getQueryString();
        return $this->pipe($svc, '/iss/trend' . ($q ? '?' . $q : ''));
    }

    private function pipe(RustApiService $svc, string $path)
    {
        $json = $svc->get($path);
        if (!is_array($json)) {
            return ApiResponse::error('UPSTREAM', 'rust_iss upstream error');
        }
        if (($json['ok'] ?? false) === false && isset($json['error'])) {
            return response()->json($json, 200);
        }
        return response()->json($json, 200);
    }
}
