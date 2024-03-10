<?php

namespace database;

use mysql_xdevapi\Exception;
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
        try {
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
        } catch (\PDOException $e) {
            // Handle any exceptions that occur during the query execution
            // For now, simply return an empty array
            return [];
        }
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
     * Inserts multiple rows into a specified table.
     *
     * @param string $table The name of the table.
     * @param array $dataArray Associative array of data to insert (column => value).
     * @return bool True on success, false on failure.
     */
    public function insertMultiple(string $table, array $dataArray): bool // we can make this actually do multiple inserts in one query..
    {
        $columns = implode(',', array_keys($dataArray[0]));
        $query   = "INSERT INTO {$table} ({$columns}) VALUES ";

        $bindValues = [];
        foreach ($dataArray as $index => $data) {
            $isLast =  (count($dataArray) - 1) == $index;

            $query .= "(";
            $i = 0;
            foreach ($data as $key => $value) {
                $uniqueBinding = ":" . $key . $index;
                $isInnerLast   = (count($data) - 1) == $i;

                $isInnerLast ? $query .= $uniqueBinding : $query .= $uniqueBinding . ", ";
                $bindValues[$uniqueBinding] = $value;

                $i += 1;
            }

            $isLast ? $query .= ");" : $query .= "),";
        }

        $stmt = $this->pdo->prepare($query);

        foreach ($bindValues as $boundKey => $boundValue) {
            $stmt->bindValue($boundKey, $boundValue);
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
     * Truncates all records from a specified table.
     *
     * @param  string $table The name of the table.
     * @return bool          True on successful truncation, false on failure.
     */
    public function truncateTable(string $table): bool
    {
        $stmt = $this->pdo->prepare("TRUNCATE TABLE {$table}");
        return $stmt->execute();
    }

    /**
     * Drops a specified table.
     *
     * @param string $table The name of the table to drop.
     * @return bool True on successful table drop, false on failure.
     */
    public function dropTable(string $table): bool
    {
        $stmt = $this->pdo->prepare("DROP TABLE IF EXISTS {$table}");
        return $stmt->execute();
    }

    /**
     * Backs up the specified table by creating a copy of its structure and data.
     *
     * @param  string $tableName       The name of the table to back up.
     * @param  string $backupTableName The name of the backup table.
     * @return bool                    True on successful backup, false on failure.
     */
    public function backup(string $tableName, string $backupTableName): bool {
        $uniqueTableName = $backupTableName . '_backup';

        $query = "CREATE TABLE {$uniqueTableName} LIKE {$tableName}; 
                  INSERT INTO {$uniqueTableName} SELECT * FROM {$tableName};";

        $stmt = $this->pdo->prepare($query);

        return $stmt->execute();
    }

    /**
     * Merges data from one table into another table within the same database,
     * checking for merge conflicts based on a specified column.
     *
     * @param string $sourceTable The name of the source table.
     * @param string $targetTable The name of the target table.
     * @param string|null $mergeColumn (Optional) The column used for merging and checking conflicts.
     * @return bool True on successful merge, false on failure.
     */
    public function mergeTables(string $sourceTable, string $targetTable, ?string $mergeColumn = null): bool
    {
        $sourceData = $this->select($sourceTable);

        $targetData = $this->select($targetTable);

        $indexedTargetData = [];
        if ($mergeColumn !== null) {
            foreach ($targetData as $row) {
                $indexedTargetData[$row[$mergeColumn]] = $row;
            }
        }

        foreach ($sourceData as $sourceRow) {
            if ($mergeColumn !== null && isset($sourceRow[$mergeColumn])) {
                $mergeValue = $sourceRow[$mergeColumn];

                if (isset($indexedTargetData[$mergeValue])) {
                    continue;
                }
            }

            $this->insert($targetTable, $sourceRow);
        }

        return true;
    }

    /**
     * Retrieves column names and mapped data types from a specified table.
     *
     * @param string $table The name of the table.
     * @return array An associative array containing column names as keys and mapped data types as values.
     */
    public function getColumnNamesWithMappedTypes(string $table): array
    {
        $query = "SHOW COLUMNS FROM {$table}";
        $stmt = $this->pdo->query($query);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnsWithMappedTypes = [];
        foreach ($columns as $column) {
            // Extracting data type from column type string
            preg_match('/^([a-z]+)/', $column['Type'], $matches);
            $dataType = $matches[1];

            // Mapping MySQL data types to PHP data types
            $mappedType = match ($dataType) {
                'tinyint'                                => 'tinyint',
                'smallint', 'mediumint', 'int', 'bigint' => 'integer',
                'float', 'double', 'decimal'             => 'float',
                default => 'string',
            };

            $columnsWithMappedTypes[$column['Field']] = $mappedType;
        }

        return $columnsWithMappedTypes;
    }

    /**
     * Creates a table with the specified name and columns.
     *
     * @param string $tableName The name of the table to create.
     * @param array $columns An associative array of column definitions (column name => column type).
     * @return bool True on successful table creation, false on failure.
     */
    public function createTable(string $tableName, array $columns): bool
    {
        try {
            $columnDefinitions = [];
            foreach ($columns as $columnName => $columnType) {
                $columnDefinitions[] = "$columnName $columnType";
            }

            $columnDefinitionsString = implode(', ', $columnDefinitions);

            $query = "CREATE TABLE IF NOT EXISTS {$tableName} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    {$columnDefinitionsString}
                 )";

            $stmt = $this->pdo->prepare($query);
            return $stmt->execute();
        } catch (\PDOException $e) {
            return false;
        }
    }


}