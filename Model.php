<?php

namespace LandPG;

use Closure;
use Throwable;
use ArrayAccess;
use LandPG\Relation\BelongsTo;
use LandPG\Relation\BelongsToMiddle;

class Model implements ArrayAccess
{
    protected string $host = '';

    protected string $port = '';

    protected string $username = '';

    protected string $password = '';

    protected string $database = '';

    protected string $table = '';

    protected string $prefix = '';

    protected string $suffix = '';

    public string $primaryKey = 'id';

    protected array $attributes = [];

    function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    function getConn()
    {
        return pg_connect("host={$this->host} port={$this->port} dbname={$this->database} user={$this->username} password={$this->password}");
    }

    /**
     * @param Closure $process
     * @return false
     * @throws Throwable
     */
    public function transaction(Closure $process)
    {
        $begin = $this->begin();
        if ($begin === false) {
            return false;
        }
        try {
            $result = $process();
            if ($result) {
                $this->rollback();
                return false;
            }
            $commit = $this->commit();
            if ($commit === false) {
                return false;
            }
        } catch (Throwable $e) {
            $this->commit();
            throw $e;
        }
    }

    public function begin()
    {
        $conn = $this->getConn();
        if (pg_result_status(pg_exec($conn, "begin")) !== PGSQL_COMMAND_OK) {
            return false;
        }
        return true;
    }

    public function rollback()
    {
        $conn = $this->getConn();
        if (pg_result_status(pg_exec($conn, "rollback")) !== PGSQL_COMMAND_OK) {
            return false;
        }
        return true;
    }

    public function commit()
    {
        $conn = $this->getConn();
        if (pg_result_status(pg_exec($conn, "commit")) !== PGSQL_COMMAND_OK) {
            return false;
        }
        return true;
    }

    function getTable()
    {
        return $this->prefix . $this->table . $this->suffix;
    }

    static public function query()
    {
        return new Builder(new static());
    }

    public function hasOne(Builder $foreign, string $localKey, string $foreignKey)
    {
        $belongsTo = new BelongsTo($this, $foreign, $localKey, $foreignKey, true);
        $foreign->belongsTo($belongsTo);
        return $foreign;
    }

    public function hasMany(Builder $foreign, string $localKey, string $foreignKey)
    {
        $belongsTo = new BelongsTo($this, $foreign, $localKey, $foreignKey, false);
        $foreign->belongsTo($belongsTo);
        return $foreign;
    }

    public function hasMiddle(Builder $foreign, Builder $middle, string $localKey, string $ofLocalKey, string $foreignKey, string $ofForeignKey)
    {
        $belongsToMiddle = new BelongsToMiddle($this, $foreign, $middle, $localKey, $ofLocalKey, $foreignKey, $ofForeignKey);
        $foreign->belongsTo($belongsToMiddle);
        return $foreignKey;
    }

    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->{$offset};
    }

    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    public function toArray()
    {
        return $this->attributes;
    }
}