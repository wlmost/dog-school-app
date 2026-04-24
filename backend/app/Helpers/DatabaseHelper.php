<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Facades\Schema;

class DatabaseHelper
{
    /**
     * Get the appropriate case-insensitive LIKE operator for the current database driver.
     * 
     * PostgreSQL: ILIKE
     * MySQL: LIKE (with COLLATE utf8_general_ci, but LIKE is case-insensitive by default)
     * SQLite: LIKE (case-insensitive by default)
     *
     * @return string
     */
    public static function caseInsensitiveLike(): string
    {
        $driver = Schema::getConnection()->getDriverName();
        
        return match ($driver) {
            'pgsql' => 'ILIKE',
            'mysql', 'mariadb', 'sqlite' => 'LIKE',
            default => 'LIKE',
        };
    }
}
