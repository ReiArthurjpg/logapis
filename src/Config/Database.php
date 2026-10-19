<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    /**
     * Carrega variáveis do arquivo .env manualmente caso getenv() não tenha carregado
     */
    public static function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);

                // Remove aspas envolventes se houver
                if (
                    (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))
                ) {
                    $val = substr($val, 1, -1);
                }

                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $val;
                    putenv("{$key}={$val}");
                }
            }
        }
    }

    public static function getEnv(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Retorna a instância única da conexão PDO (Singleton Pattern)
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = self::getEnv('DB_HOST', '127.0.0.1');
            $port = self::getEnv('DB_PORT', '3306');
            $db   = self::getEnv('DB_NAME', 'logmov_db');
            $user = self::getEnv('DB_USER', 'root');
            $pass = self::getEnv('DB_PASS', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new RuntimeException("Falha na conexão com o banco de dados: " . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }

        return self::$instance;
    }
}
