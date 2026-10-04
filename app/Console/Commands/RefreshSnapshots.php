<?php

namespace App\Console\Commands;

use App\Services\SnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshSnapshots extends Command
{
    protected $signature = 'spms:snapshots {--company= : Company id} {--year= : Year} {--month= : Month 1-12}';
    protected $description = 'Recalculate performance_snapshots (company, perspective, objective, department scopes)';

    public function handle(SnapshotService $snapshots): int
    {
        $pairs = DB::table('kpis')->select('company_id', 'year')->distinct()
            ->when($this->option('company'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($this->option('year'), fn ($q, $v) => $q->where('year', $v))
            ->get();

        $months = $this->option('month') ? [(int) $this->option('month')] : range(1, 12);
        $n = 0;

        foreach ($pairs as $p) {
            foreach ($months as $m) {
                $snapshots->refreshMonth((int) $p->company_id, (int) $p->year, $m);
                $n++;
            }
            $this->line("company {$p->company_id} / {$p->year}: done");
        }

        $this->info("Refreshed {$n} company-month snapshot sets.");
        return self::SUCCESS;
    }
}
