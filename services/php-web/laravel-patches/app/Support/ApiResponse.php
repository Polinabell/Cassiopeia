<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class ApiResponse
{
    public static function ok(mixed $data = null): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $data,
            'error' => null,
        ], 200);
    }

    public static function error(string $code, string $message, int $httpCode = 500): JsonResponse
    {
        $trace = (string) Str::uuid();
        return response()->json([
            'ok' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'trace_id' => $trace,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}

