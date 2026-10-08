<?php

namespace App\Support;

class RequestMetrics
{
    public bool $active = false;
    public int $queries = 0;
    public float $databaseMs = 0;
}
