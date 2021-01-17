<?php

namespace LandPG\Relation;

use LandPG\Collection;
use LandPG\Help;

abstract class BelongsTo extends Relation
{
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