<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Kpi;
use App\Models\KpiEntry;
use App\Models\User;
use App\Services\AccessScope;
use App\Services\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** In the last days of the month, tells each person which of their KPIs still have no actual value. Once per person and month. */
class RemindMissingActuals extends Command
{
    protected $signature = 'notifications:remind-actuals {--force : Run on any day of the month}';
    protected $description = 'Remind people to enter missing KPI actuals for the current month';

    public function handle(AccessScope $access): int
    {
        $now = now();
        if (! $this->option('force') && $now->day < $now->daysInMonth - 4) {
            $this->info('Not the end of the month yet.');
            return self::SUCCESS;
        }

        $sent = 0;

        foreach (Company::where('is_active', true)->get() as $company) {
            $targeted = DB::table('kpi_targets')->where('company_id', $company->id)
                ->where('year', $now->year)->where('month', $now->month)->pluck('kpi_id');

            $entered = KpiEntry::withoutGlobalScopes()->where('company_id', $company->id)
                ->where('year', $now->year)->where('month', $now->month)->whereNotNull('actual_value')->pluck('kpi_id');

            $missing = Kpi::withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', true)
                ->whereIn('id', $targeted->diff($entered)->all())->get(['id', 'department_id']);

            if ($missing->isEmpty()) continue;

            $users = User::where('company_id', $company->id)->where('is_super_admin', false)->where('is_active', true)->get()
                ->filter(fn (User $u) => $u->hasPermission('kpis.enter'));

            foreach ($users as $user) {
                $ids = $access->editIds($user); // null = every department
                $mine = $ids === null ? $missing : $missing->filter(fn ($k) => $k->department_id !== null && in_array((int) $k->department_id, $ids, true));

                if ($mine->isEmpty()) continue;

                                Notifier::to(
                    $user, 'reminders',
                    'Missing KPI values this month',
                    ':count KPI(s) still have no actual value this month.',
                    '/kpi-entry', 'warning',
                    'reminder:'.$now->year.'-'.$now->month,
                    ['count' => $mine->count()]
                );
                $sent++;
            }
        }

        $this->info("Reminders sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}