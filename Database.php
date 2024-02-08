<?php

namespace database;

use PDO;

class Database
{
    private PDO $pdo;

    /**
     * Constructor to initialize the PDO connection.
     */
    public function __construct()
    {
        $dsn      = "mysql:dbname=" . getenv("DB_NAME") . ";host=" . getenv("DB_HOST");
        $dbUser   = getenv("DB_USER");
        $dbUserPw = getenv("DB_PASS");

        $this->pdo = new PDO($dsn, $dbUser, $dbUserPw);
    }

    /**
     * Retrieves the PDO connection object.
     *
     * @return PDO The PDO connection object.
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Fetches all books from the database.
     *
     * @return array An associative array of user data.
     */
    public function getAllBooks(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM book");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Performs a SELECT query on a specified table with optional left join.
     *
     * @param string $table The name of the table to query.
     * @param array $columns Optional array of columns to select.
     * @param string $where Optional WHERE clause.
     * @param int $limit Optional limit for the number of rows to fetch.
     * @param array $leftJoin Optional array defining left join tables and conditions.
     * @return array An associative array of the query result.
     */
    public function select(string $table, array $columns = [], string $where = '', int $limit = 0, array $leftJoin = []): array
    {
        if (empty($columns)) {
            $columns = '*';
        } else {
            $columns = implode(',', $columns);
        }

        // Constructing LEFT JOIN statements if provided
        $leftJoinStatements = '';
        foreach ($leftJoin as $joinTable => $joinCondition) {
            $leftJoinStatements .= " LEFT JOIN {$joinTable} ON {$joinCondition}";
        }

        if (!empty($where)) {
            $where = "WHERE " . $where;
        }

        if ($limit > 0) {
            $limit = "LIMIT {$limit}";
        } else {
            $limit = "";
        }

        $query = "SELECT {$columns} FROM {$table} {$leftJoinStatements} {$where} {$limit}";
        $stmt  = $this->pdo->query($query);

        if (!empty($limit)) {
            $results = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!empty($results)) {
            return $results;
        }

        return [];
    }

    /**
     * Inserts data into a specified table.
     *
     * @param string $table The name of the table.
     * @param array $data Associative array of data to insert (column => value).
     * @return bool True on success, false on failure.
     */
    public function insert(string $table, array $data): bool
    {
        $columns      = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");

        foreach ($data as $key => &$value) {
            $stmt->bindParam(':' . $key, $value);
        }

        return $stmt->execute();
    }

    /**
     * Deletes records from a specified table based on conditions.
     *
     * @param string $table The name of the table.
     * @param array $conditions Associative array of conditions (column => value).
     * @return bool True on successful deletion, false on failure.
     */
    public function delete(string $table, array $conditions): bool
    {
        $whereClauses = array_map(function($key) {
            return "$key = :$key";
        }, array_keys($conditions));

        $where = implode(' AND ', $whereClauses);
        $stmt  = $this->pdo->prepare("DELETE FROM {$table} WHERE {$where}");

        foreach ($conditions as $key => &$value) {
            $stmt->bindParam(':' . $key, $value);
        }

        return $stmt->execute();
    }

    /**
     * Updates records in a specified table based on conditions.
     *
     * @param  string $table      The name of the table.
     * @param  array  $data       Associative array of data to update (column => value).
     * @param  array  $conditions Associative array of conditions (column => value).
     * @return bool               True on successful update, false on failure.
     */
    public function update(string $table, array $data, array $conditions): bool
    {
        $setClauses = array_map(function ($key) {
            return "$key = :$key";
        }, array_keys($data));

        $sets = implode(', ', $setClauses);

        $whereClauses = array_map(function ($key) {
            return "$key = :$key";
        }, array_keys($conditions));

        $where = implode(' AND ', $whereClauses);

        $stmt = $this->pdo->prepare("UPDATE {$table} SET {$sets} WHERE {$where}");

        foreach ($data as $key => &$value) {
            $stmt->bindParam(':' . $key, $value);
        }

        foreach ($conditions as $key => &$value) {
            $stmt->bindParam(':' . $key, $value);
        }

        return $stmt->execute();
    }

    /**
     * Performs a SELECT query on a specified table.
     *
     * @param string $table The name of the table to query.
     * @param array $columns Optional array of columns to select.
     * @param array $in ids
     * @return array An associative array of the query result.
     */
    public function selectWithinIds(string $table, array $columns = [], array $in = []): array
    {
        if (empty($columns)) {
            $columns = '*';
        } else {
            $columns = implode(',', $columns);
        }

        if (!empty($in)) {
            $where = " WHERE id IN " . "(" . implode(',', $in) . ")";
        } else {
            $where = '';
        }

        $query = "SELECT {$columns} FROM {$table} {$where}";

        $stmt = $this->pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Selects a user by email.
     *
     * @param string $email The email of the user to find.
     * @return array An associative array of user data or an empty array if not found.
     */
    public function selectUserByEmail(string $email): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Selects data from a target table through a pivot table.
     *
     * @param  string $pivotTable    The name of the pivot table.
     * @param  string $targetTable   The name of the target table.
     * @param  array  $targetColumns Optional array of columns to select from the target table.
     * @param  string $pivotColumn   The column name in the pivot table.
     * @param  mixed  $pivotValue    The value in the pivot column to filter by.
     * @return array                 An associative array of the query result.
     */
    public function selectThroughPivot(string $pivotTable, string $targetTable, string $pivotColumn, $pivotValue, array $targetColumns = []): array
    {
        if (empty($targetColumns)) {
            $targetColumns = '*';
        } else {
            $targetColumns = implode(',', $targetColumns);
        }

        $query = "SELECT {$targetColumns} FROM {$targetTable}
                  JOIN {$pivotTable} ON {$targetTable}.id = {$pivotTable}.{$pivotColumn}
                  WHERE {$pivotTable}.{$pivotColumn} = :pivotValue";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':pivotValue', $pivotValue);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deletes all records from a specified table.
     *
     * @param string $table The name of the table.
     * @return bool True on successful deletion, false on failure.
     */
    public function deleteAll(string $table): bool
    {
        // truncate is used for this purpose
        $stmt = $this->pdo->prepare("TRUNCATE {$table}");
        return $stmt->execute();
    }

}