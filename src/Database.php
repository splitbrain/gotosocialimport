<?php

namespace App;

use PDO;
use Psr\Log\LoggerInterface;

class Database
{

    protected $pdo;
    protected $dryrun = true;
    protected LoggerInterface $logger;

    public function __construct(string $path, LoggerInterface $logger)
    {

        $this->logger = $logger;

        $dsn = 'sqlite:' . $path;
        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // set WAL journal mode
        $this->pdo->exec('PRAGMA journal_mode = WAL;');
        // enable foreign key constraints
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
    }

    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }

    public function commit()
    {
        $this->pdo->commit();
    }

    public function rollback()
    {
        $this->pdo->rollBack();
    }

    /**
     * Query one single row
     */
    public function queryRecord(string $sql, array $parameters = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        if (is_array($row) && count($row)) return $row;
        return null;
    }

    /**
     * Insert or replace the given data into the table
     */
    public function saveRecord(string $table, array $data): void
    {
        $columns = array_map(function ($column) {
            return '"' . $column . '"';
        }, array_keys($data));
        $values = array_values($data);
        $placeholders = array_pad([], count($columns), '?');





        if ($this->dryrun) {
            $this->logger->notice("creating record in $table:\n" . print_r($data, true));
        } else {
            $sql = 'INSERT INTO "' . $table . '" (' . join(',', $columns) . ') VALUES (' . join(',', $placeholders) . ')';
            $stm = $this->pdo->prepare($sql);
            $stm->execute($values);
            $stm->closeCursor();
        }
    }
}
