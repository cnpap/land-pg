<?php

namespace LandPG\Relation;

use LandPG\Help;
use LandPG\Model;

class BelongsToMany extends BelongsTo
{
    function fetch(Model $localModel, string $method): void
    {
        $result = [];
        /** @var Model $foreignRow */
        foreach ($this->data as $foreignRow) {
            if ((string)$localModel[$this->localKey] === (string)$foreignRow[$this->foreignKey]) {
                if (count($this->columns) > 1) {
                    $columns = Help::getColumns($this->columns);
                    $data    = [];
                    foreach ($foreignRow->toArray() as $column => $value) {
                        if (in_array($column, $columns)) {
                            $data[] = $value;
                        }
                    }
                    $result[] = $data;
                } else {
                    if ($this->merge) {
                        $result[] = $foreignRow[current($this->columns)];
                    } else {
                        $result[] = [current($this->columns) => $foreignRow[current($this->columns)]];
                    }
                }
            }
        }
        $localModel[$method] = $result;
    }
}