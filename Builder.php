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
    protected array $columns = [];

    protected array $whereExp = [];

    protected Relation|null $belongs = null;

    protected int $limitNum = 0;

    protected int $offsetNum = 0;

    protected array $withArr = [];

    protected Builder|null $union = null;

    public const ORDER_BY_ASC = 'asc';

    public const ORDER_BY_DESC = 'desc';

    protected array $sort = [];

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

    public function getColumnKeys(): array
    {
        $keys = [];
        foreach ($this->columns as $columnK => $columnV) {
            if (is_numeric($columnK)) {
                $keys[] = $columnV;
            } else {
                $keys[] = $columnK;
            }
        }
        return $keys;
    }

    /**
     * @param string $column
     * @param $char
     * @param $value
     * @param bool $merge
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
    public function where(string $column, $char, $value = null, bool $merge = true): Builder
    {
        if ($value === null && $char !== '=') {
            $value = $char;
            $char  = '=';
        }
        $exps = [[$column, $char, $value]];
        if ($merge && count($this->whereExp)) {
            $endWhere                                   = array_merge(end($this->whereExp), $exps);
            $this->whereExp[count($this->whereExp) - 1] = $endWhere;
        } else {
            $this->whereExp[] = $exps;
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
            $setColumnSql = implode(', ', $data->getColumnKeys());
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
                $execSql .= " on conflict $columnSql do update set " . $this->toUpdatePrepare($conflict[1]);
            } else {
                $execSql .= " on conflict $columnSql do nothing";
            }
        }
        return $execSql;
    }

    public function insert($data, array $conflict = []): mixed
    {
        if ($data instanceof Builder) {
            return pg_affected_rows($this->execute($this->previewInsert($data->limit(1), $conflict)));
        } else {
            return pg_affected_rows($this->execute($this->previewInsert([$data], $conflict)));
        }
    }

    public function insertMany($data, array $conflict = []): mixed
    {
        return pg_affected_rows($this->execute($this->previewInsert($data, $conflict)));
    }

    public function delete(): mixed
    {
        $this->useGuard();
        $execSql = "delete" . " from " . $this->model->getTable();
        if (count($this->whereExp)) {
            $execSql .= ' where ' . $this->toWhereSqlPrepare();
        }
        return pg_affected_rows($this->execute($execSql));
    }

    public function previewUpdate(array $data): mixed
    {
        $this->useGuard();
        $setSql  = $this->toUpdatePrepare($data);
        $execSql = "update " . $this->model->getTable() . " set " . $setSql;
        if (count($this->whereExp)) {
            $execSql .= ' where ' . $this->toWhereSqlPrepare();
        }
        return $execSql;
    }

    public function update(array $data): mixed
    {
        return pg_affected_rows($this->execute($this->previewUpdate($data)));
    }

    public function belongsTo(Relation $belongs)
    {
        $this->belongs = $belongs;
    }

    public function previewSelect(): mixed
    {
        $this->useGuard();
        $columnSql = '*';
        if (count($this->columns)) {
            $columnArr = [];
            foreach ($this->columns as $columnK => $columnV) {
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
            $this->union->columns($this->columns);
            $execSql .= ' ' . $this->union->previewSelect();
        }
        if (count($this->sort)) {
            $execSql       .= ' order by ';
            $groupBySqlArr = [];
            foreach ($this->sort as $column => $order) {
                $groupBySqlArr[] = "$column $order";
            }
            $execSql .= implode(', ', $groupBySqlArr);
        }
        if ($this->offsetNum !== 0) {
            $execSql .= ' offset ' . $this->offsetNum;
        }
        if ($this->limitNum !== 0) {
            $execSql .= ' limit ' . $this->limitNum;
        }
        return $execSql;
    }

    public function select(): BaseCollection
    {
        if (count($this->sort) === 0) {
            $this->orderBy([$this->model->primaryKey => self::ORDER_BY_ASC]);
        }
        $result = $this->execute($this->previewSelect());
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
        'data'     => "",
        'page'     => "int",
        'per_page' => "int",
        'amount'   => "int"
    ])]
    public function page($page = null, $perPage = null): mixed
    {
        if (is_null($page)) {
            $page    = $_GET['page'];
            $perPage = $_GET['per_page'];
        } else if (is_null($perPage)) {
            $perPage = $_GET['per_page'];
        }
        $amount = clone $this;
        if (isset($_GET['order_by'])) {
            $this->orderBy([$_GET['order_by'] => $_GET['order_direction'] ?? 'asc']);
        }
        $this->limit($perPage);
        $this->offset($perPage * ($page - 1));
        return [
            'data'     => $this->select()->toArray(),
            'page'     => (int)$page,
            'per_page' => (int)$perPage,
            'amount'   => (int)$amount->count()
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
        $this->columns(["sum($key)"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['sum'];
    }

    public function avg(string $key): float|int
    {
        $this->columns(["avg($key)"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['avg'];
    }

    public function min(string $key): float|int
    {
        $this->columns(["min($key)"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['min'];
    }

    public function max(string $key): float|int
    {
        $this->columns(["max($key)"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['max'];
    }

    public function count(): int
    {
        $this->columns(["count({$this->model->primaryKey})"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['count'];
    }

    public function keys($column): array
    {
        return array_map(function ($row) use ($column) {
            return $row[$column];
        }, $this->columns([$column])->select()->toArray());
    }

    public function detach(): bool
    {
        if ($this->belongs instanceof BelongsToMiddle) {
            return $this->belongs->detach() !== false;
        } else {
            return false;
        }
    }

    public function attach($data, $fixed = []): bool
    {
        if ($this->belongs instanceof BelongsToMiddle) {
            return $this->belongs->attach($data, $fixed) === false;
        } else {
            return false;
        }
    }

    public function sync($data, $fixed = []): bool
    {
        if ($this->belongs instanceof BelongsToMiddle) {
            return $this->belongs->sync($data, $fixed);
        } else {
            return false;
        }
    }
}