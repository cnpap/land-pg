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
        $data = new $this->from($this->data[$offset]);
        foreach ($this->withArr as $with) {
            /** @var Relation $belongs */
            list($method, $belongs) = $with;
            $more = $belongs->fetch($data);
            if (!(bool)$more) {
                if ($belongs instanceof BelongsTo) {
                    $more = null;
                } else {
                    $more = [];
                }
            }
            $data[$method] = $more;
        }
        if ($instance) {
            return $data;
        }
        return $data;
    }
}