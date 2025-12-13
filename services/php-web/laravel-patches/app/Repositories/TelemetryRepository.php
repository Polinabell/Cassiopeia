<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TelemetryRepository
{
    public function latest(int $limit = 20): array
    {
        return DB::table('telemetry_legacy')
            ->select(['recorded_at', 'voltage', 'temp', 'source_file'])
            ->orderByDesc('recorded_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn($row) => [
                'recorded_at' => $row->recorded_at,
                'voltage' => (float) $row->voltage,
                'temp' => (float) $row->temp,
                'source_file' => $row->source_file,
            ])
            ->toArray();
    }
}


