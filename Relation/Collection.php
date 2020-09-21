<?php

namespace LandPG\Relation;

use LandPG\Collection as BaseCollection;

class Collection extends BaseCollection
{
    public array $withArr = [];

    public function current()
    {
        return $this->offsetGet($this->index);
    }

    public function offsetGet($offset, $instance = true)
    {
        if (!isset($this->data[$offset])) {
            return null;
        }
        $data = $this->data[$offset];
        foreach ($this->withArr as $with) {
            /** @var Relation $belongs */
            list($method, $belongs) = $with;
            $data[$method] = $belongs->fetch($data);
        }
        if ($instance) {
            return new $this->from($data);
        }
        return $data;
    }
}