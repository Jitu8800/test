<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Columns with aliases (safe from ambiguity)
$aColumns = [
    db_prefix().'projects.name as project_name',
    db_prefix().'projects.start_date as project_start_date',
    db_prefix().'projects.deadline as project_deadline',
    db_prefix().'projects.status as project_status',
];

// Primary index column
$sIndexColumn     = 'id';
$sTable           = db_prefix().'projects';

// Additional columns you need
$additionalSelect = [
    db_prefix().'projects.id as project_id',
];

// Join with clients table
$join = [
    'JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'projects.clientid',
];

// Where conditions
$where    = [];
$staff_id = get_staff_user_id();

if ($this->ci->input->post('staff_id')) {
    $staff_id = $this->ci->input->post('staff_id');
} else {
    // Exclude finished & canceled projects if no staff_id
    $where[] = 'AND ' . db_prefix() . 'projects.status != 4 AND ' . db_prefix() . 'projects.status != 5';
}

$where[] = 'AND ' . db_prefix() . 'projects.id IN (
    SELECT project_id FROM ' . db_prefix() . 'project_members 
    WHERE staff_id=' . $this->ci->db->escape_str($staff_id) . '
)';

// Fetch DataTables result
$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);

$output  = $result['output'];
$rResult = $result['rResult'];

// Format rows
foreach ($rResult as $aRow) {
    $row = [];

    // Loop in same order as $aColumns
    foreach (['project_name', 'project_start_date', 'project_deadline', 'project_status'] as $col) {
        $_data = $aRow[$col];

        if ($col === 'project_start_date' || $col === 'project_deadline') {
            $_data = _d($_data); // format date
        } elseif ($col === 'project_name') {
            $_data = '<a href="' . admin_url('projects/view/' . $aRow['project_id']) . '">' . $_data . '</a>';
        } elseif ($col === 'project_status') {
            $status = get_project_status_by_id($_data);
            $_data  = '<span class="label label project-status-' . $_data . '" 
                        style="color:' . $status['color'] . ';
                               border:1px solid ' . $status['color'] . '">' 
                        . $status['name'] . '</span>';
        }

        $row[] = $_data;
    }

    $output['aaData'][] = $row;
}
