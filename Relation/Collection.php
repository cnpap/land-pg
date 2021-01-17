<?php

namespace LandPG\Relation;

use LandPG\Collection as BaseCollection;
use LandPG\Model;

class Collection extends BaseCollection
{
    public array $withArr = [];

    public function current()
    {
        return $this->offsetGet($this->index);
    }

    public function offsetGet($offset, $instance = true): ?Model
    {
        if (!isset($this->data[$offset])) {
            return null;
        }
        /** @var Model $data */
        $data = new $this->from($this->data[$offset]);
        foreach ($this->withArr as $with) {
            /** @var Relation $belongs */
            list($method, $belongs) = $with;
            $belongs->fetch($data, $method);
        }
        if ($instance) {
            return $data;
        }
        return $data;
    }
}