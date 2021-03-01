<?php

namespace LandPG;

use Closure;
use JetBrains\PhpStorm\ArrayShape;
use LandPG\Exception\ConnException;
use LandPG\Exception\SqlExecException;
use LandPG\Relation\BelongsToMiddle;
use LandPG\Relation\Relation;
use LandPG\Relation\Collection;
use LandPG\Collection as BaseCollection;

class Builder extends ToSql implements Edition
{
    protected array         $columns      = [];
    protected array         $whereExp     = [];
    protected array         $mustWhereExp = [];
    protected array         $withArr      = [];
    protected array         $sort         = [];
    protected Relation|null $belongs      = null;
    protected Builder|null  $union        = null;
    protected int           $offsetNum    = 0;
    protected int           $limitNum     = 0;
    protected string        $groupBy      = '';
    public const ORDER_BY_ASC  = 'asc';
    public const ORDER_BY_DESC = 'desc';

    public function __construct(public Model $model)
    {
    }

    public function columns(array $columns, bool $merge = false): Builder
    {
        if ($merge) {
            $this->columns = array_merge($this->columns, $columns);
        } else {
            $this->columns = $columns;
        }
        return $this;
    }

    /**
     * @param string $column
     * @param $char
     * @param $value
     * @param bool $merge
     * @param bool $must
     *
     * 'column1', '=', 'aa'
     * 'column2', '>', 'bb'
     * 'column3', '<', 'cc'
     * 'column4', 'in', [1, 2, 3]
     * 'column5', 'not in', [1, 2, 3]
     * 'column6', 'in', a Builder object
     * 'column7', 'is', 'null'
     * 'column8', 'is not', 'null'
     * ....
     *
     * @return $this
     *
     * @see bool $merge 如果传递 false 则 where 通过 or 分割
     */
    public function where(string $column, $char, $value = null, bool $merge = true, bool $must = false): Builder
    {
        if ($value === null) {
            $value = $char;
            if (is_array($char)) {
                $char = 'in';
            } else {
                $char = '=';
            }
        }
        $exps = [[$column, $char, $value]];
        if ($must) {
            $where = &$this->mustWhereExp;
        } else {
            $where = &$this->whereExp;
        }
        if ($merge && count($where)) {
            $endWhere                 = array_merge(end($where), $exps);
            $where[count($where) - 1] = $endWhere;
        } else {
            $where[] = $exps;
        }
        return $this;
    }

    public function when($exp, Closure $next): Builder
    {
        if ((bool)$exp) {
            $next($this, $exp);
        }
        return $this;
    }

    public function orderBy(array $sort): Builder
    {
        $this->sort = $sort;
        return $this;
    }

    public function limit(int $limitNum): Builder
    {
        $this->limitNum = $limitNum;
        return $this;
    }

    public function offset(int $offsetNum): Builder
    {
        $this->offsetNum = $offsetNum;
        return $this;
    }

    public function with(array $withArr): Builder
    {
        $this->withArr = array_merge($this->withArr, $withArr);
        return $this;
    }

    protected function execute($sql): mixed
    {
        $conn = $this->model->getConn();
        $id   = uniqid();
        $ok   = pg_prepare($conn, $id, $sql);
        if ($ok === false) {
            throw new ConnException();
        }
        $result = pg_execute($conn, $id, $this->guard->data);
        if ($result === false) {
            throw new SqlExecException($this->guard->fmtSql($sql));
        }
        return $result;
    }

    public function previewInsert($data, array $conflict): mixed
    {
        $this->useGuard();
        $execSql = 'insert into ' . $this->model->getTable();
        if ($data instanceof Builder) {
            $data->useGuard($this->guard);
            $setColumnSql = implode(', ', Help::getColumns($this->columns));
            $execSql      .= " ($setColumnSql) " . $data->previewSelect();
        } else {
            $sqlArr = [];
            foreach ($data as $row) {
                $sqlArr[] = $this->guard->arr(array_values($row));
            }
            $setColumnSql = implode(', ', array_keys($data[0]));
            $execSql      = "insert into " . $this->model->getTable() . " ($setColumnSql) values " . implode(', ', $sqlArr);
        }
        if (count($conflict)) {
            $columnSql = implode(', ', $conflict[0]);
            if (count($conflict[1])) {
                $execSql .= " on conflict ($columnSql) do update set " . $this->toUpdatePrepare($conflict[1]);
            } else {
                $execSql .= " on conflict $columnSql do nothing";
            }
        }
        return $execSql . " returning *";
    }

    public function insert($data, array $conflict = []): mixed
    {
        if ($data instanceof Builder) {
            return $this->execute($this->previewInsert($data->limit(1), $conflict));
        } else {
            return $this->execute($this->previewInsert([$data], $conflict));
        }
    }

    public function insertMany($data, array $conflict = []): mixed
    {
        return $this->execute($this->previewInsert($data, $conflict));
    }

    public function delete(): mixed
    {
        $this->useGuard();
        $execSql = "delete" . " from " . $this->model->getTable();
        if (count($this->whereExp)) {
            $execSql .= ' where ' . $this->toWhereSqlPrepare();
        }
        return $this->execute($execSql . " returning *");
    }

    public function previewUpdate(array $data): mixed
    {
        $this->useGuard();
        $setSql  = $this->toUpdatePrepare($data);
        $execSql = "update " . $this->model->getTable() . " set " . $setSql;
        if (count($this->whereExp)) {
            $execSql .= ' where ' . $this->toWhereSqlPrepare();
        }
        return $execSql . " returning *";
    }

    public function update(array $data): mixed
    {
        return $this->execute($this->previewUpdate($data));
    }

    public function belongsTo(Relation $belongs)
    {
        $this->belongs = $belongs;
    }

    public function previewSelect($columns = []): mixed
    {
        $this->useGuard();
        $columnSql = '*';
        if (count($this->columns)) {
            $columns = Help::mergeColumns($this->columns, $columns);
        }
        if (count($columns)) {
            $columnArr = [];
            foreach ($columns as $columnK => $columnV) {
                if (is_numeric($columnK)) {
                    $columnArr[] = $columnV;
                } else {
                    $columnArr[] = "$columnV as $columnK";
                }
            }
            $columnSql = implode(', ', $columnArr);
        }
        $execSql = "select $columnSql from " . $this->model->getTable();
        if (count($this->whereExp)) {
            $execSql .= ' where ' . $this->toWhereSqlPrepare();
        }
        if (!is_null($this->union)) {
            $execSql .= ' ' . $this->union->previewSelect($columns);
        }
        if ($this->groupBy !== '') {
            $execSql .= " group by $this->groupBy ";
        }
        if (count($this->sort)) {
            $execSql       .= ' order by ';
            $orderBySqlArr = [];
            foreach ($this->sort as $column => $order) {
                $orderBySqlArr[] = "$column $order";
            }
            $execSql .= implode(', ', $orderBySqlArr);
        }
        if ($this->offsetNum !== 0) {
            $execSql .= ' offset ' . $this->offsetNum;
        }
        if ($this->limitNum !== 0) {
            $execSql .= ' limit ' . $this->limitNum;
        }
        return $execSql;
    }

    public function groupBy($column): Builder
    {
        $this->groupBy = $column;
        return $this;
    }

    public function select($columns = []): BaseCollection
    {
        if (count($this->sort) === 0) {
            $this->orderBy([$this->model->primaryKey => self::ORDER_BY_ASC]);
        }
        $result = $this->execute($this->previewSelect($columns));
        $data   = pg_fetch_all($result);
        if (is_bool($data)) {
            $data = [];
        }
        $face       = count($this->withArr) ? Collection::class : BaseCollection::class;
        $collection = new $face($data, $this->columns, get_class($this->model));
        if (count($this->withArr)) {
            $collection->withArr = array_map(function ($with) use ($collection) {
                [$method, $params] = $this->relationParams($with);
                /** @var Builder $foreign */
                $foreign = $this->model->{$method}(...$params);
                $foreign->belongs->batch($collection);
                return [$method, $foreign->belongs];
            }, $this->withArr);
        }
        return $collection;
    }

    function relationParams($data): array
    {
        $item   = explode(':', $data);
        $params = [];
        if (isset($item[1])) {
            $params = explode(',', $item[1]);
        }
        return [$item[0], $params];
    }

    #[ArrayShape([
        'page_data'    => "",
        'page_current' => "int",
        'page_size'    => "int",
        'page_total'   => "int"
    ])]
    public function page($pageCurrent, $pageSize): mixed
    {
        $amount = clone $this;
        $this->limit($pageSize);
        $this->offset($pageSize * ($pageCurrent - 1));
        return [
            'page_data'    => $this->select()->toArray(),
            'page_current' => (int)$pageCurrent,
            'page_size'    => (int)$pageSize,
            'page_total'   => (int)$amount->count()
        ];
    }

    public function union(Builder $select): Builder
    {
        $this->union = $select;
        return $this;
    }

    public function first(): Model|null
    {
        $this->limit(1);
        return $this->select()->current();
    }

    public function sum(string $key): int|float
    {
        $this->sort = [];
        $this->columns(["sum($key)"]);
        $result = $this->execute($this->previewSelect());
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['sum'];
    }

    public function avg(string $key): float|int
    {
        $this->sort = [];
        $this->columns(["avg($key)"]);
        $result = $this->execute($this->previewSelect());
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['avg'];
    }

    public function min(string $key): float|int
    {
        $this->sort = [];
        $this->columns(["min($key)"]);
        $result = $this->execute($this->previewSelect());
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['min'];
    }

    public function max(string $key): float|int
    {
        $this->sort = [];
        $this->columns(["max($key)"]);
        $result = $this->execute($this->previewSelect());
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['max'];
    }

    public function count(): int
    {
        $this->sort = [];
        $this->columns(["count({$this->model->primaryKey})"]);
        $result = $this->execute($this->previewSelect());
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['count'];
    }

    public function keys($column): array
    {
        return array_map(function ($row) use ($column) {
            return $row[$column];
        }, $this->columns([$column])->select()->toArray());
    }

    public function detach($ids = null): mixed
    {
        if ($this->belongs instanceof BelongsToMiddle) {
            return $this->belongs->detach($ids);
        } else {
            return false;
        }
    }

    public function attach($ids, $fixed = []): mixed
    {
        if ($this->belongs instanceof BelongsToMiddle) {
            return $this->belongs->attach($ids, $fixed);
        } else {
            return false;
        }
    }

    public function sync($data, $fixed = [])
    {
        if ($this->belongs instanceof BelongsToMiddle) {
            $this->belongs->sync($data, $fixed);
        }
    }
}