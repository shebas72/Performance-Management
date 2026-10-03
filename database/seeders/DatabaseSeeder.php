<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Company;
use App\Models\User;
use App\Models\StrategyHouse;
use App\Models\CoreValue;
use App\Models\BscPerspective;
use App\Models\StrategicObjective;
use App\Models\Department;
use App\Models\Kpi;
use App\Models\KpiTarget;
use App\Models\KpiEntry;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Company ─────────────────────────────────────────────────
        $company = Company::create([
            'name'              => 'Ministry of Digital Economy',
            'name_ar'           => 'وزارة الاقتصاد الرقمي',
            'slug'              => 'ministry-digital-economy',
            'timezone'          => 'Asia/Riyadh',
            'default_language'  => 'en',
            'plan'              => 'enterprise',
            'app_mode'          => 'standalone',
            'fiscal_year_start' => '01-01',
            'is_active'         => true,
        ]);

        // ── 2. Users ────────────────────────────────────────────────────
        $admin = User::create([
            'company_id'   => $company->id,
            'name'         => 'Ahmed Al-Rashid',
            'name_ar'      => 'أحمد الراشد',
            'email'        => 'admin@spms.test',
            'password'     => Hash::make('password'),
            'role'         => 'company_admin',
            'job_title'    => 'Chief Strategy Officer',
            'job_title_ar' => 'مدير الاستراتيجية التنفيذي',
        ]);

        $managers = [];
        $managerData = [
            ['Sara Al-Mutairi',  'سارة المطيري',  'Financial Manager',      'مدير مالي'],
            ['Khalid Al-Otaibi', 'خالد العتيبي',  'Customer Relations Mgr', 'مدير علاقات العملاء'],
            ['Noura Al-Dosari',  'نورة الدوسري',  'Operations Manager',     'مدير العمليات'],
            ['Faisal Al-Harbi',  'فيصل الحربي',   'HR & Learning Manager',  'مدير الموارد البشرية'],
        ];
        foreach ($managerData as $i => [$name, $nameAr, $title, $titleAr]) {
            $managers[] = User::create([
                'company_id'   => $company->id,
                'name'         => $name,
                'name_ar'      => $nameAr,
                'email'        => 'manager' . ($i + 1) . '@spms.test',
                'password'     => Hash::make('password'),
                'role'         => 'dept_manager',
                'job_title'    => $title,
                'job_title_ar' => $titleAr,
            ]);
        }

        // ── 3. Strategy House ───────────────────────────────────────────
        $house = StrategyHouse::create([
            'company_id' => $company->id,
            'year'       => 2025,
            'mission'    => 'To drive digital transformation and enable a knowledge-based economy that enhances quality of life.',
            'mission_ar' => 'قيادة التحول الرقمي وتمكين اقتصاد المعرفة لتعزيز جودة الحياة.',
            'vision'     => 'A leading digital nation by 2030.',
            'vision_ar'  => 'دولة رقمية رائدة بحلول عام 2030.',
            'is_active'  => true,
        ]);

        $coreValuesData = [
            ['Innovation',    'الابتكار',    '#534AB7'],
            ['Excellence',    'التميز',      '#0F6E56'],
            ['Transparency',  'الشفافية',    '#185FA5'],
            ['Collaboration', 'التعاون',     '#854F0B'],
            ['Integrity',     'النزاهة',     '#993C1D'],
        ];
        foreach ($coreValuesData as $i => [$name, $nameAr, $color]) {
            CoreValue::create([
                'company_id'        => $company->id,
                'strategy_house_id' => $house->id,
                'name'              => $name,
                'name_ar'           => $nameAr,
                'color'             => $color,
                'sort_order'        => $i,
            ]);
        }

        // ── 4. BSC Perspectives ─────────────────────────────────────────
        $perspectives = [];
        $perspData = [
            ['Financial',              'المالي',            'FIN', 30, '#0F6E56'],
            ['Customer',               'العملاء',           'CUS', 30, '#534AB7'],
            ['Internal Process',       'العمليات الداخلية', 'INT', 25, '#185FA5'],
            ['People, Learning & Dev', 'الأفراد والتعلم',   'PPL', 15, '#854F0B'],
        ];
        foreach ($perspData as $i => [$name, $nameAr, $code, $weight, $color]) {
            $perspectives[$code] = BscPerspective::create([
                'company_id' => $company->id,
                'name'       => $name,
                'name_ar'    => $nameAr,
                'code'       => $code,
                'color'      => $color,
                'weight'     => $weight,
                'sort_order' => $i,
            ]);
        }

        // ── 5. Departments ──────────────────────────────────────────────
        $deptData = [
            ['Finance & Budget',       'المالية والميزانية',     'FIN', '#0F6E56'],
            ['Customer Experience',    'تجربة العملاء',          'CX',  '#534AB7'],
            ['Digital Infrastructure', 'البنية التحتية الرقمية', 'DI',  '#185FA5'],
            ['Human Resources',        'الموارد البشرية',        'HR',  '#854F0B'],
            ['Innovation & Research',  'الابتكار والبحث',        'IR',  '#993C1D'],
            ['Cybersecurity',          'الأمن السيبراني',        'CS',  '#2C3E50'],
            ['Strategy & Planning',    'الاستراتيجية والتخطيط',  'SP',  '#8E44AD'],
            ['Government Services',    'الخدمات الحكومية',       'GS',  '#16A085'],
            ['Data & Analytics',       'البيانات والتحليلات',    'DA',  '#E67E22'],
        ];
        $departments = [];
        foreach ($deptData as $i => [$name, $nameAr, $code, $color]) {
            $departments[$code] = Department::create([
                'company_id' => $company->id,
                'manager_id' => $managers[min($i, count($managers) - 1)]->id,
                'name'       => $name,
                'name_ar'    => $nameAr,
                'code'       => $code,
                'color'      => $color,
                'sort_order' => $i,
                'is_active'  => true,
            ]);
        }

        // Update users with department_ids
        $managers[0]->update(['department_id' => $departments['FIN']->id]);
        $managers[1]->update(['department_id' => $departments['CX']->id]);
        $managers[2]->update(['department_id' => $departments['DI']->id]);
        $managers[3]->update(['department_id' => $departments['HR']->id]);

        // ── 6. Strategic Objectives ─────────────────────────────────────
        $objectives = [];
        $objData = [
            ['FIN', 'SO-FIN-01', 'Optimize Budget Utilization',          'تحسين استخدام الميزانية',         10],
            ['FIN', 'SO-FIN-02', 'Increase Revenue Diversification',     'تنويع مصادر الإيرادات',           10],
            ['FIN', 'SO-FIN-03', 'Reduce Operational Costs',             'تخفيض التكاليف التشغيلية',        10],
            ['CUS', 'SO-CUS-01', 'Enhance Digital Service Satisfaction', 'تعزيز رضا الخدمات الرقمية',       10],
            ['CUS', 'SO-CUS-02', 'Increase Digital Adoption Rate',       'رفع معدل التبني الرقمي',          10],
            ['CUS', 'SO-CUS-03', 'Reduce Service Delivery Time',         'تقليل وقت تقديم الخدمة',          10],
            ['INT', 'SO-INT-01', 'Streamline Internal Processes',        'تبسيط العمليات الداخلية',          8],
            ['INT', 'SO-INT-02', 'Strengthen Cybersecurity Posture',     'تعزيز الأمن السيبراني',            9],
            ['INT', 'SO-INT-03', 'Accelerate Digital Transformation',    'تسريع التحول الرقمي',              8],
            ['PPL', 'SO-PPL-01', 'Build Digital Capabilities',           'بناء الكفاءات الرقمية',            5],
            ['PPL', 'SO-PPL-02', 'Improve Employee Engagement',          'تحسين انخراط الموظفين',            5],
            ['PPL', 'SO-PPL-03', 'Develop Leadership Pipeline',          'تطوير قيادات المستقبل',            5],
        ];
        foreach ($objData as [$perspCode, $code, $name, $nameAr, $weight]) {
            $objectives[$code] = StrategicObjective::create([
                'company_id'         => $company->id,
                'bsc_perspective_id' => $perspectives[$perspCode]->id,
                'strategy_house_id'  => $house->id,
                'code'               => $code,
                'name'               => $name,
                'name_ar'            => $nameAr,
                'weight'             => $weight,
                'year'               => 2025,
                'is_active'          => true,
            ]);
        }

        // ── 7. KPIs with monthly targets & entries ──────────────────────
        // [obj_code, dept_code, code, name, name_ar, unit, direction, monthly_target]
        $kpiData = [
            ['SO-FIN-01', 'FIN', 'KPI-1.1.1', 'Budget Utilization Rate',         'معدل استخدام الميزانية',           '%',   'higher_is_better', 95],
            ['SO-FIN-01', 'FIN', 'KPI-1.1.2', 'Cost per Service Transaction',    'تكلفة كل معاملة خدمية',            'SAR', 'lower_is_better',  50],
            ['SO-FIN-02', 'FIN', 'KPI-1.2.1', 'Non-Oil Revenue Growth Rate',     'معدل نمو الإيرادات غير النفطية',   '%',   'higher_is_better', 15],
            ['SO-CUS-01', 'CX',  'KPI-2.1.1', 'Customer Satisfaction Index',     'مؤشر رضا العملاء',                 '%',   'higher_is_better', 90],
            ['SO-CUS-01', 'CX',  'KPI-2.1.2', 'Net Promoter Score',              'مؤشر صافي الترويج',                'pts', 'higher_is_better', 75],
            ['SO-CUS-02', 'GS',  'KPI-2.2.1', 'Digital Services Adoption Rate',  'معدل تبني الخدمات الرقمية',        '%',   'higher_is_better', 80],
            ['SO-CUS-03', 'GS',  'KPI-2.3.1', 'Average Service Delivery Time',   'متوسط وقت إنجاز الخدمة',          'days','lower_is_better',   3],
            ['SO-INT-01', 'DI',  'KPI-3.1.1', 'Process Automation Rate',         'معدل أتمتة العمليات',              '%',   'higher_is_better', 70],
            ['SO-INT-02', 'CS',  'KPI-3.2.1', 'Cybersecurity Compliance Rate',   'معدل الامتثال للأمن السيبراني',    '%',   'higher_is_better', 95],
            ['SO-INT-03', 'IR',  'KPI-3.3.1', 'Digital Transformation Projects', 'مشاريع التحول الرقمي المكتملة',    '%',   'higher_is_better', 85],
            ['SO-PPL-01', 'HR',  'KPI-4.1.1', 'Digital Skills Training Hours',   'ساعات التدريب على المهارات الرقمية','hrs','higher_is_better', 40],
            ['SO-PPL-02', 'HR',  'KPI-4.2.1', 'Employee Engagement Score',       'درجة انخراط الموظفين',             '%',   'higher_is_better', 80],
        ];

        // Monthly achievement % values (matching the PDF sample data)
        // These are achievement percentages, not raw values
        $monthlyAchievements = [
            'KPI-1.1.1' => [88, 70, 89, 92, 91, 75, 78, 80],
            'KPI-1.1.2' => [85, 72, 88, 90, 89, 74, 77, 80],
            'KPI-1.2.1' => [82, 68, 85, 88, 87, 70, 75, 77],
            'KPI-2.1.1' => [80, 77, 82, 85, 85, 79, 80, 81],
            'KPI-2.1.2' => [75, 72, 78, 80, 82, 76, 78, 79],
            'KPI-2.2.1' => [78, 65, 82, 85, 88, 72, 76, 80],
            'KPI-2.3.1' => [85, 70, 88, 90, 91, 75, 79, 82],
            'KPI-3.1.1' => [72, 65, 78, 82, 80, 68, 72, 75],
            'KPI-3.2.1' => [90, 82, 92, 95, 94, 88, 90, 91],
            'KPI-3.3.1' => [80, 72, 85, 88, 87, 75, 78, 80],
            'KPI-4.1.1' => [55, 50, 60, 65, 63, 52, 57, 59],
            'KPI-4.2.1' => [75, 70, 78, 80, 79, 72, 76, 77],
        ];

        foreach ($kpiData as [$objCode, $deptCode, $code, $name, $nameAr, $unit, $direction, $monthlyTarget]) {
            $obj  = $objectives[$objCode];
            $dept = $departments[$deptCode];

            $kpi = Kpi::create([
                'company_id'             => $company->id,
                'strategic_objective_id' => $obj->id,
                'bsc_perspective_id'     => $obj->bsc_perspective_id,
                'department_id'          => $dept->id,
                'owner_id'               => $managers[0]->id,
                'created_by'             => $admin->id,
                'code'                   => $code,
                'name'                   => $name,
                'name_ar'                => $nameAr,
                'type'                   => 'strategic',
                'frequency'              => 'monthly',
                'direction'              => $direction,
                'value_type'             => match($unit) {
                    '%'   => 'percentage',
                    'SAR' => 'currency',
                    default => 'number',
                },
                'unit'             => $unit,
                'weight'           => 10,
                'annual_target'    => $monthlyTarget * 12,
                'year'             => 2025,
                'threshold_red'    => 60,
                'threshold_yellow' => 80,
                'is_active'        => true,
            ]);

            // Create 12 monthly targets
            for ($m = 1; $m <= 12; $m++) {
                KpiTarget::create([
                    'kpi_id'       => $kpi->id,
                    'company_id'   => $company->id,
                    'year'         => 2025,
                    'month'        => $m,
                    'target_value' => $monthlyTarget,
                ]);
            }

            // Create actual entries for months 1–8
            // achievement% values are stored directly (max 999.9999 in DB)
            // actual_value is derived from achievement% × target
            $achievements = $monthlyAchievements[$code] ?? [];

            foreach ($achievements as $i => $achievementPct) {
                $month = $i + 1;

                // Derive actual value from achievement percentage
                if ($direction === 'higher_is_better') {
                    $actualValue = round(($achievementPct / 100) * $monthlyTarget, 4);
                } else {
                    // lower_is_better: achievement = target/actual → actual = target/achievement
                    $actualValue = round($monthlyTarget / ($achievementPct / 100), 4);
                }

                // Cap achievement_pct to fit decimal(7,4) — max 999.9999
                $storedAchievement = min(round($achievementPct, 4), 999.9999);

                $status = match(true) {
                    $achievementPct >= 80 => 'on_track',
                    $achievementPct >= 60 => 'at_risk',
                    default               => 'behind',
                };

                KpiEntry::create([
                    'kpi_id'          => $kpi->id,
                    'company_id'      => $company->id,
                    'logged_by'       => $managers[0]->id,
                    'year'            => 2025,
                    'month'           => $month,
                    'actual_value'    => $actualValue,
                    'achievement_pct' => $storedAchievement,
                    'status'          => $status,
                    'data_status'     => 'complete',
                    'submitted_at'    => now()->setYear(2025)->startOfYear()->addMonths($month - 1),
                ]);
            }
        }

        $this->command->info('');
        $this->command->info('✅ SPMS demo data seeded successfully.');
        $this->command->info('   Login:   admin@spms.test / password');
        $this->command->info('   Company: Ministry of Digital Economy');
        $this->command->info('   KPIs:    12 strategic KPIs with 8 months of data');
    }
}