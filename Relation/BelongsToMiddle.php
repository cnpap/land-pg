<?php

namespace LandPG\Relation;

use LandPG\Builder;
use LandPG\Collection;
use LandPG\Model;

class BelongsToMiddle extends Relation
{
    protected string $middle;

    protected string $ofLocalKey;

    protected string $ofForeignKey;

    function __construct(Model $model, Builder $foreign, Builder $middle, string $localKey, string $ofLocalKey, string $foreignKey, string $ofForeignKey)
    {
        $foreign->columns([$foreignKey]);
        $this->model = $model;
        $this->foreign = $foreign;
        $this->middle = $middle;
        $this->localKey = $localKey;
        $this->ofLocalKey = $ofLocalKey;
        $this->foreignKey = $foreignKey;
        $this->ofForeignKey = $ofForeignKey;
    }

    public function attach()
    {
        $localKey = $this->model->{$this->localKey};
        $this->middle->columns([$this->ofForeignKey]);
        $this->middle->where([[$this->ofLocalKey, '=', $localKey]]);
        $this->dev();
    }

    public function batch(Collection $collection)
    {
        $localKeys = $collection->one($this->localKey);
        $this->middle->columns([$this->ofForeignKey]);
        $this->middle->where([[$this->ofLocalKey, 'in', $localKeys]]);
        $this->dev();
        $this->foreign->select();
    }

    public function dev()
    {
        $originKeys = $this->middle->select()->one($this->ofForeignKey);
        $this->foreign->where([[$this->foreignKey, 'in', $originKeys]]);
    }

    function fetch(array $localRow)
    {
        $result = [];
        foreach ($this->data as $foreignRow) {
            if ($localRow[$this->localKey] === $foreignRow[$this->foreignKey]) {
                $result[] = $foreignRow;
            }
        }
        return $result;
    }
}