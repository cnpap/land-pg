<?php

namespace LandPG\Relation;

use LandPG\Builder;
use LandPG\Collection;
use LandPG\Model;

class BelongsToMiddle extends Relation
{
    protected Builder $middle;

    protected Collection $middleCol;

    protected string $ofLocalKey;

    protected string $ofForeignKey;

    function __construct(Model $model, Builder $foreign, Builder $middle, string $localKey, string $ofLocalKey, string $foreignKey, string $ofForeignKey)
    {
        $this->model        = $model;
        $this->foreign      = $foreign;
        $this->middle       = $middle;
        $this->localKey     = $localKey;
        $this->ofLocalKey   = $ofLocalKey;
        $this->foreignKey   = $foreignKey;
        $this->ofForeignKey = $ofForeignKey;
    }

    public function batch(Collection $collection)
    {
        $localKeys = $collection->one($this->localKey);
        $this->middle->columns([$this->ofLocalKey, $this->ofForeignKey])->where($this->ofLocalKey, 'in', $localKeys);
        $this->dev();
        $this->data = $this->foreign->select();
    }

    public function dev()
    {
        $this->middleCol = $this->middle->select();
        $originKeys      = $this->middleCol->one($this->ofForeignKey);
        $this->foreign->where($this->foreignKey, 'in', $originKeys);
    }

    function fetch(array $localRow): array
    {
        $middleKeys = [];
        for ($i = 0; $i < $this->middleCol->count(); $i++) {
            $middleModel = $this->middleCol[$i];
            if ($localRow[$this->localKey] === $middleModel->{$this->ofLocalKey}) {
                $middleKeys[] = $middleModel->{$this->ofForeignKey};
            }
        }
        $result = [];
        /** @var Model $foreignRow */
        foreach ($this->data as $foreignRow) {
            if (in_array($foreignRow->{$this->foreignKey}, $middleKeys)) {
                $result[] = $foreignRow->toArray();
            }
        }
        return $result;
    }

    function sync($ps = [], array $fixed = [])
    {
        $key  = $this->model->{$this->model->primaryKey};
        $rows = (clone $this->middle)->where($this->ofLocalKey, $key)->delete();
        if ($rows === false) {
            return false;
        }
        if ($ps instanceof Builder) {
            $ps->columns([
                $this->ofLocalKey   => $key,
                $this->ofForeignKey => $this->foreignKey,
                ...$fixed
            ]);
            return (clone $this->middle)->insertMany($ps);
        } else {
            $data = [];
            foreach ($ps as $p) {
                $data[] = [
                    $this->ofLocalKey   => $key,
                    $this->ofForeignKey => $p,
                    ...$fixed
                ];
            }
            return (clone $this->middle)->insertMany($data);
        }
    }
}