<?php

namespace LandPG;

class Guard
{
    private int $count = 0;

    public array $data = [];

    public function str(string $str)
    {
        $this->data[] = $str;
        return '$' . ++$this->count;
    }

    public function arr(array $arr)
    {
        $is = [];
        foreach ($arr as $v) {
            $is[] = '$' . ++$this->count;
            $this->data[] = $v;
        }
        return '(' . implode(', ', $is) . ')';
    }

    public function when(array $arr)
    {
        $caseSql = [];
        foreach ($arr[1] as $when => $then) {
            $caseSql[] = "when $when then $" . ++$this->count;
            $this->data[] = $then;
        }
        return "case $arr[0] " . implode(' ', $caseSql) . ' end';
    }
}