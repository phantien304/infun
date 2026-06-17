<?php

namespace App\Models\Base;

use App\Models\Traits\HasAdvancedScopes;
use App\Models\Traits\HasAuditColumns;
use App\Models\Traits\HasCascadeRelations;
use App\Models\Traits\HasCompositeKey;
use App\Models\Traits\HasSchemaCache;
use App\Services\StoredProcedureService;
use Illuminate\Database\Eloquent\Model;

class Base extends Model
{
    use \Awobaz\Compoships\Compoships;
    use HasAuditColumns;
    use HasCompositeKey;
    use HasCascadeRelations;
    use HasSchemaCache;
    use HasAdvancedScopes;

    protected $allowBlankField = false;
    protected $exceptAllowBlankField = [];
    public $timestamps = false;
    protected $alias = '';
    protected $primaryKeyAutoIncrement = '';
    protected $sequence = '';

    public function getAllowBlankField(): bool
    {
        return $this->allowBlankField;
    }

    public function getExceptAllowBlankField()
    {
        return $this->exceptAllowBlankField;
    }

    public function setExceptAllowBlankField($exceptAllowBlankField)
    {
        $this->exceptAllowBlankField = $exceptAllowBlankField;
    }

    public function getPrimaryKeyAutoIncrement()
    {
        return $this->primaryKeyAutoIncrement;
    }

    public function setPrimaryKeyAutoIncrement($primaryKeyAutoIncrement)
    {
        $this->primaryKeyAutoIncrement = $primaryKeyAutoIncrement;
    }

    public function getSequence()
    {
        return $this->sequence;
    }

    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
    }

    public static function getTableName(): string
    {
        return (new static())->getTable();
    }

    public static function newEntity(): static
    {
        return new static();
    }

    public function getField($field)
    {
        return $this->getTableName() . '.' . $field;
    }

    public function getQualifiedColumn($column)
    {
        return $this->getTable() . '.' . $column;
    }

    public static function getQuaColumn($column)
    {
        return (new static())->getQualifiedColumn($column);
    }

    public function getAlias()
    {
        return $this->alias ?: $this->table;
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function save(array $options = [])
    {
        $attrs = $this->getAttributes();
        $ids = (array)$this->getKeyName();
        $update = true;
        foreach ($ids as $id) {
            $id = $id ? $id : 'id';
            if (!isset($attrs[$id])) {
                $update = false;
                unset($attrs[$id]);
            }
        }
        if ($this->allowBlankField) {
            foreach ($this->getFillable() as $field) {
                if (isset($attrs[$field]) || in_array($field, $this->getExceptAllowBlankField())) {
                    continue;
                }
                $attrs[$field] = '';
            }
        }
        $update && $this->allowFillActionAt() ? $attrs[getSystemConfig('updated_at_column.field')] = now()->toDateTimeString() : null;
        $this->setRawAttributes([])->fill($attrs);
        return parent::save($options);
    }

    public function fill(array $attributes)
    {
        $keys = (array)$this->getKeyName();
        foreach ($keys as $key) {
            $key = $key ? $key : 'id';
            isset($attributes[$key]) ? $this->$key = $attributes[$key] : null;
        }
        return parent::fill($attributes);
    }

    public function hasAttribute($key)
    {
        return array_key_exists($key, $this->getAttributes());
    }

    public function removeAttribute($key)
    {
        unset($this->attributes[$key]);
        return $this;
    }

    public function mergeAttributes($data)
    {
        $this->attributes = array_merge($this->attributes, $data);
        return $this;
    }

    public function removeTrashAttributes()
    {
        $attrs = $this->getAttributes();
        $this->setRawAttributes([])->fill($attrs);
        return $this;
    }

    public function removeRelation($key)
    {
        $relations = $this->getRelations();
        unset($relations[$key]);
        $this->setRelations($relations);
        return $this;
    }

    public function removeRelations()
    {
        $this->setRelations([]);
    }

    public function getNextInsertId(): int|string
    {
        $entity = $this;
        if ($entity->getKey()) {
            return $entity->getKey();
        }
        $nextId = 1;
        $table = $entity->getTable();
        switch ($this->getConnection()->getDriverName()) {
            case 'mysql':
                $statement = $this->getConnection()->select("SHOW TABLE STATUS LIKE '{$table}'");
                $nextId = $statement[0]->Auto_increment;
                break;
            case 'pgsql':
                $statement = $this->getConnection()->select("SELECT nextval('{$this->getSequence()}')");
                $nextId = $statement[0]->nextval;
                break;
            case 'sqlite':
                break;
            case 'sqlsrv':
                break;
        }
        return $nextId;
    }

    protected function insertAndSetId(\Illuminate\Database\Eloquent\Builder $query, $attributes)
    {
        if ($this->incrementing && empty($attributes[$this->getPrimaryKeyAutoIncrement()])) {
            $attributes[$this->getPrimaryKeyAutoIncrement()] = $this->getNextInsertId();
        }
        return parent::insertAndSetId($query, $attributes);
    }

    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }
        return match ($this->getCastType($key)) {
            'bigint'    => (int) $value,
            'character' => (string) $value,
            default     => parent::castAttribute($key, $value),
        };
    }

    protected function fireModelEvent($event, $halt = true)
    {
        if (!isset(static::$dispatcher)) {
            return true;
        }
        $method = $halt ? 'until' : 'dispatch';
        $result = $this->filterModelEventResults(
            $this->fireCustomModelEvent($event, $method)
        );
        if ($result === false) {
            return false;
        }
        $prefix = defined('EVENT_MODEL_TYPE') ? getConstant('EVENT_MODEL_TYPE') : 'eloquent';
        return !empty($result) ? $result : static::$dispatcher->{$method}(
            $prefix . ".{$event}: " . static::class,
            $this
        );
    }

    protected static function registerModelEvent($event, $callback): void
    {
        if (isset(static::$dispatcher)) {
            $name = static::class;
            $prefix = defined('EVENT_MODEL_TYPE') ? getConstant('EVENT_MODEL_TYPE') : 'eloquent';
            static::$dispatcher->listen($prefix . ".{$event}: {$name}", $callback);
        }
    }

    public static function getAllGlobalScope()
    {
        return static::$globalScopes;
    }

    public static function callRaw(string $sProcedure, array $aParams = [], bool $isExecute = false): mixed
    {
        return StoredProcedureService::call($sProcedure, $aParams, $isExecute);
    }
}
