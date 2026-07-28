<?php

namespace App\Models\Traits;

trait HasSchemaCache
{
    protected static array $schemaCacheMemo = [];

    public function getTableColumnAndTypeList(): array
    {
        $key = $this->schemaCacheKey();

        if (array_key_exists($key, static::$schemaCacheMemo)) {
            return static::$schemaCacheMemo[$key];
        }

        $cached = cache()->get($key);
        if (!empty($cached)) {
            return static::$schemaCacheMemo[$key] = $cached;
        }

        $columns = $this->fetchTableColumns();

        if (empty($columns)) {
            return $columns;
        }

        cache()->forever($key, $columns);

        return static::$schemaCacheMemo[$key] = $columns;
    }

    public function getFillable(): array
    {
        $fields = parent::getFillable();

        if (empty($fields)) {
            return array_keys($this->getTableColumnAndTypeList());
        }

        $updatedAt = getSystemConfig('updated_at_column.field');
        if ($updatedAt) {
            $fields[] = $updatedAt;
        }

        return $fields;
    }

    protected function schemaCacheKey(): string
    {
        $conn = $this->getConnection();

        return implode(':', [
            'schema',
            $conn->getDatabaseName(),
            $conn->getDriverName(),
            $this->getTable(),
        ]);
    }

    protected function fetchTableColumns(): array
    {
        $conn  = $this->getConnection();
        $table = $this->getTable();

        return match ($conn->getDriverName()) {
            'mysql'   => $this->fetchMysqlColumns($conn, $table),
            'pgsql'   => $this->fetchPgsqlColumns($conn, $table),
            'sqlite'  => $this->fetchSqliteColumns($conn, $table),
            'sqlsrv'  => $this->fetchSqlsrvColumns($conn, $table),
            default   => [],
        };
    }

    protected function fetchMysqlColumns($conn, string $table): array
    {
        $columns = [];

        foreach ($conn->select("describe `{$table}`") as $field) {
            $type = str_contains($field->Type, '(')
                ? substr($field->Type, 0, strpos($field->Type, '('))
                : $field->Type;

            $columns[$field->Field] = $type;
        }

        return $columns;
    }

    protected function fetchPgsqlColumns($conn, string $table): array
    {
        $dbName  = $conn->getDatabaseName();
        $columns = [];

        $sql = "SELECT COLUMN_NAME AS field, data_type AS type
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = '{$table}'
                AND table_catalog = '{$dbName}'";

        foreach ($conn->select($sql) as $field) {
            $type = str_contains($field->type, ' ')
                ? substr($field->type, 0, strpos($field->type, ' '))
                : $field->type;

            $columns[$field->field] = $type;
        }

        return $columns;
    }

    protected function fetchSqliteColumns($conn, string $table): array
    {
        $columns = [];

        foreach ($conn->select("PRAGMA table_info(`{$table}`)") as $field) {
            $type = strtolower($field->type ?: 'text');
            $type = str_contains($type, '(')
                ? substr($type, 0, strpos($type, '('))
                : $type;

            $columns[$field->name] = $type;
        }

        return $columns;
    }

    protected function fetchSqlsrvColumns($conn, string $table): array
    {
        $dbName  = $conn->getDatabaseName();
        $columns = [];

        $sql = "SELECT COLUMN_NAME AS field, DATA_TYPE AS type
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = '{$table}'
                AND TABLE_CATALOG = '{$dbName}'";

        foreach ($conn->select($sql) as $field) {
            $columns[$field->field] = $field->type;
        }

        return $columns;
    }
}
