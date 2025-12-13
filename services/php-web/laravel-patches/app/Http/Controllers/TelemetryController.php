<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\TelemetryRepository;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TelemetryController extends Controller
{
    public function index(Request $r, TelemetryRepository $repo)
    {
        $limit = min(500, max(1, (int)$r->query('limit', 100)));
        $sortCol = $r->query('sort', 'recorded_at');
        $sortDir = strtolower($r->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $search = trim((string)$r->query('q', ''));

        $allowedCols = ['id', 'recorded_at', 'voltage', 'temp', 'source_file'];
        if (!in_array($sortCol, $allowedCols, true)) {
            $sortCol = 'recorded_at';
        }

        $query = DB::table('telemetry_legacy');
        if ($search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('source_file', 'ILIKE', "%{$search}%")
                  ->orWhereRaw("CAST(voltage AS TEXT) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("CAST(temp AS TEXT) LIKE ?", ["%{$search}%"]);
            });
        }
        $rows = $query->orderBy($sortCol, $sortDir)->limit($limit)->get()->toArray();

        return view('telemetry', [
            'rows' => array_map(fn($r) => (array)$r, $rows),
            'filter' => [
                'limit' => $limit,
                'sort' => $sortCol,
                'dir' => $sortDir,
                'q' => $search,
            ],
            'columns' => $allowedCols,
        ]);
    }

    /**
     * Download telemetry data as XLSX with proper formatting.
     */
    public function downloadXlsx(Request $r, TelemetryRepository $repo)
    {
        $limit = min(1000, max(1, (int)$r->query('limit', 500)));
        $sortCol = $r->query('sort', 'recorded_at');
        $sortDir = strtolower($r->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedCols = ['id', 'recorded_at', 'voltage', 'temp', 'source_file'];
        if (!in_array($sortCol, $allowedCols, true)) {
            $sortCol = 'recorded_at';
        }

        $rows = DB::table('telemetry_legacy')
            ->orderBy($sortCol, $sortDir)
            ->limit($limit)
            ->get()
            ->toArray();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Telemetry');

        // Header row
        $headers = ['ID', 'Время (UTC)', 'Напряжение (В)', 'Температура (°C)', 'Источник', 'Валидно'];
        foreach ($headers as $col => $h) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $h);
        }
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        // Data rows
        $rowNum = 2;
        foreach ($rows as $row) {
            $r = (array)$row;
            $sheet->setCellValueByColumnAndRow(1, $rowNum, (int)$r['id']);
            // Timestamp formatted as datetime
            $ts = strtotime($r['recorded_at']);
            $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($ts);
            $sheet->setCellValueByColumnAndRow(2, $rowNum, $excelDate);
            $sheet->getStyleByColumnAndRow(2, $rowNum)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_DATE_DATETIME);

            // Numbers
            $sheet->setCellValueByColumnAndRow(3, $rowNum, (float)$r['voltage']);
            $sheet->getStyleByColumnAndRow(3, $rowNum)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

            $sheet->setCellValueByColumnAndRow(4, $rowNum, (float)$r['temp']);
            $sheet->getStyleByColumnAndRow(4, $rowNum)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

            // String
            $sheet->setCellValueByColumnAndRow(5, $rowNum, (string)$r['source_file']);

            // Boolean: valid if voltage in range and temp in range
            $valid = ($r['voltage'] >= 3.0 && $r['voltage'] <= 15.0 && $r['temp'] >= -60 && $r['temp'] <= 100);
            $sheet->setCellValueByColumnAndRow(6, $rowNum, $valid ? 'ИСТИНА' : 'ЛОЖЬ');

            $rowNum++;
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'telemetry_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Download telemetry data as CSV with proper formatting.
     */
    public function downloadCsv(Request $r)
    {
        $limit = min(1000, max(1, (int)$r->query('limit', 500)));

        $rows = DB::table('telemetry_legacy')
            ->orderBy('recorded_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();

        $filename = 'telemetry_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function() use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'recorded_at', 'voltage', 'temp', 'source_file', 'valid'], ';');
            foreach ($rows as $row) {
                $r = (array)$row;
                $valid = ($r['voltage'] >= 3.0 && $r['voltage'] <= 15.0 && $r['temp'] >= -60 && $r['temp'] <= 100);
                fputcsv($out, [
                    (int)$r['id'],
                    $r['recorded_at'],              // timestamp as ISO string
                    number_format((float)$r['voltage'], 2, '.', ''),
                    number_format((float)$r['temp'], 2, '.', ''),
                    $r['source_file'],
                    $valid ? 'ИСТИНА' : 'ЛОЖЬ',
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

