<?php

namespace App\Http\Controllers;

use App\Services\RustApiService;

class IssController extends Controller
{
    public function index(RustApiService $rust)
    {
        $lastJson  = $rust->get('/last');
        $trendJson = $rust->get('/iss/trend');

        return view('iss', [
            'last' => $lastJson['data'] ?? [],
            'trend' => $trendJson['data'] ?? [],
            'base' => env('RUST_BASE', 'http://rust_iss:3000'),
        ]);
    }
}
