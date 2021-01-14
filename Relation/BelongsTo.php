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
        $this->model      = $model;
        $this->foreign    = $foreign;
        $this->localKey   = $localKey;
        $this->foreignKey = $foreignKey;
        $this->first      = $first;
    }

    function batch(Collection $collection)
    {
        $this->data = $this
            ->foreign
            ->where(
                $this->foreignKey,
                'in',
                $collection->one($this->localKey)
            )
            ->select([$this->foreignKey]);
    }

    function fetch(Model $localModel): array
    {
        $result = [];
        /** @var Model $foreignRow */
        foreach ($this->data as $foreignRow) {
            if ($localModel[$this->localKey] === $foreignRow[$this->foreignKey]) {
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