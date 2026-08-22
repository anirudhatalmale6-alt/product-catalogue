<?php
/**
 * Thin PDO wrapper. One connection per request, opened lazily.
 *
 * Every query in this project goes through here with bound parameters -
 * there is no string concatenation of user input into SQL anywhere.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static array $cfg = [];

    public static function configure(array $cfg): void
    {
        self::$cfg = $cfg;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $c = self::$cfg;
        $charset = $c['charset'] ?? 'utf8mb4';

        if (!empty($c['socket'])) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s',
                $c['socket'], $c['name'], $charset);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'], (int)($c['port'] ?? 3306), $c['name'], $charset);
        }

        try {
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            // Never leak credentials or the DSN to the browser.
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('<h1>Database unavailable</h1><p>The catalogue could not reach '
               . 'its database. Check app/config.php.</p>');
        }

        return self::$pdo;
    }

    /** Run a query and return the statement. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** First column of the first row, or null. */
    public static function scalar(string $sql, array $params = [])
    {
        $val = self::run($sql, $params)->fetchColumn();
        return $val === false ? null : $val;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function begin(): void    { self::pdo()->beginTransaction(); }
    public static function commit(): void   { self::pdo()->commit(); }
    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }
}
