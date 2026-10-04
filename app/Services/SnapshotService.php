<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Writes rollups into performance_snapshots (history + cheap reads for reports/PDF). */
class SnapshotService
{
    public function __construct(private PerformanceService $perf) {}

    public function refreshMonth(int $companyId, int $year, int $month): void
    {
        $ctx = $this->perf->load($companyId, $year);
        $r   = $this->perf->rollup($ctx, $this->perf->kpiScores($ctx, $month));

        DB::transaction(function () use ($companyId, $year, $month, $r) {
            $this->put($companyId, $year, $month, 'company', [], $r['company']);
            foreach ($r['perspectives'] as $id => $row) $this->put($companyId, $year, $month, 'perspective', ['bsc_perspective_id' => $id], $row);
            foreach ($r['objectives'] as $id => $row)   $this->put($companyId, $year, $month, 'objective', ['strategic_objective_id' => $id], $row);
            foreach ($r['departments'] as $id => $row)  $this->put($companyId, $year, $month, 'department', ['department_id' => $id], $row);
        });
    }

    private function put(int $companyId, int $year, int $month, string $scope, array $ids, array $row): void
    {
        $keys = array_merge([
            'company_id' => $companyId, 'year' => $year, 'month' => $month, 'scope' => $scope,
            'department_id' => null, 'bsc_perspective_id' => null, 'strategic_objective_id' => null,
        ], $ids);

        $values = [
            'achievement_pct' => $row['score'],
            'kpi_count'       => $row['kpi_count'],
            'kpi_high'        => $row['high'],
            'kpi_medium'      => $row['medium'],
            'kpi_low'         => $row['low'],
            'kpi_not_entered' => $row['not_entered'],
            'calculated_at'   => now(),
            'updated_at'      => now(),
        ];

        $q = DB::table('performance_snapshots')->where($keys);
        if ($q->exists()) {
            $q->update($values);
        } else {
            DB::table('performance_snapshots')->insert($keys + $values + ['created_at' => now()]);
        }
    }
}
