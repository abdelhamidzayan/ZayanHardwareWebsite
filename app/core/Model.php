<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Database;

abstract class Model
{
    protected static function db(): \PDO
    {
        return Database::connection();
    }
}
