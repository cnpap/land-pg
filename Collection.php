<?php

namespace LandPG;

use Iterator;
use ArrayAccess;

class Collection implements Iterator, Edition, ArrayAccess
{
    protected array $data;

    protected int $index = 0;

    protected string $from;

    public function __construct(array $data, string $from)
    {
        $this->data = $data;
        $this->from = $from;
    }

    public function current()
    {
        if (isset($this->data[$this->index])) {
            return new $this->from($this->data[$this->index]);
        }
        return null;
    }

    public function next()
    {
        ++$this->index;
    }

    public function key()
    {
        return $this->index;
    }

    public function valid()
    {
        return isset($this->data[$this->index]);
    }

    public function rewind()
    {
        $this->index = 0;
    }

    function toArray()
    {
        $data = [];
        for ($i = 0; $i < count($this->data); $i++) {
            $data[] = $this->offsetGet($i, false);
        }
        return $data;
    }

    private function cleaned()
    {
        $this->rewind();
        return $this->current();
    }

    public function avg(string $key)
    {
        $tmp = 0;
        foreach ($this->data as $row) {
            $tmp = $tmp + $row->{$key};
        }
        return $tmp / count($this->data);
    }

    public function max(string $key)
    {
        $tmp = $this->cleaned();
        foreach ($this->data as $row) {
            if ($row->{$key} > $tmp) {
                $tmp = $row->{$key};
            }
        }
        return $tmp;
    }

    public function min(string $key)
    {
        $tmp = $this->cleaned();
        foreach ($this->data as $row) {
            if ($row->{$key} < $tmp) {
                $tmp = $row->{$key};
            }
        }
        return $tmp;
    }

    public function sum(string $key)
    {
        $tmp = 0;
        foreach ($this->data as $row) {
            $tmp = $tmp + $row->{$key};
        }
        return $tmp;
    }

    public function count()
    {
        return count($this->data);
    }

    public function one(string $key)
    {
        return array_map(function ($row) use ($key) {
            return $row[$key];
        }, $this->data);
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset, $instance = true)
    {
        if ($instance === true) {
            return new $this->from($this->data[$offset] ?? null);
        }
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }
}