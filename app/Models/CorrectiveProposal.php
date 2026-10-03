<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectiveProposal extends Model
{
    protected $fillable = [
        'company_id', 'kpi_id', 'department_id',
        'submitted_by', 'reviewed_by',
        'title', 'title_ar', 'description', 'description_ar',
        'root_cause', 'root_cause_detail',
        'due_date', 'status', 'year', 'month',
    ];

    protected $casts = ['due_date' => 'date'];

    public function kpi(): BelongsTo        { return $this->belongsTo(Kpi::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function submitter(): BelongsTo  { return $this->belongsTo(User::class, 'submitted_by'); }
    public function reviewer(): BelongsTo   { return $this->belongsTo(User::class, 'reviewed_by'); }

    public static function rootCauseLabels(): array
    {
        return [
            'resources_shortage'   => ['en' => 'Resources Shortage',   'ar' => 'نقص الموارد'],
            'technical_challenges' => ['en' => 'Technical Challenges',  'ar' => 'تحديات تقنية'],
            'administrative'       => ['en' => 'Administrative',        'ar' => 'إدارية'],
            'lack_of_followup'     => ['en' => 'Lack of Follow-up',     'ar' => 'ضعف المتابعة'],
            'other'                => ['en' => 'Other',                 'ar' => 'أخرى'],
        ];
    }
}
