<?php

namespace LandPG\Relation;

use LandPG\Collection as BaseCollection;

class Collection extends BaseCollection
{
    public array $withArr = [];

    public function current()
    {
        $data = $this->data[$this->index];
        foreach ($this->withArr as $with) {
            /** @var Relation $belongs */
            list($method, $belongs) = $with;
            $data[$method] = $belongs->fetch($data);
        }
        return $data;
    }
}