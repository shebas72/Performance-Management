<?php

namespace App\Services;

use App\Models\Kpi;

class KpiCalculator
{
    /** achievement_pct is decimal(8,4); keep stored values well inside it. */
    public const MAX_STORED = 999.9999;

    public const DEFAULT_RED = 60.0;
    public const DEFAULT_YELLOW = 80.0;

    public function achievement(Kpi $kpi, float $actual, float $target): float
    {
        if ($kpi->direction === 'lower_is_better') {
            if ($actual <= 0) {
                $pct = $target <= 0 ? 100.0 : self::MAX_STORED;   // zero is a perfect score
            } elseif ($target <= 0) {
                $pct = 0.0;
            } else {
                $pct = ($target / $actual) * 100;
            }
        } else {
            $pct = $target == 0.0 ? 0.0 : ($actual / $target) * 100;
        }

        return round(max(0.0, min($pct, self::MAX_STORED)), 4);
    }

    /** Per-KPI status using the KPI's own thresholds (falls back to 60/80). */
    public function status(Kpi $kpi, float $pct): string
    {
        $red    = (float) $kpi->threshold_red > 0 ? (float) $kpi->threshold_red : self::DEFAULT_RED;
        $yellow = (float) $kpi->threshold_yellow > 0 ? (float) $kpi->threshold_yellow : self::DEFAULT_YELLOW;

        return match (true) {
            $pct >= $yellow => 'on_track',
            $pct >= $red    => 'at_risk',
            default         => 'behind',
        };
    }

    /** Status for an aggregated score (objective / perspective / department / company). */
    public function statusForScore(?float $score): string
    {
        if ($score === null) return 'not_entered';

        return match (true) {
            $score >= self::DEFAULT_YELLOW => 'on_track',
            $score >= self::DEFAULT_RED    => 'at_risk',
            default                        => 'behind',
        };
    }

    /** Max achievement counted in rollups, so one over-performer can't mask a failing KPI. */
    public function scoreCap(): float
    {
        return (float) config('spms.score_cap', 120);
    }
}
