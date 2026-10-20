<?php

namespace GlpiPlugin\Glpiinject;

final class CsvExport
{
    public static function sanitizeCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }
}