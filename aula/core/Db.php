<?php
defined('AULA') || exit;

/**
 * Acceso a base de datos (SQLite o MySQL) con un SQL común a ambos.
 *
 * En las migraciones se pueden usar estos marcadores:
 *   {PK}          clave primaria autoincremental
 *   {TEXT}        texto largo
 *   {TABLE_OPTS}  opciones de tabla (motor/charset en MySQL)
 */
class Db
{
    public static ?PDO $pdo = null;
    public static string $driver = 'sqlite';

    public static function connect(array $c, string $dataDir): void
    {
        self::$driver = ($c['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (self::$driver === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
            self::$pdo->exec("SET time_zone = '+00:00'");
        } else {
            if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                throw new RuntimeException('El servidor no tiene la extensión pdo_sqlite. Activa SQLite en PHP o configura MySQL en aula/config.local.php.');
            }
            self::$pdo = new PDO('sqlite:' . $dataDir . '/aula.sqlite', null, null, $opts);
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA busy_timeout = 8000');
            self::$pdo->exec('PRAGMA synchronous = NORMAL');
        }
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::$pdo->prepare($sql);
        $st->execute(array_values($params));
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Primer valor de la primera fila. */
    public static function val(string $sql, array $params = [])
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Lista con los valores de la primera columna. */
    public static function col(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        self::q($sql, array_values($data));
        return (int)self::$pdo->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): void
    {
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
        self::q("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $params));
    }

    public static function delete(string $table, string $where, array $params = []): void
    {
        self::q("DELETE FROM $table WHERE $where", $params);
    }

    /** "?, ?, ?" para usar con IN (...). */
    public static function in(array $values): string
    {
        return $values ? implode(', ', array_fill(0, count($values), '?')) : 'NULL';
    }

    public static function tx(callable $fn)
    {
        self::$pdo->beginTransaction();
        try {
            $r = $fn();
            self::$pdo->commit();
            return $r;
        } catch (Throwable $e) {
            self::$pdo->rollBack();
            throw $e;
        }
    }

    public static function ddl(string $sql): void
    {
        $mysql = self::$driver === 'mysql';
        self::$pdo->exec(strtr($sql, [
            '{PK}' => $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
            '{TEXT}' => $mysql ? 'MEDIUMTEXT' : 'TEXT',
            '{TABLE_OPTS}' => $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '',
        ]));
    }

    public static function tableExists(string $table): bool
    {
        if (self::$driver === 'mysql') {
            return (bool)self::val('SHOW TABLES LIKE ?', [$table]);
        }
        return (bool)self::val("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
    }

    /**
     * Ejecuta las migraciones pendientes de un componente (núcleo o plugin).
     * $migrations = [1 => ['CREATE TABLE ...', ...], 2 => [...]]
     */
    public static function migrate(string $component, array $migrations): void
    {
        $key = 'dbver_' . $component;
        $current = self::tableExists('settings') ? (int)Settings::get($key, 0) : 0;
        ksort($migrations);
        foreach ($migrations as $version => $statements) {
            if ($version <= $current) {
                continue;
            }
            foreach ($statements as $sql) {
                if (is_callable($sql)) {
                    $sql();
                } else {
                    self::ddl($sql);
                }
            }
            Settings::set($key, $version);
        }
    }
}
