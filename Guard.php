<?php

namespace LandPG;

class Guard
{
    private int  $count = 0;
    public array $data  = [];

    public function str(string $str): string
    {
        $this->data[] = $str;
        return '$' . ++$this->count;
    }

    public function arr(array $arr): string
    {
        $is = [];
        foreach ($arr as $v) {
            $is[]         = '$' . ++$this->count;
            $this->data[] = $v;
        }
        if (count($is)) {
            return '(' . implode(', ', $is) . ')';
        } else {
            return '(null)';
        }
    }

    public function when(array $arr): string
    {
        $caseSql = [];
        foreach ($arr[1] as $when => $then) {
            $caseSql[]    = "when $when then $" . ++$this->count;
            $this->data[] = $then;
        }
        return "case $arr[0] " . implode(' ', $caseSql) . ' end';
    }

    public function fmtSql(string $sql): array|string|null
    {
        $data = $this->data;
        return preg_replace_callback('@\$([\d])@', function ($matches) use ($data) {
            return $data[$matches[1] - 1];
        }, $sql);
    }
}