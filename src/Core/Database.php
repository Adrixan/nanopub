<?php

declare(strict_types=1);

namespace NanoPub\Core;

use Generator;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * PDO singleton wrapper with memory-optimized features.
 * 
 * Supports unbuffered queries for memory-efficient iteration
 * over large result sets.
 */
final class Database
{
    /**
     * Singleton PDO instance.
     */
    private static ?PDO $connection = null;

    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct() {}

    /**
     * Get the PDO connection instance.
     * 
     * @return PDO
     * @throws RuntimeException If connection fails
     */
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = Config::get('database');
        
        if (empty($config['host']) || empty($config['name'])) {
            throw new RuntimeException('Database configuration is incomplete');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['name'],
            $config['charset'] ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // Unbuffered queries for memory efficiency
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
                "SET NAMES %s COLLATE %s",
                $config['charset'] ?? 'utf8mb4',
                $config['collation'] ?? 'utf8mb4_unicode_ci'
            ),
        ];

        try {
            self::$connection = new PDO(
                $dsn,
                $config['user'] ?? '',
                $config['password'] ?? '',
                $options
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        return self::$connection;
    }

    /**
     * Execute a prepared statement and return the result.
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement
     * @throws RuntimeException If query fails
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::getConnection();

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Query execution failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Execute a query and yield results as a generator.
     * 
     * Memory-efficient iteration over large result sets.
     * Uses unbuffered queries to avoid loading all rows into memory.
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return Generator<int, array, mixed, void>
     */
    public static function fetchGenerator(string $sql, array $params = []): Generator
    {
        $stmt = self::query($sql, $params);

        try {
            while ($row = $stmt->fetch()) {
                yield $row;
            }
        } finally {
            // Ensure statement is closed to free unbuffered query
            $stmt->closeCursor();
        }
    }

    /**
     * Fetch a single row from a query.
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        $stmt->closeCursor();
        
        return $result ?: null;
    }

    /**
     * Fetch all rows from a query.
     * 
     * Warning: This loads all rows into memory. Use fetchGenerator()
     * for large result sets.
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array<int, array>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        // Temporarily enable buffering for fetchAll
        $pdo = self::getConnection();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        
        try {
            $stmt = self::query($sql, $params);
            $result = $stmt->fetchAll();
            $stmt->closeCursor();
            return $result;
        } finally {
            // Restore unbuffered mode
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }
    }

    /**
     * Execute a statement and return affected row count.
     * 
     * @param string $sql SQL statement with placeholders
     * @param array $params Parameters to bind
     * @return int Number of affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::query($sql, $params);
        $count = $stmt->rowCount();
        $stmt->closeCursor();
        
        return $count;
    }

    /**
     * Get the last inserted ID.
     * 
     * @return string
     */
    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }

    /**
     * Begin a transaction.
     */
    public static function beginTransaction(): void
    {
        self::getConnection()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public static function commit(): void
    {
        self::getConnection()->commit();
    }

    /**
     * Rollback the current transaction.
     */
    public static function rollback(): void
    {
        self::getConnection()->rollBack();
    }

    /**
     * Execute a callback within a transaction.
     * 
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws \Throwable
     */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();
        
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    /**
     * Close the connection.
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }
}