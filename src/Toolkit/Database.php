<?php

namespace MicroUnit\Toolkit;

use MicroUnit\Exceptions\TestSkippedException;

/**
 * A small PDO wrapper for integration tests.
 *
 *     $tester->fixture('db', fn() => Database::sqlite('tests/schema.sql'));
 *     $tester->fixture('db', fn() => Database::connect(getenv('DB_DSN'), 'app', 'secret')->beginTransaction(),
 *         cleanup: fn(Database $db) => $db->rollBack());
 *
 * If the database cannot be reached the test is skipped instead of failing, so a missing service
 * does not turn the whole run red.
 */
final readonly class Database
{
    private function __construct(
        public \PDO $pdo,
    ) {}

    /** A SQLite database (in memory by default), optionally initialised from an SQL file. */
    public static function sqlite(?string $schemaFile = null, string $path = ':memory:'): self
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new TestSkippedException('The pdo_sqlite extension is not installed.');
        }

        $database = new self(new \PDO('sqlite:' . $path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]));

        return $schemaFile === null ? $database : $database->runFile($schemaFile);
    }

    /** Connects to any PDO database. An unreachable server skips the test with the connection error. */
    public static function connect(string $dsn, ?string $user = null, ?string $password = null): self
    {
        try {
            return new self(new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]));
        } catch (\PDOException $exception) {
            throw new TestSkippedException("Database not reachable ($dsn): {$exception->getMessage()}");
        }
    }

    /** Runs every statement in an SQL file (e.g. a schema or seed file). */
    public function runFile(string $sqlFile): self
    {
        if (!is_file($sqlFile)) {
            throw new \InvalidArgumentException("SQL file '$sqlFile' does not exist.");
        }
        $this->pdo->exec((string) file_get_contents($sqlFile));

        return $this;
    }

    /**
     * Runs a statement and returns the number of affected rows.
     *
     * @param array<int|string, mixed> $parameters
     */
    public function execute(string $sql, array $parameters = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount();
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        /** @var list<array<string, mixed>> $rows FETCH_ASSOC returns one column => value array per row */
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * The first row, or null when there is none.
     *
     * @param array<int|string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $parameters = []): ?array
    {
        return $this->select($sql, $parameters)[0] ?? null;
    }

    /**
     * Inserts a row and returns its id.
     *
     * @param array<string, mixed> $row column => value
     */
    public function insert(string $table, array $row): string
    {
        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns)),
        );
        $this->execute($sql, $row);

        return (string) $this->pdo->lastInsertId();
    }

    public function count(string $table): int
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM $table");
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function beginTransaction(): self
    {
        $this->pdo->beginTransaction();

        return $this;
    }

    /** Rolls back the open transaction, if there is one, so it is safe to use as a fixture cleanup. */
    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
