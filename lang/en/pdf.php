<?php

// Labels used by the PDF reports (resources/views/pdf). Keep keys identical in lang/ar/pdf.php.
return [
    'generated' => 'Generated',
    'page' => 'Page',
    'page_of' => 'of',
    'through' => 'Through :period',
    'no_data' => 'No data for this period.',
    'restricted_note' => 'Showing your departments only:',

    'report' => [
        'dashboard' => 'Executive Dashboard',
        'objectives' => 'Strategic Objectives',
        'departments' => 'Department Performance',
        'team' => 'Team & Employee Performance',
    ],

    'status' => ['on_track' => 'On track', 'at_risk' => 'At risk', 'behind' => 'Behind', 'not_entered' => 'Not entered'],

    'overall_month' => 'Overall score (month)',
    'overall_ytd' => 'Overall score (year to date)',
    'kpis_total' => 'KPIs',
    'objectives_total' => 'Objectives',
    'data_entered' => 'Data entered',
    'perspective_scorecard' => 'Balanced scorecard perspectives',
    'trend_title' => 'Monthly score trend',
    'worst_kpis' => 'KPIs needing attention',
    'incomplete_title' => 'Incomplete data this month',
    'no_perspective' => 'No perspective',
    'dept_rollup_note' => 'A department score includes its sub-departments. Scores are capped as set in the company settings.',

    'col' => [
        'perspective' => 'Perspective', 'objective' => 'Objective', 'department' => 'Department', 'kpi' => 'KPI', 'kpis' => 'KPIs',
        'code' => 'Code', 'weight' => 'Weight', 'month' => 'Month', 'ytd' => 'Year to date', 'mix' => 'High / Med / Low',
        'reason' => 'Reason', 'count' => 'Count', 'status' => 'Status', 'date' => 'Date', 'user' => 'User', 'change' => 'Change', 'values' => 'Old → New',
    ],

    'kpi' => [
        'score_month' => 'Score (:month)', 'annual_target' => 'Annual target', 'weight' => 'Weight', 'details' => 'KPI details',
        'owner' => 'Owner', 'type' => 'Type', 'frequency' => 'Frequency', 'direction' => 'Direction', 'thresholds' => 'Red / yellow thresholds',
        'description' => 'Description', 'monthly' => 'Monthly performance', 'target' => 'Target', 'actual' => 'Actual',
        'achievement' => 'Achievement', 'note' => 'Note', 'incomplete' => 'Incomplete', 'history' => 'Change history',
        'created_by' => 'Created by :name on :date',
    ],
    'kpi_type' => ['strategic' => 'Strategic', 'operational' => 'Operational'],
    'frequency' => ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'],
    'direction' => ['higher_is_better' => 'Higher is better', 'lower_is_better' => 'Lower is better'],

    'actions' => [
        'kpi_created' => 'KPI created', 'kpi_updated' => 'KPI updated', 'entry_created' => 'Entry added', 'entry_updated' => 'Entry updated',
        'entry_deleted' => 'Entry deleted', 'target_created' => 'Target set', 'target_updated' => 'Target updated', 'target_deleted' => 'Target removed',
    ],
    'fields' => [
        'code' => 'Code', 'name' => 'Name', 'name_ar' => 'Arabic name', 'year' => 'Year', 'strategic_objective_id' => 'Objective',
        'bsc_perspective_id' => 'Perspective', 'department_id' => 'Department', 'owner_id' => 'Owner', 'type' => 'Type', 'frequency' => 'Frequency',
        'direction' => 'Direction', 'value_type' => 'Value type', 'unit' => 'Unit', 'weight' => 'Weight', 'annual_target' => 'Annual target',
        'threshold_red' => 'Red threshold', 'threshold_yellow' => 'Yellow threshold', 'has_recovery_target' => 'Recovery target', 'is_active' => 'Active',
        'actual_value' => 'Actual value', 'data_status' => 'Data status', 'incomplete_reason' => 'Incomplete reason', 'note' => 'Note', 'note_ar' => 'Arabic note',
        'target_value' => 'Target value',
    ],

    'team' => [
        'people' => 'People with KPIs', 'data_entry' => 'Data entry', 'missing' => ':n months missing', 'teams' => 'Teams', 'manager' => 'Manager',
        'members' => 'Members', 'people_title' => 'People', 'unassigned' => 'KPIs without an owner', 'overdue' => 'overdue', 'person' => 'Person',
        'projects' => 'Projects', 'initiatives' => 'Initiatives', 'tasks' => 'Tasks',
    ],
    'role' => ['admin' => 'Admin', 'manager' => 'Manager', 'employee' => 'Employee', 'viewer' => 'Viewer'],

    'months' => [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'],
    'months_short' => [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'],
];
