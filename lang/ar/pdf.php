<?php

// تسميات تقارير PDF (resources/views/pdf). يجب أن تتطابق المفاتيح مع lang/en/pdf.php.
return [
    'generated' => 'تاريخ الإصدار',
    'page' => 'صفحة',
    'page_of' => 'من',
    'through' => 'حتى :period',
    'no_data' => 'لا توجد بيانات لهذه الفترة.',
    'restricted_note' => 'يعرض إدارتك فقط:',

    'report' => [
        'dashboard' => 'لوحة المؤشرات التنفيذية',
        'objectives' => 'الأهداف الاستراتيجية',
        'departments' => 'أداء الإدارات',
        'team' => 'أداء الفريق والموظفين',
    ],

    'status' => ['on_track' => 'ضمن المسار', 'at_risk' => 'معرّض للخطر', 'behind' => 'متأخر', 'not_entered' => 'لم تُدخل بيانات'],

    'overall_month' => 'النتيجة الإجمالية (الشهر)',
    'overall_ytd' => 'النتيجة الإجمالية (منذ بداية السنة)',
    'kpis_total' => 'المؤشرات',
    'objectives_total' => 'الأهداف',
    'data_entered' => 'البيانات المُدخلة',
    'perspective_scorecard' => 'محاور بطاقة الأداء المتوازن',
    'trend_title' => 'اتجاه النتيجة الشهرية',
    'worst_kpis' => 'مؤشرات تحتاج إلى اهتمام',
    'incomplete_title' => 'بيانات غير مكتملة هذا الشهر',
    'no_perspective' => 'بدون محور',
    'dept_rollup_note' => 'تشمل نتيجة الإدارة الإدارات الفرعية التابعة لها. النتائج مقيّدة بالحد المحدد في إعدادات الشركة.',

    'col' => [
        'perspective' => 'المحور', 'objective' => 'الهدف', 'department' => 'الإدارة', 'kpi' => 'المؤشر', 'kpis' => 'المؤشرات',
        'code' => 'الرمز', 'weight' => 'الوزن', 'month' => 'الشهر', 'ytd' => 'منذ بداية السنة', 'mix' => 'مرتفع / متوسط / منخفض',
        'reason' => 'السبب', 'count' => 'العدد', 'status' => 'الحالة', 'date' => 'التاريخ', 'user' => 'المستخدم', 'change' => 'التغيير', 'values' => 'القديم ← الجديد',
    ],

    'kpi' => [
        'score_month' => 'النتيجة (:month)', 'annual_target' => 'المستهدف السنوي', 'weight' => 'الوزن', 'details' => 'تفاصيل المؤشر',
        'owner' => 'المسؤول', 'type' => 'النوع', 'frequency' => 'التكرار', 'direction' => 'الاتجاه', 'thresholds' => 'حدّا الأحمر / الأصفر',
        'description' => 'الوصف', 'monthly' => 'الأداء الشهري', 'target' => 'المستهدف', 'actual' => 'الفعلي',
        'achievement' => 'نسبة الإنجاز', 'note' => 'ملاحظة', 'incomplete' => 'غير مكتمل', 'history' => 'سجل التغييرات',
        'created_by' => 'أنشأه :name بتاريخ :date',
    ],
    'kpi_type' => ['strategic' => 'استراتيجي', 'operational' => 'تشغيلي'],
    'frequency' => ['monthly' => 'شهري', 'quarterly' => 'ربع سنوي', 'yearly' => 'سنوي'],
    'direction' => ['higher_is_better' => 'الأعلى أفضل', 'lower_is_better' => 'الأقل أفضل'],

    'actions' => [
        'kpi_created' => 'إنشاء المؤشر', 'kpi_updated' => 'تعديل المؤشر', 'entry_created' => 'إضافة قيمة', 'entry_updated' => 'تعديل قيمة',
        'entry_deleted' => 'حذف قيمة', 'target_created' => 'تحديد مستهدف', 'target_updated' => 'تعديل مستهدف', 'target_deleted' => 'حذف مستهدف',
    ],
    'fields' => [
        'code' => 'الرمز', 'name' => 'الاسم', 'name_ar' => 'الاسم العربي', 'year' => 'السنة', 'strategic_objective_id' => 'الهدف',
        'bsc_perspective_id' => 'المحور', 'department_id' => 'الإدارة', 'owner_id' => 'المسؤول', 'type' => 'النوع', 'frequency' => 'التكرار',
        'direction' => 'الاتجاه', 'value_type' => 'نوع القيمة', 'unit' => 'الوحدة', 'weight' => 'الوزن', 'annual_target' => 'المستهدف السنوي',
        'threshold_red' => 'حد الأحمر', 'threshold_yellow' => 'حد الأصفر', 'has_recovery_target' => 'مستهدف التعافي', 'is_active' => 'نشط',
        'actual_value' => 'القيمة الفعلية', 'data_status' => 'حالة البيانات', 'incomplete_reason' => 'سبب عدم الاكتمال', 'note' => 'ملاحظة', 'note_ar' => 'الملاحظة العربية',
        'target_value' => 'القيمة المستهدفة',
    ],

    'team' => [
        'people' => 'الأشخاص أصحاب المؤشرات', 'data_entry' => 'إدخال البيانات', 'missing' => ':n أشهر ناقصة', 'teams' => 'الفرق', 'manager' => 'المدير',
        'members' => 'الأعضاء', 'people_title' => 'الأشخاص', 'unassigned' => 'مؤشرات بلا مسؤول', 'overdue' => 'متأخرة', 'person' => 'الشخص',
        'projects' => 'المشاريع', 'initiatives' => 'المبادرات', 'tasks' => 'المهام',
    ],
    'role' => ['admin' => 'مدير النظام', 'manager' => 'مدير', 'employee' => 'موظف', 'viewer' => 'مطّلع'],

    'months' => [1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'],
    'months_short' => [1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'],
];
