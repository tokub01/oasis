<?php

namespace App\Support\Operations;

use Illuminate\Database\Connection;
use PDO;
use RuntimeException;

/**
 * A plain SQL dump of a MySQL database written in PHP, so backups need no mysqldump binary on the server. Every table
 * gets DROP + CREATE; rows follow in INSERT batches, read unbuffered so large tables do not fill the memory. Volatile
 * tables (cache, sessions, queue) are dumped without rows. Statements are separated by STATEMENT_END, which restore()
 * splits on – values may contain semicolons and line breaks.
 */
final class DatabaseDump
{
    public const STATEMENT_END = "\n-- oasis:end-of-statement\n";

    /** Tables whose rows are not worth restoring: they are rebuilt at runtime or hold short-lived secrets. */
    public const SKIP_ROWS = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches'];

    private const BATCH = 200;

    /**
     * Write the dump of $connection to $out and return the row count per table.
     *
     * @param  resource  $out
     * @return array<string, int>
     */
    public static function write(Connection $connection, $out): array
    {
        $pdo = $connection->getPdo();
        $tables = array_map(fn ($row) => array_values((array) $row)[0],
            $connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
        sort($tables);
        fwrite($out, 'SET FOREIGN_KEY_CHECKS=0'.self::STATEMENT_END);
        $counts = [];
        foreach ($tables as $table) {
            $quoted = '`'.str_replace('`', '``', $table).'`';
            $create = array_values((array) $connection->selectOne('SHOW CREATE TABLE '.$quoted))[1];
            fwrite($out, 'DROP TABLE IF EXISTS '.$quoted.self::STATEMENT_END.$create.self::STATEMENT_END);
            $counts[$table] = in_array($table, self::SKIP_ROWS, true) ? 0 : self::rows($pdo, $quoted, $out);
        }
        fwrite($out, 'SET FOREIGN_KEY_CHECKS=1'.self::STATEMENT_END);

        return $counts;
    }

    /**
     * Run a dump read from $in against $connection.
     *
     * @param  resource  $in
     */
    public static function restore(Connection $connection, $in): void
    {
        $pdo = $connection->getPdo();
        $buffer = '';
        while (! feof($in)) {
            $chunk = fread($in, 1 << 20);
            if ($chunk === false) {
                throw new RuntimeException('Der Export ist nicht lesbar.');
            }
            $buffer .= $chunk;
            while (($end = strpos($buffer, self::STATEMENT_END)) !== false) {
                $pdo->exec(substr($buffer, 0, $end));
                $buffer = substr($buffer, $end + strlen(self::STATEMENT_END));
            }
        }
        if (trim($buffer) !== '') {
            throw new RuntimeException('Der Export endet mitten in einer Anweisung.');
        }
    }

    /** @param  resource  $out */
    private static function rows(PDO $pdo, string $quoted, $out): int
    {
        $buffered = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        try {
            $statement = $pdo->query('SELECT * FROM '.$quoted);
            if ($statement === false) {
                throw new RuntimeException('Tabelle '.$quoted.' ist nicht lesbar.');
            }
            $count = 0;
            $batch = [];
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                $batch[] = '('.implode(',', array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), $row)).')';
                $count++;
                if (count($batch) === self::BATCH) {
                    fwrite($out, 'INSERT INTO '.$quoted.' VALUES '.implode(',', $batch).self::STATEMENT_END);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                fwrite($out, 'INSERT INTO '.$quoted.' VALUES '.implode(',', $batch).self::STATEMENT_END);
            }
            $statement->closeCursor();

            return $count;
        } finally {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
        }
    }
}
