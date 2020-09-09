<?php

namespace LandPG;

interface Edition
{
    function count();

    function sum(string $key);

    function avg(string $key);

    function min(string $key);

    function max(string $key);
}