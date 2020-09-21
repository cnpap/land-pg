<?php

namespace LandPG\Relation;

use LandPG\Builder;
use LandPG\Collection;
use LandPG\Model;

class BelongsTo extends Relation
{
    protected bool $first;

    function __construct(Model $model, Builder $foreign, string $localKey, string $foreignKey, bool $first = false)
    {
        $foreign->columns([$foreignKey]);
        $this->model = $model;
        $this->foreign = $foreign;
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->first = $first;
    }

    function attach()
    {
        $localKey = $this->model->{$this->localKey};
        $this->foreign->where($this->foreignKey, '=', $localKey);
        if ($this->first) {
            $this->foreign->limit(1);
        }
    }

    function batch(Collection $collection)
    {
        $localKeys = $collection->one($this->localKey);
        $this->foreign->where($this->foreignKey, 'in', $localKeys);
        $this->data = $this->foreign->select();
    }

    function fetch(array $localRow)
    {
        $result = [];
        /** @var Model $foreignRow */
        foreach ($this->data as $foreignRow) {
            if ($localRow[$this->localKey] === $foreignRow[$this->foreignKey]) {
                if ($this->first) {
                    return $foreignRow->toArray();
                } else {
                    $result[] = $foreignRow->toArray();
                }
            }
        }
        return $result;
    }
}