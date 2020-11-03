<?php

namespace LandPG;

use Closure;
use LandPG\Exception\ConnException;
use LandPG\Exception\SqlExecException;
use LandPG\Relation\Relation;
use LandPG\Relation\Collection;
use LandPG\Collection as BaseCollection;

class Builder extends ToSql implements Edition
{
    protected Model $model;

    protected array $columns = [];

    protected array $whereExp = [];

    protected ?Relation $belongs = null;

    protected int $limitNum = 0;

    protected int $offsetNum = 0;

    protected array $withArr = [];

    protected ?Builder $union = null;

    public const ORDER_BY_ASC = 'asc';

    public const ORDER_BY_DESC = 'desc';

    protected array $sort = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function columns(array $columns)
    {
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    /**
     * @param string $column
     * @param string $char
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
    public function where(string $column, string $char, $value, bool $merge = true)
    {
        $exps = [[$column, $char, $value]];
        if ($merge && count($this->whereExp)) {
            $endWhere                                   = array_merge(end($this->whereExp), $exps);
            $this->whereExp[count($this->whereExp) - 1] = $endWhere;
        } else {
            $this->whereExp[] = $exps;
        }
        return $this;
    }

    public function when($exp, Closure $next)
    {
        if ((bool)$exp) {
            $next($this, $exp);
        }
        return $this;
    }

    /**
     * @param array $sort
     *
     * @return $this
     *
     * @see $sort
     * [
     *     column1 => 'asc',
     *     column2 => 'desc'
     * ]
     */
    public function orderBy(array $sort)
    {
        $this->sort = $sort;
        return $this;
    }

    /**
     * @param int $limitNum
     * @return $this
     */
    public function limit(int $limitNum)
    {
        $this->limitNum = $limitNum;
        return $this;
    }

    /**
     * @param int $offsetNum
     * @return $this
     */
    public function offset(int $offsetNum)
    {
        $this->offsetNum = $offsetNum;
        return $this;
    }

    /**
     * @param array $withArr
     * @return $this
     *
     * @see array $withArr
     * [
     *     a instanceOF LandPG\Relation\Relation::class object,
     *     a instanceOF LandPG\Relation\Relation::class object,
     *     ....
     * ]
     */
    public function with(array $withArr)
    {
        $this->withArr = array_merge($this->withArr, $withArr);
        return $this;
    }

    /**
     * @param $sql
     * @return resource
     */
    protected function execute($sql)
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

    public function previewInsert(array $data, array $conflict)
    {
        $this->useGuard();
        $sqlArr = [];
        foreach ($data as $row) {
            $sqlArr[] = $this->guard->arr(array_values($row));
        }
        $setColumnSql = implode(', ', array_keys($data[0]));
        $execSql      = "insert into " . $this->model->getTable() . " ($setColumnSql) values " . implode(', ', $sqlArr);
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

    /**
     * @param array $data
     * @param array $conflict
     *
     * @return int
     *
     * @see $data        二维数组, 一个或多个键值, 做为新增数据
     * @see $conflict[0] 一维数组, 一个或多个字段用作判断信息是否存在, 如果存在则舍弃新增, 执行子查询
     * @see $conflict[1] 一维数组, 没有数据, 则无子查询, 如果有数据, 则作为 update 的变更数据
     */
    public function insert(array $data, array $conflict = [])
    {
        return pg_affected_rows($this->execute($this->previewInsert($data, $conflict)));
    }

    public function delete()
    {
        $this->useGuard();
        $execSql = "delete" . " from " . $this->model->getTable();
        if (count($this->whereExp)) {
            $execSql .= ' where ' . $this->toWhereSqlPrepare();
        }
        return pg_affected_rows($this->execute($execSql));
    }

    public function previewUpdate(array $data)
    {
        $this->useGuard();
        $setSql  = $this->toUpdatePrepare($data);
        $execSql = "update " . $this->model->getTable() . " set " . $setSql;
        if (count($this->whereExp)) {
            $execSql .= ' where ' . $this->toWhereSqlPrepare();
        }
        return $execSql;
    }

    public function update(array $data)
    {
        return pg_affected_rows($this->execute($this->previewUpdate($data)));
    }

    public function belongsTo(Relation $belongs)
    {
        $this->belongs = $belongs;
    }

    public function previewSelect()
    {
        $this->useGuard();
        $columnSql = '*';
        if (count($this->columns)) {
            $columnSql = implode(', ', $this->columns);
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

    /**
     * @return \LandPG\Collection|Collection
     */
    public function select()
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
            $collection->withArr = array_map(function ($method) use ($collection) {
                /** @var Builder $foreign */
                $foreign = $this->model->{$method}();
                $foreign->belongs->batch($collection);
                return [$method, $foreign->belongs];
            }, $this->withArr);
        }
        return $collection;
    }

    public function page($page = null, $perPage = null)
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

    public function union(Builder $select)
    {
        $this->union = $select;
    }

    /**
     * @return Model|null
     */
    public function first()
    {
        $this->limit(1);
        return $this->select()->current();
    }

    public function sum(string $key)
    {
        $this->columns(["sum($key)"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['sum'];
    }

    public function avg(string $key)
    {
        $this->columns(["avg($key)"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['avg'];
    }

    public function min(string $key)
    {
        $this->columns(["min($key)"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['min'];
    }

    public function max(string $key)
    {
        $this->columns(["max($key)"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['max'];
    }

    public function count()
    {
        $this->columns(["count({$this->model->primaryKey})"]);
        $result = $this->execute($this->previewSelect());
        if ($result === false) {
            return false;
        }
        return pg_fetch_array($result, 0, PGSQL_ASSOC)['count'];
    }

    public function keys($column)
    {
        return array_map(function ($row) use ($column) {
            return $row[$column];
        }, $this->columns([$column])->select()->toArray());
    }
}