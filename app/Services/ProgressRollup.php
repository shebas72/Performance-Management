<?php

namespace App\Services;

use App\Models\ExecutionPlanTask;
use App\Models\Initiative;
use App\Models\ProgressUpdate;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Task -> initiative -> project roll-up.
 * An initiative with tasks gets the average task completion; a project with initiatives gets the average initiative completion.
 * The result is written as an 'auto' monthly update. A month that someone reported by hand ('manual') is never overwritten.
 */
class ProgressRollup
{
    public function initiative(Initiative $i): void
    {
        $avg = ExecutionPlanTask::where('initiative_id', $i->id)->avg('completion_pct');
        if ($avg !== null) {
            $this->snapshot('initiative', $i, (float) $avg);
        }
        $this->projectsOfInitiative($i->id);
    }

    public function projectsOfInitiative(int $initiativeId): void
    {
        $this->projects(DB::table('initiative_project')->where('initiative_id', $initiativeId)->pluck('project_id')->all());
    }

    public function projects(array $projectIds): void
    {
        foreach (Project::whereIn('id', $projectIds)->get() as $p) {
            $avg = Initiative::whereIn('id', DB::table('initiative_project')->where('project_id', $p->id)->select('initiative_id'))->avg('completion_pct');
            if ($avg !== null) {
                $this->snapshot('project', $p, (float) $avg);
            }
        }
    }

    /** Sets the current completion (and a matching status) from the most recent monthly update. */
    public function applyLatest(string $type, $subject): void
    {
        $latest = ProgressUpdate::where('subject_type', $type)->where('subject_id', $subject->id)->orderByDesc('year')->orderByDesc('month')->first();
        if (! $latest) {
            return;
        }
        $pct    = (float) $latest->completion_pct;
        $status = $subject->status;
        if ($status !== 'cancelled') {
            if ($pct >= 100) $status = 'completed';
            elseif ($status === 'completed') $status = 'in_progress';
            elseif ($pct > 0 && $status === 'not_started') $status = 'in_progress';
        }
        $subject->forceFill(['completion_pct' => $pct, 'status' => $status])->save();
    }

    private function snapshot(string $type, $subject, float $pct): void
    {
        [$year, $month] = $this->period($type, $subject);
        $keys = ['subject_type' => $type, 'subject_id' => $subject->id, 'year' => $year, 'month' => $month];

        $existing = ProgressUpdate::where($keys)->first();
        if (! $existing || $existing->source === 'auto') {
            ProgressUpdate::updateOrCreate($keys, ['company_id' => $subject->company_id, 'completion_pct' => round($pct, 2), 'source' => 'auto', 'note' => null]);
        }
        $this->applyLatest($type, $subject);
    }

    /** Current month for the current year; for other years, the last reported month (or December / January). */
    private function period(string $type, $subject): array
    {
        $now = now();
        $year = (int) $subject->year;
        if ($year === $now->year) {
            return [$year, $now->month];
        }
        $last = ProgressUpdate::where('subject_type', $type)->where('subject_id', $subject->id)->where('year', $year)->max('month');

        return [$year, (int) ($last ?: ($year < $now->year ? 12 : 1))];
    }
}
