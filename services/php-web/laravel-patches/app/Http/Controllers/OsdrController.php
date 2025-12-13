<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RustApiService;
use App\Support\ApiResponse;

class OsdrController extends Controller
{
    public function index(Request $request, RustApiService $rust)
    {
        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min(300, $limit));
        $q = trim((string) $request->query('q', ''));
        $sortCol = $request->query('sort', 'inserted_at');
        $sortDir = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedCols = ['id', 'dataset_id', 'title', 'updated_at', 'inserted_at'];
        if (!in_array($sortCol, $allowedCols, true)) {
            $sortCol = 'inserted_at';
        }

        $resp = $rust->get('/osdr/list?limit='.min(500, $limit * 2));
        $error = null;
        if (($resp['ok'] ?? false) === false) {
            $error = $resp['error']['message'] ?? 'upstream error';
        }
        $items = $this->flattenOsdr($resp['data']['items'] ?? []);
        $items = $this->applyFilters($items, $q);
        $items = $this->applySort($items, $sortCol, $sortDir);
        $items = array_slice($items, 0, $limit);

        return view('osdr', [
            'items' => $items,
            'src'   => env('RUST_BASE', 'http://rust_iss:3000').'/osdr/list',
            'error' => $error,
            'filter' => [
                'q' => $q,
                'limit' => $limit,
                'sort' => $sortCol,
                'dir' => $sortDir,
            ],
        ]);
    }

    /** Преобразует данные вида {"OSD-1": {...}, "OSD-2": {...}} в плоский список */
    private function flattenOsdr(array $items): array
    {
        $out = [];
        foreach ($items as $row) {
            $raw = $row['raw'] ?? [];
            if (is_array($raw) && $this->looksOsdrDict($raw)) {
                foreach ($raw as $k => $v) {
                    if (!is_array($v)) continue;
                    $rest = $v['REST_URL'] ?? $v['rest_url'] ?? $v['rest'] ?? null;
                    $title = $v['title'] ?? $v['name'] ?? null;
                    if (!$title && is_string($rest)) {
                        // запасной вариант: последний сегмент URL как подпись
                        $title = basename(rtrim($rest, '/'));
                    }
                    $out[] = [
                        'id'          => $row['id'],
                        'dataset_id'  => $k,
                        'title'       => $title,
                        'status'      => $row['status'] ?? null,
                        'updated_at'  => $row['updated_at'] ?? null,
                        'inserted_at' => $row['inserted_at'] ?? null,
                        'rest_url'    => $rest,
                        'raw'         => $v,
                    ];
                }
            } else {
                // обычная строка — просто прокинем REST_URL если найдётся
                $row['rest_url'] = is_array($raw) ? ($raw['REST_URL'] ?? $raw['rest_url'] ?? null) : null;
                $out[] = $row;
            }
        }
        return $out;
    }

    private function looksOsdrDict(array $raw): bool
    {
        // словарь ключей "OSD-xxx" ИЛИ значения содержат REST_URL
        foreach ($raw as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'OSD-')) return true;
            if (is_array($v) && (isset($v['REST_URL']) || isset($v['rest_url']))) return true;
        }
        return false;
    }

    private function applyFilters(array $items, string $q): array
    {
        $q = mb_strtolower($q);

        return array_values(array_filter($items, function ($it) use ($q) {
            if ($q !== '') {
                $hay = mb_strtolower(($it['dataset_id'] ?? '').' '.($it['title'] ?? ''));
                if (!str_contains($hay, $q)) return false;
            }
            return true;
        }));
    }

    private function applySort(array $items, string $col, string $dir): array
    {
        usort($items, function($a, $b) use ($col, $dir) {
            $va = $a[$col] ?? '';
            $vb = $b[$col] ?? '';
            
            // Try to compare as dates if the column looks like a date
            if (in_array($col, ['updated_at', 'inserted_at'])) {
                $va = strtotime($va) ?: 0;
                $vb = strtotime($vb) ?: 0;
            }
            
            if ($dir === 'asc') {
                return $va <=> $vb;
            }
            return $vb <=> $va;
        });
        
        return $items;
    }
}
