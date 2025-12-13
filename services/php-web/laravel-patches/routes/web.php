<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OsdrController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\AstroController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\IssController;
use App\Http\Controllers\TelemetryController;
use App\Http\Controllers\JwstController;

Route::get('/', fn() => redirect('/dashboard'));

// ============ Страницы (каждая бизнес-функция на своей странице) ============
Route::get('/dashboard',  [DashboardController::class, 'index']);
Route::get('/iss',        [IssController::class, 'index']);
Route::get('/osdr',       [OsdrController::class, 'index']);
Route::get('/telemetry',  [TelemetryController::class, 'index']);
Route::get('/jwst',       [JwstController::class, 'index']);
Route::get('/astro',      [AstroController::class, 'page']);

// ============ API: ISS ============
Route::get('/api/iss/last',  [ProxyController::class, 'last']);
Route::get('/api/iss/trend', [ProxyController::class, 'trend']);

// ============ API: JWST ============
Route::get('/api/jwst/feed', [JwstController::class, 'feed']);

// ============ API: Astronomy ============
Route::get('/api/astro/events',    [AstroController::class, 'events']);
Route::get('/api/astro/positions', [AstroController::class, 'positions']);


// ============ Telemetry Downloads ============
Route::get('/telemetry/download/csv',  [TelemetryController::class, 'downloadCsv']);
Route::get('/telemetry/download/xlsx', [TelemetryController::class, 'downloadXlsx']);

// ============ CMS ============
Route::get('/page/{slug}', [CmsController::class, 'page']);
