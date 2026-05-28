<?php

namespace App\Services;

use App\Models\AppTrace;
use App\Models\Setting;

class TraceService
{
    public static function error(string $message, array $context = []): void
    {
        if ((int) Setting::getCached('trace_level', '1') < AppTrace::LEVEL_ERROR) return;
        self::write(AppTrace::LEVEL_ERROR, $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        if ((int) Setting::getCached('trace_level', '1') < AppTrace::LEVEL_WARNING) return;
        self::write(AppTrace::LEVEL_WARNING, $message, $context);
    }

    public static function currentLevel(): int
    {
        return (int) Setting::getCached('trace_level', '1');
    }

    private static function write(int $level, string $message, array $context): void
    {
        try {
            AppTrace::create([
                'level'   => $level,
                'message' => mb_substr($message, 0, 500),
                'context' => $context ?: null,
            ]);
        } catch (\Throwable) {
            // Never let trace writing break the application
        }
    }
}
