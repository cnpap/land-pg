<?php

namespace LandPG;

/**
 * Class ToSql
 * @package LandPG
 *
 * @property-read array whereExp
 * @property-read array mustWhereExp
 * @method previewSelect
 */
class ToSql
{
    public ?Guard $guard = null;

    public function useGuard(Guard $guard = null)
    {
        if (is_null($guard) && is_null($this->guard)) {
            $this->guard = new Guard();
        } else if (!is_null($guard)) {
            $this->guard = $guard;
        }
    }

    protected function toUpdatePrepare($data): string
    {
        $sqlArr = [];
        foreach ($data as $column => $exp) {
            if (is_string($exp) || is_numeric($exp) || is_float($exp)) {
                $sqlArr[] = "$column = " . $this->guard->str($exp);
            } else if (is_array($exp)) {
                $sqlArr[] = "$column = " . $this->guard->when($exp);
            } else if ($exp instanceof Builder) {
                $exp->useGuard($this->guard);
                $sqlArr[] = "$column = " . '(' . $exp->previewSelect() . ')';
            } else if ($exp === null) {
                $sqlArr[] = "$column = null";
            } else {
                $sqlArr[] = $column;
            }
        }
        return implode(', ', $sqlArr);
    }

    protected function toWherePartSqlPrepare($baseWhereExp): string
    {
        $sqlArr = [];
        foreach ($baseWhereExp as $whereExp) {
            $whereSqlArr = [];
            foreach ($whereExp as $exp) {
                $sqlStr = $exp[0] . " {$exp[1]} ";
                $value  = $exp[2];
                if (is_string($value) || is_numeric($value) || is_float($value)) {
                    $whereSqlArr[] = $sqlStr . $this->guard->str($value);
                } else if (is_array($value)) {
                    $whereSqlArr[] = $sqlStr . $this->guard->arr($value);
                } else if ($value instanceof Builder) {
                    $value->useGuard($this->guard);
                    $whereSqlArr[] = $sqlStr . '(' . $value->previewSelect() . ')';
                } else if ($value === null) {
                    $whereSqlArr[] = $sqlStr . 'null';
                }
            }
            $sqlArr[] = implode(' and ', $whereSqlArr);
        }
        return implode(' or ', $sqlArr);
    }

    protected function toWhereSqlPrepare(): string
    {
        $whereSql = $this->toWherePartSqlPrepare($this->whereExp);
        if (count($this->mustWhereExp)) {
            $mustWhereSql = $this->toWherePartSqlPrepare($this->mustWhereExp);
            return "($mustWhereSql) and ($whereSql)";
        }
        return $whereSql;
    }
}