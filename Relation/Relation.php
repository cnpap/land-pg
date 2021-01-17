<?php

namespace LandPG\Relation;

use LandPG\Builder;
use LandPG\Collection;
use LandPG\Model;

abstract class Relation
{
    public Model         $model;
    public string        $foreignKey;
    protected Builder    $foreign;
    protected string     $localKey;
    protected Collection $data;
    protected array      $columns;

    abstract public function batch(Collection $collection);

    abstract public function fetch(Model $localModel, string $method): void;
}