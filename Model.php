<?php

namespace LandPG;

use Closure;
use LandPG\Relation\BelongsToMany;
use LandPG\Relation\BelongsToOne;
use Throwable;
use ArrayAccess;
use LandPG\Relation\BelongsToMiddle;

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
        $this->attributes = $attributes;
    }

    function __get($name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    function __set($name, $value)
    {
        if (is_array($value) && isset($this->filter[$name]) && $this->filter[$name] === 'array') {
            $this->attributes[$name] = json_encode($value);
        } else {
            $this->attributes[$name] = $value;
        }
    }

    function toArray(): array
    {
        return $this->attributes;
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

    static public function query(Model $model = null): Builder
    {
        return new Builder($model ?? new static());
    }

    public function hasOne(Builder $foreign, string $localKey, string $foreignKey, array $columns = [], bool $merge = false): Builder
    {
        $belongsTo = new BelongsToOne($this, $foreign, $localKey, $foreignKey, $columns, $merge);
        $foreign->belongsTo($belongsTo);
        return $foreign;
    }

    public function hasMany(Builder $foreign, string $localKey, string $foreignKey, array $columns = [], bool $merge = true): Builder
    {
        $belongsTo = new BelongsToMany($this, $foreign, $localKey, $foreignKey, $columns, $merge);
        $foreign->belongsTo($belongsTo);
        return $foreign;
    }

    public function hasMiddle(Builder $foreign, Builder $middle, string $localKey, string $ofLocalKey, string $foreignKey, string $ofForeignKey, array $columns = [], array $ofColumns = []): Builder
    {
        $belongsToMiddle = new BelongsToMiddle($this, $foreign, $middle, $localKey, $ofLocalKey, $foreignKey, $ofForeignKey, $columns, $ofColumns);
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
}