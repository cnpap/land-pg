<?php


namespace LandPG\Relation;


use LandPG\Builder;
use LandPG\Help;
use LandPG\Model;

class BelongsToMany extends BelongsTo
{
    function __construct(Model $model, Builder $foreign, string $localKey, string $foreignKey, array $columns)
    {
        $this->model      = $model;
        $this->foreign    = $foreign;
        $this->localKey   = $localKey;
        $this->foreignKey = $foreignKey;
        $this->columns    = $columns;
    }

    function fetch(Model $localModel, string $method): void
    {
        $result  = [];
        $columns = Help::getColumns($this->columns);
        /** @var Model $foreignRow */
        foreach ($this->data as $foreignRow) {
            if ($localModel[$this->localKey] === $foreignRow[$this->foreignKey]) {
                $data = [];
                foreach ($foreignRow->toArray() as $column => $value) {
                    if (in_array($column, $columns)) {
                        $data[] = $value;
                    }
                }
                $result[] = $data;
            }
        }
        $localModel[$method] = $result;
    }
}