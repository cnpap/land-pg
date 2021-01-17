<?php

namespace LandPG\Relation;

use LandPG\Builder;
use LandPG\Collection;
use LandPG\Help;
use LandPG\Model;

abstract class BelongsTo extends Relation
{
    protected bool $merge;

    function __construct(Model $model, Builder $foreign, string $localKey, string $foreignKey, array $columns, bool $merge)
    {
        $this->model      = $model;
        $this->foreign    = $foreign;
        $this->localKey   = $localKey;
        $this->foreignKey = $foreignKey;
        $this->columns    = $columns;
        $this->merge      = $merge;
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
            ->select(Help::mergeColumns([$this->foreignKey], $this->columns));
    }
}