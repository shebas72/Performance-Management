<?php

namespace App\Services;

use App\Models\Kpi;
use App\Models\KpiEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpiEntryService
{
    public function __construct(private KpiCalculator $calc) {}

    /**
     * Create or update the entry for (kpi, year, month) and compute achievement + status.
     * $prefix is used for error keys in bulk mode (e.g. "entries.3.").
     */
    public function save(Kpi $kpi, User $user, int $year, int $month, array $data, string $prefix = ''): KpiEntry
    {
        if ((int) $kpi->year !== $year) {
            throw ValidationException::withMessages([
                $prefix . 'year' => "Year must match the KPI year ({$kpi->year}).",
            ]);
        }

        $raw    = $data['actual_value'] ?? null;
        $actual = ($raw === null || $raw === '') ? null : (float) $raw;

        $dataStatus = $data['data_status'] ?? ($actual === null ? 'incomplete' : 'complete');
        $reason     = $data['incomplete_reason'] ?? null;

        if ($actual === null && $dataStatus === 'complete') {
            throw ValidationException::withMessages([
                $prefix . 'actual_value' => 'actual_value is required when data_status is complete.',
            ]);
        }
        if ($dataStatus === 'incomplete' && empty($reason)) {
            throw ValidationException::withMessages([
                $prefix . 'incomplete_reason' => 'incomplete_reason is required when data is incomplete.',
            ]);
        }

        $pct    = null;
        $status = 'not_entered';

        if ($actual !== null) {
            $target = DB::table('kpi_targets')
                ->where('kpi_id', $kpi->id)
                ->where('company_id', $kpi->company_id)
                ->where('year', $year)
                ->where('month', $month)
                ->value('target_value');

            if ($target === null) {
                throw ValidationException::withMessages([
                    $prefix . 'actual_value' => "No target is set for {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '. Add a KPI target first.',
                ]);
            }

            $pct    = $this->calc->achievement($kpi, $actual, (float) $target);
            $status = $this->calc->status($kpi, $pct);
        }

        $attrs = [
            'company_id'        => $kpi->company_id,
            'logged_by'         => $user->id,
            'actual_value'      => $actual,
            'achievement_pct'   => $pct,
            'status'            => $status,
            'data_status'       => $dataStatus,
            'incomplete_reason' => $dataStatus === 'incomplete' ? $reason : null,
            'submitted_at'      => now(),
        ];
        foreach (['note', 'note_ar'] as $f) {
            if (array_key_exists($f, $data)) $attrs[$f] = $data[$f];
        }

        return KpiEntry::updateOrCreate(
            ['kpi_id' => $kpi->id, 'year' => $year, 'month' => $month],
            $attrs
        );
    }
}
