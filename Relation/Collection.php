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

    public function offsetGet($offset)
    {
        $data = $this->data[$offset];
        foreach ($this->withArr as $with) {
            /** @var Relation $belongs */
            list($method, $belongs) = $with;
            $data[$method] = $belongs->fetch($data);
        }
        return new $this->from($data);
    }
}