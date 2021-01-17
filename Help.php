<?php

namespace LandPG;

class Help
{
    static function getColumns($columns): array
    {
        return array_map(function ($columnK, $columnV) {
            return is_int($columnK) ? $columnV : $columnK;
        }, array_keys($columns), $columns);
    }

    static function mergeColumns($columns1, $columns2): array
    {
        $strColumnKS1 = array_filter($columns1, 'is_string', ARRAY_FILTER_USE_KEY);
        $strColumnKS2 = array_filter($columns2, 'is_string', ARRAY_FILTER_USE_KEY);
        $strColumnKS  = array_merge($strColumnKS1, $strColumnKS2);
        $intColumnKS1 = array_filter($columns1, 'is_int', ARRAY_FILTER_USE_KEY);
        $intColumnKS2 = array_filter($columns2, 'is_int', ARRAY_FILTER_USE_KEY);
        $intColumnKS  = array_merge($intColumnKS1, array_diff($intColumnKS1, $intColumnKS2));
        return array_merge($strColumnKS, $intColumnKS);
    }
}