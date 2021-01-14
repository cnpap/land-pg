<?php

namespace LandPG;

use Closure;
use JetBrains\PhpStorm\Pure;
use Throwable;
use ArrayAccess;
use LandPG\Relation\BelongsTo;
use LandPG\Relation\BelongsToMiddle;
use ReflectionClass;

class Model implements ArrayAccess
{
    protected array  $attributes = [];
    protected string $host       = '';
    protected string $port       = '';
    protected string $username   = '';
    protected string $password   = '';
    protected string $database   = '';
    protected string $table      = '';
    protected string $prefix     = '';
    protected string $suffix     = '';
    public string    $primaryKey = 'id';
    protected array  $filter     = [];

    function __construct(array $attributes = [])
    {
        $this->readComment();
        foreach ($attributes as $attributeName => $attributeVal) {
            if (is_array($attributeVal)) {
                $attributes[$attributeName] = $attributeVal;
            }
        }
        $this->attributes = $attributes;
    }

    function __get($name): mixed
    {
        if (isset($this->attributes[$name])) {
            return $this->filterVal($name);
        } else {
            return null;
        }
    }

    protected function filterVal($name)
    {
        if (isset($this->filter[$name])) {
            return match ($this->filter[$name]) {
                'int' => (int)$this->attributes[$name],
                'array' => json_decode($this->attributes[$name], true),
                'bool' => (bool)$this->attributes[$name],
                'float' => (float)$this->attributes[$name],
                'default' => $this->attributes[$name]
            };
        } else {
            return $this->attributes[$name];
        }
    }

    function __set($name, $value)
    {
        if (is_array($value) && isset($this->filter[$name])) {
            $this->attributes[$name] = json_encode($value);
        } else {
            $this->attributes[$name] = $value;
        }
    }

    function getConn(): mixed
    {
        return pg_connect("host={$this->host} port={$this->port} dbname={$this->database} user={$this->username} password={$this->password}");
    }

    public function transaction(Closure $process, Closure $errProcess = null): mixed
    {
        $begin = $this->begin();
        if ($begin === false) {
            return false;
        }
        try {
            $result = $process() ?? true;
            if (!$result) {
                $this->rollback();
                return false;
            }
            $commit = $this->commit();
            if ($commit === false) {
                return false;
            }
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            if ($errProcess === null) {
                return false;
            } else {
                return $errProcess($e);
            }
        }
    }

    public function begin(): bool
    {
        $conn = $this->getConn();
        if (pg_result_status(pg_exec($conn, "begin")) !== PGSQL_COMMAND_OK) {
            return false;
        }
        return true;
    }

    public function rollback(): bool
    {
        $conn = $this->getConn();
        if (pg_result_status(pg_exec($conn, "rollback")) !== PGSQL_COMMAND_OK) {
            return false;
        }
        return true;
    }

    public function commit(): bool
    {
        $conn = $this->getConn();
        if (pg_result_status(pg_exec($conn, "commit")) !== PGSQL_COMMAND_OK) {
            return false;
        }
        return true;
    }

    function getTable(): string
    {
        return $this->prefix . $this->table . $this->suffix;
    }

    #[Pure]
    static public function query(): Builder
    {
        return new Builder(new static());
    }

    public function hasOne(Builder $foreign, string $localKey, string $foreignKey): Builder
    {
        $belongsTo = new BelongsTo($this, $foreign, $localKey, $foreignKey, true);
        $foreign->belongsTo($belongsTo);
        return $foreign;
    }

    public function hasMany(Builder $foreign, string $localKey, string $foreignKey): Builder
    {
        $belongsTo = new BelongsTo($this, $foreign, $localKey, $foreignKey, false);
        $foreign->belongsTo($belongsTo);
        return $foreign;
    }

    public function hasMiddle(Builder $foreign, Builder $middle, string $localKey, string $ofLocalKey, string $foreignKey, string $ofForeignKey): Builder
    {
        $belongsToMiddle = new BelongsToMiddle($this, $foreign, $middle, $localKey, $ofLocalKey, $foreignKey, $ofForeignKey);
        $foreign->belongsTo($belongsToMiddle);
        return $foreign;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->{$offset};
    }

    public function offsetSet($offset, $value): void
    {
        $this->{$offset} = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function save()
    {
        $builder = new Builder(new static());
        if (isset($this->attributes[$this->primaryKey])) {
            $builder->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
            $this->attributes['updated_at'] = 'now()';
            $result                         = $builder->update($this->attributes);
        } else {
            $result = $builder->insert($this->attributes);
        }
        $this->attributes = pg_fetch_array($result, 0, PGSQL_ASSOC);
    }

    protected function readComment(): void
    {
        $re      = new ReflectionClass($this);
        $comment = $re->getDocComment();
        preg_match_all('@(?:\@property)(?:-(read|write))?\s+(int|bool|array|float)\s+\$?([a-z_]+)@', $comment, $matches, PREG_SET_ORDER);
        if (count($matches)) {
            $filter = [];
            foreach ($matches as $matched) {
                $filter[$matched[3]] = $matched[2];
            }
            $this->filter = $filter;
        }
    }

    public function toArray(): array
    {
        $filter     = $this->filter;
        $attributes = $this->attributes;
        foreach (array_keys($attributes) as $attributeName) {
            if (isset($filter[$attributeName])) {
                $attributes[$attributeName] = $this->filterVal($attributeName);
            }
        }
        return $attributes;
    }
}