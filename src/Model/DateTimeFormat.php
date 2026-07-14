<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use DateTimeZone;

/** Utilidad para normalizar las fechas de la BD (DATETIME en UTC) a ISO-8601. */
final class DateTimeFormat
{
    /** Convierte 'YYYY-MM-DD HH:MM:SS' (UTC) a 'YYYY-MM-DDTHH:MM:SSZ'. */
    public static function toIso8601(string $mysqlDateTime): string
    {
        $date = new DateTimeImmutable($mysqlDateTime, new DateTimeZone('UTC'));

        return $date->format('Y-m-d\TH:i:s\Z');
    }
}
