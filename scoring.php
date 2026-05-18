<?php
declare(strict_types=1);

function calc_points(array $s): int
{
    $runs = (int)($s['runs'] ?? 0);
    $fours = (int)($s['fours'] ?? 0);
    $sixes = (int)($s['sixes'] ?? 0);
    $wickets = (int)($s['wickets'] ?? 0);
    $catches = (int)($s['catches'] ?? 0);
    $runouts = (int)($s['runouts'] ?? 0);
    $stumpings = (int)($s['stumpings'] ?? 0);
    $maiden_overs = (int)($s['maiden_overs'] ?? 0);

    return
        ($runs * 1) +
        ($fours * 4) +
        ($sixes * 8) +
        ($wickets * 10) +
        ($catches * 6) +
        ($runouts * 6) +
        ($stumpings * 8) +
        ($maiden_overs * 12);
}

function clamp_nonneg_int($v): int
{
    $n = (int)$v;
    return $n < 0 ? 0 : $n;
}

