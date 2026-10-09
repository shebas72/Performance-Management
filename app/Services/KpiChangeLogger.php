<?php

namespace App\Services;

use App\Models\Kpi;
use App\Models\KpiChangeLog;
use App\Models\KpiEntry;
use App\Models\KpiTarget;
use Illuminate\Support\Facades\DB;

/** Writes the KPI audit trail: one row per changed field. */
class KpiChangeLogger
{
    public const ENTRY_FIELDS = ['actual_value', 'data_status', 'incomplete_reason', 'note', 'note_ar'];

    private const KPI_FIELDS = [
        'code', 'name', 'name_ar', 'year', 'strategic_objective_id', 'bsc_perspective_id', 'department_id', 'owner_id',
        'type', 'frequency', 'direction', 'value_type', 'unit', 'weight', 'annual_target',
        'threshold_red', 'threshold_yellow', 'has_recovery_target', 'is_active',
    ];

    private const NUMERIC = ['actual_value', 'target_value', 'annual_target', 'weight', 'threshold_red', 'threshold_yellow'];
    private const BOOLEAN = ['has_recovery_target', 'is_active'];
    private const LOOKUPS = [
        'department_id'          => 'departments',
        'owner_id'               => 'users',
        'strategic_objective_id' => 'strategic_objectives',
        'bsc_perspective_id'     => 'bsc_perspectives',
    ];

    public function kpiCreated(Kpi $kpi, ?int $userId): void
    {
        $this->write($kpi->company_id, $kpi->id, $userId, 'kpi_created');
    }

    /** $before is the model's raw attributes captured before the save. */
    public function kpiUpdated(Kpi $kpi, array $before, ?int $userId): void
    {
        $changes = $kpi->getChanges();
        foreach (self::KPI_FIELDS as $f) {
            if (! array_key_exists($f, $changes)) continue;
            $this->diff($kpi->company_id, $kpi->id, $userId, 'kpi_updated', $f, $before[$f] ?? null, $kpi->getAttributes()[$f] ?? null);
        }
    }

    /** $before = tracked entry fields before the save, or null when the entry is new. */
    public function entrySaved(Kpi $kpi, ?array $before, KpiEntry $entry, ?int $userId): void
    {
        $after = $entry->only(self::ENTRY_FIELDS);

        foreach (self::ENTRY_FIELDS as $f) {
            if ($before === null) {
                if ($this->norm($f, $after[$f] ?? null) !== null) {
                    $this->write($kpi->company_id, $kpi->id, $userId, 'entry_created', $f, null, $after[$f] ?? null, $entry->year, $entry->month);
                }
            } else {
                $this->diff($kpi->company_id, $kpi->id, $userId, 'entry_updated', $f, $before[$f] ?? null, $after[$f] ?? null, $entry->year, $entry->month);
            }
        }
    }

    public function entryDeleted(KpiEntry $entry, ?int $userId): void
    {
        $this->write($entry->company_id, $entry->kpi_id, $userId, 'entry_deleted', 'actual_value', $entry->actual_value, null, $entry->year, $entry->month);
    }

    public function targetSaved(KpiTarget $target, ?array $before, ?int $userId): void
    {
        $action = $before === null ? 'target_created' : 'target_updated';
        $this->diff($target->company_id, $target->kpi_id, $userId, $action, 'target_value', $before['target_value'] ?? null, $target->getAttributes()['target_value'] ?? null, $target->year, $target->month);
    }

    public function targetDeleted(KpiTarget $target, ?int $userId): void
    {
        $this->write($target->company_id, $target->kpi_id, $userId, 'target_deleted', 'target_value', $target->getAttributes()['target_value'] ?? null, null, $target->year, $target->month);
    }

    private function diff($cid, $kpiId, ?int $userId, string $action, string $field, $old, $new, $year = null, $month = null): void
    {
        if ($this->norm($field, $old) === $this->norm($field, $new)) return;
        $this->write($cid, $kpiId, $userId, $action, $field, $old, $new, $year, $month);
    }

    private function write($cid, $kpiId, ?int $userId, string $action, ?string $field = null, $old = null, $new = null, $year = null, $month = null): void
    {
        KpiChangeLog::create([
            'company_id'   => $cid,
            'kpi_id'       => $kpiId,
            'user_id'      => $userId,
            'action'       => $action,
            'field'        => $field,
            'period_year'  => $year,
            'period_month' => $month,
            'old_value'    => $this->display($field, $old),
            'new_value'    => $this->display($field, $new),
        ]);
    }

    /** Canonical form used for comparing before and after. */
    private function norm(?string $field, $v): ?string
    {
        if ($v === null || $v === '') return null;
        if (in_array($field, self::NUMERIC, true)) {
            return rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
        }
        if (in_array($field, self::BOOLEAN, true)) return $v ? '1' : '0';

        return (string) $v;
    }

    /** What is stored and shown: normalised, with ids resolved to names. */
    private function display(?string $field, $v): ?string
    {
        $v = $this->norm($field, $v);
        if ($v !== null && isset(self::LOOKUPS[$field])) {
            return DB::table(self::LOOKUPS[$field])->where('id', $v)->value('name') ?? "#{$v}";
        }

        return $v;
    }
}
