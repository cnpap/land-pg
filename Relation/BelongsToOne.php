<?php

namespace LandPG\Relation;

use LandPG\Help;
use LandPG\Model;

class BelongsToOne extends BelongsTo
{
    function fetch(Model $localModel, string $method): void
    {
        if ($this->data->count() === null) {
            $localModel[$method] = null;
        } else {
            $columns = Help::getColumns($this->columns);
            /** @var Model $foreignRow */
            foreach ($this->data as $foreignRow) {
                if ((string)$localModel[$this->localKey] === (string)$foreignRow[$this->foreignKey]) {
                    if ($this->merge) {
                        foreach ($foreignRow->toArray() as $column => $value) {
                            if (in_array($column, $columns)) {
                                $localModel[$column] = $value;
                            }
                        }
                    } else {
                        $result = [];
                        foreach ($foreignRow->toArray() as $column => $value) {
                            if (in_array($column, $columns)) {
                                $result[$column] = $value;
                            }
                        }
                        $localModel[$method] = $result;
                    }
                }
            }
        }
    }
}