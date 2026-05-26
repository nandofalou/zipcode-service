<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use RuntimeException;

final class PdoFactory
{
    public static function create(string $dbPath): PDO
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Não foi possível criar o diretório: {$dir}");
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL;');
        $pdo->exec('PRAGMA synchronous=NORMAL;');
        $pdo->exec('PRAGMA foreign_keys=ON;');

        return $pdo;
    }
}
