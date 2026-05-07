<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->model('gdpr_model');

$lockAfterConvert = get_option('lead_lock_after_convert_to_customer');
$has_permission_delete = has_permission('leads', '', 'delete');
$custom_fields = get_table_custom_fields('leads');
$consentLeads = get_option('gdpr_enable_consent_for_leads');
$statuses = $CI->leads_model->get_status();

/* ================================================================
   ALWAYS enable big selects when custom fields are involved
   (no @ suppression — errors must surface)
================================================================ */
if (!empty($custom_fields)) {
    $CI->db->query('SET SESSION SQL_BIG_SELECTS = 1');
}

/* ================================================================
   Preload Sub Status (unchanged — already efficient)
================================================================ */
$allSubStatuses = $CI->db->select('id, status_id, sub_name')
    ->from(db_prefix().'leads_substatus')
    ->get()->result_array();

$subStatusByStatus = [];   // ← was $substatusesByStatus (wrong name)
$subStatusById = [];   // ← was missing entirely

foreach ($allSubStatuses as $sub) {
    $subStatusByStatus[$sub['status_id']][] = $sub;
    $subStatusById[$sub['id']] = $sub['sub_name'];
}

/* ================================================================
   Main Columns
================================================================ */
$aColumns = [
    '1',
    db_prefix().'leads.id as id',
    db_prefix().'leads.name as name',
    db_prefix().'leads.city as city',
    db_prefix().'leads.form_name as form_name',
    db_prefix().'leads.company as company',
    db_prefix().'leads.website as website',
    db_prefix().'leads.address as address',
    db_prefix().'leads.email as email',
    db_prefix().'leads.phonenumber as phonenumber',
    db_prefix().'leads.sub_status as sub_status_name',
    db_prefix().'staff.firstname as assigned_firstname',
    db_prefix().'leads_status.name as status_name',
    db_prefix().'leads_sources.name as source_name',
    db_prefix().'leads.lastcontact as lastcontact',
    db_prefix().'leads.dateadded as dateadded',
];

if (is_gdpr() && $consentLeads == '1') {
    $aColumns[] = '1';
}

$sIndexColumn = 'id';
$sTable = db_prefix().'leads';

/* ================================================================
   Joins

   FIX 1: is_converted was a correlated subquery (ran once per row).
   Now it's a LEFT JOIN on a pre-aggregated inline view — runs ONCE.

   Before: 18,000 rows = 18,000 extra SELECT COUNT queries
   After:  1 join against a tiny aggregated set
================================================================ */
$join = [
    // Staff name
    'LEFT JOIN '.db_prefix().'staff
        ON '.db_prefix().'staff.staffid = '.db_prefix().'leads.assigned',

    // Status name + color
    'LEFT JOIN '.db_prefix().'leads_status
        ON '.db_prefix().'leads_status.id = '.db_prefix().'leads.status',

    // Source name
    'LEFT JOIN '.db_prefix().'leads_sources
        ON '.db_prefix().'leads_sources.id = '.db_prefix().'leads.source',

    // FIX: is_converted as a single aggregated JOIN (not a correlated subquery per row)
    'LEFT JOIN (
        SELECT leadid, 1 AS is_converted
        FROM '.db_prefix().'clients
        WHERE leadid IS NOT NULL AND leadid > 0
        GROUP BY leadid
    ) AS converted_clients
        ON converted_clients.leadid = '.db_prefix().'leads.id',
];

/* ================================================================
   Custom Fields — unchanged logic, just cleaner formatting
   Each field still needs its own JOIN (unavoidable in this pattern).
   SQL_BIG_SELECTS above handles the optimizer limit.
================================================================ */
$customFieldsColumns = [];

foreach ($custom_fields as $key => $field) {
    $selectAs = is_cf_date($field) ? 'date_picker_cvalue_'.$key : 'cvalue_'.$key;

    $customFieldsColumns[] = $selectAs;
    $aColumns[] = 'ctable_'.$key.'.value as '.$selectAs;

    $join[] = 'LEFT JOIN '.db_prefix().'customfieldsvalues as ctable_'.$key.'
        ON ctable_'.$key.'.relid    = '.db_prefix().'leads.id
        AND ctable_'.$key.'.fieldto = "'.$field['fieldto'].'"
        AND ctable_'.$key.'.fieldid = '.(int) $field['id'];
}

/* ================================================================
   WHERE Filters
================================================================ */
$where = [];

$filter = $CI->input->post('custom_view');

if ($filter) {
    switch ($filter) {
        case 'lost':
            $where[] = db_prefix().'leads.lost = 1';
            break;
        case 'junk':
            $where[] = db_prefix().'leads.junk = 1';
            break;
        case 'not_assigned':
            $where[] = db_prefix().'leads.assigned = 0';
            break;
        case 'contacted_today':
            $where[] = db_prefix().'leads.lastcontact LIKE "'.date('Y-m-d').'%"';
            break;
        case 'created_today':
            $where[] = db_prefix().'leads.dateadded LIKE "'.date('Y-m-d').'%"';
            break;
        case 'public':
            $where[] = db_prefix().'leads.is_public = 1';
            break;
    }
}

/* Always exclude lost/junk unless specifically viewing them */
if (!$filter || !in_array($filter, ['lost', 'junk'])) {
    $where[] = 'AND '.db_prefix().'leads.lost = 0';
    $where[] = 'AND '.db_prefix().'leads.junk = 0';
}

// /* Assigned filter */
// if (has_permission('leads', '', 'view') && $CI->input->post('assigned')) {
//     $where[] = 'AND ' . db_prefix() . 'leads.assigned = ' . (int) $CI->input->post('assigned');
// }

/* Assigned filter (multiple) */
$postAssigned = $CI->input->post('assigned');

if (has_permission('leads', '', 'view') && $postAssigned && is_array($postAssigned)) {
    $assignedEscaped = implode(',', array_map('intval', $postAssigned));

    $where[] = 'AND '.db_prefix().'leads.assigned IN ('.$assignedEscaped.')';
}

$postStatus = $CI->input->post('status');

$postStatus = (array) $postStatus;

$hasEmpty = in_array('', $postStatus, true);

if ($hasEmpty) {

    $where[] = 'AND '.db_prefix().'leads.assigned = 0';

} else {

    $postStatus = array_map('intval', $postStatus);

    if (!empty($postStatus)) {
        $statusesEscaped = implode(',', $postStatus);
        $where[] = 'AND '.db_prefix().'leads.status IN ('.$statusesEscaped.')';
    }
}

// if ($postStatus && is_array($postStatus)) {
//     $statusesEscaped = implode(',', array_map('intval', $postStatus));
//     $where[] = 'AND '.db_prefix().'leads.status IN('.$statusesEscaped.')';
// }

/* Sub status filter */
$postSubStatus = $CI->input->post('sub_status');
if ($postSubStatus && is_array($postSubStatus)) {
    $substatusesEscaped = implode(',', array_map('intval', $postSubStatus));
    $where[] = 'AND '.db_prefix().'leads.sub_status IN('.$substatusesEscaped.')';
}

/* Source filter */
if ($CI->input->post('source')) {
    $where[] = 'AND '.db_prefix().'leads.source = '.(int) $CI->input->post('source');
}

/* Card status filter */
if ($CI->input->post('card_status')) {
    $where[] = 'AND '.db_prefix().'leads.card_status = "'.$CI->db->escape_str($CI->input->post('card_status')).'"';
}

/* Payment mode filter */
if ($CI->input->post('payment_mode')) {
    $where[] = 'AND '.db_prefix().'leads.payment_mode = "'.$CI->db->escape_str($CI->input->post('payment_mode')).'"';
}

/* Date range filter */
if ($CI->input->post('date_range')) {
    $dateRange = $CI->input->post('date_range');

    if (strpos($dateRange, ' to ') !== false) {
        [$startDate, $endDate] = explode(' to ', $dateRange);

        // Use indexed column correctly — BETWEEN is index-friendly
        $where[] = 'AND '.db_prefix().'leads.dateadded BETWEEN "'
            .$CI->db->escape_str(trim($startDate)).' 00:00:00" AND "'
            .$CI->db->escape_str(trim($endDate)).' 23:59:59"';
    }
}

/* Permission filter — non-admins only see their own / public leads */
if (!has_permission('leads', '', 'view')) {
    $userId = (int) get_staff_user_id();
    $where[] = 'AND ('.db_prefix().'leads.assigned = '.$userId
        .' OR '.db_prefix().'leads.addedfrom = '.$userId
        .' OR '.db_prefix().'leads.is_public = 1)';
}

/* ================================================================
   Additional Columns

   FIX 2: is_converted now reads from the JOIN above (0 extra queries)
   instead of the old correlated subquery.
================================================================ */
$additionalColumns = [
    db_prefix().'leads.junk',
    db_prefix().'leads.lost',
    'color',
    db_prefix().'leads.status',
    db_prefix().'leads.sub_status',
    db_prefix().'leads.assigned',
    'lastname as assigned_lastname',
    db_prefix().'leads.addedfrom as addedfrom',

    // Reads from JOIN — no subquery, no extra DB round-trip
    'COALESCE(converted_clients.is_converted, 0) as is_converted',

    db_prefix().'leads.zip',
];

/* ================================================================
   DataTables Init
================================================================ */
$result = data_tables_init(
    $aColumns,
    $sIndexColumn,
    $sTable,
    $join,
    $where,
    $additionalColumns
);

// echo '<pre>';
// foreach ($CI->db->queries as $key => $query) {
//     echo $query."\nTime: ".$CI->db->query_times[$key]."\n-------------------------\n";
// }
// echo '</pre>';
// exit;

$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    $row[] = '<div class="checkbox"><input type="checkbox" value="'.$aRow['id'].'"><label></label></div>';

    $hrefAttr = 'href="'.admin_url('leads/index/'.$aRow['id']).'" onclick="init_lead('.$aRow['id'].');return false;"';
    $row[] = '<a '.$hrefAttr.'>'.$aRow['id'].'</a>';

    $nameRow = '<a '.$hrefAttr.'>'.$aRow['name'].'</a>';

    $nameRow .= '<div class="row-options">';
    $nameRow .= '<a '.$hrefAttr.'>'._l('view').'</a>';

    $locked = false;

    if ($aRow['is_converted'] > 0) {
        $locked = ((!is_admin() && $lockAfterConvert == 1) ? true : false);
    }

    if (!$locked) {
        $nameRow .= ' | <a href="'.admin_url('leads/index/'.$aRow['id'].'?edit=true').'" onclick="init_lead('.$aRow['id'].', true);return false;">'._l('edit').'</a>';
    }

    if ($aRow['addedfrom'] == get_staff_user_id() || $has_permission_delete) {
        $nameRow .= ' | <a href="'.admin_url('leads/delete/'.$aRow['id']).'" class="_delete text-danger">'._l('delete').'</a>';
    }
    $nameRow .= '</div>';

    $row[] = $nameRow;

    if (is_gdpr() && $consentLeads == '1') {
        $consentHTML = '<p class="bold"><a href="#" onclick="view_lead_consent('.$aRow['id'].'); return false;">'._l('view_consent').'</a></p>';
        $consents = $this->ci->gdpr_model->get_consent_purposes($aRow['id'], 'lead');

        foreach ($consents as $consent) {
            $consentHTML .= '<p style="margin-bottom:0px;">'.$consent['name'].(!empty($consent['consent_given']) ? '<i class="fa fa-check text-success pull-right"></i>' : '<i class="fa fa-remove text-danger pull-right"></i>').'</p>';
        }
        $row[] = $consentHTML;
    }

    $row[] = ($aRow['email'] != '' ? '<a href="mailto:'.$aRow['email'].'">'.$aRow['email'].'</a>' : '');

    // $row[] = ($aRow['phonenumber'] != '' ? '<a href="tel:' . $aRow['phonenumber'] . '">' . $aRow['phonenumber'] . '</a>' : '');

    $row[] = '
<span style="white-space:nowrap;">

    <a href="javascript:void(0)"
       class="make-call text-success"
       data-phone="'.htmlspecialchars($aRow['phonenumber'] ?? '').'"
       data-lead-id="'.$aRow['id'].'"
       data-assigned="'.$aRow['assigned'].'">
        <i class="fa fa-phone"></i> '.htmlspecialchars($aRow['phonenumber'] ?? '').'
    </a>

    <span style="display:inline-block;width:10px;"></span>

    <a href="'.base_url('admin/whatsapp').'"
       class="send-whatsapp open-whatsapp"
       data-phone="'.htmlspecialchars($aRow['phonenumber'] ?? '').'"
       title="Send WhatsApp" target="_blank">
        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg"
             alt="WhatsApp"
             style="width:25px;height:25px;vertical-align:middle;">
    </a>

</span>';

    $base_currency = get_base_currency();

    // $row[] .= render_tags($aRow['tags']);
    $assignedOutput = '';
    if ($aRow['assigned'] != 0) {
        $full_name = $aRow['assigned_firstname'].' '.$aRow['assigned_lastname'];

        $assignedOutput = '
                <div class="text-center" style="display:inline-block;">
                    <a data-toggle="tooltip" data-title="'.$full_name.'" 
                    href="'.admin_url('profile/'.$aRow['assigned']).'">
                        <div style="display:flex; flex-direction:column; align-items:center;">
                            '.staff_profile_image($aRow['assigned'], ['staff-profile-image-small', 'mbot5']).'
                            <div style="margin-top:4px; font-size:13px;">'.$full_name.'</div>
                        </div>
                    </a>
                </div>
                <span class="hide">'.$full_name.'</span>
            ';
    } else {
        $assignedOutput = '<div class="text-center text-muted">Unassigned</div>';
    }

    $row[] = $assignedOutput;

    if ($aRow['status_name'] == null) {
        if ($aRow['lost'] == 1) {
            $outputStatus = '<span class="label label-danger">'._l('lead_lost').'</span>';
        } elseif ($aRow['junk'] == 1) {
            $outputStatus = '<span class="label label-warning">'._l('lead_junk').'</span>';
        }
    } else {
        $outputStatus = '<span class="lead-status-'.$aRow['status'].' label'.(empty($aRow['color']) ? ' label-default' : '').'" style="color:'.$aRow['color'].';border:1px solid '.adjust_hex_brightness($aRow['color'], 0.4).';background: '.adjust_hex_brightness($aRow['color'], 0.04).';">'.$aRow['status_name'];

        if (!$locked) {
            $outputStatus .= '<div class="dropdown inline-block mleft5 table-export-exclude">';
            $outputStatus .= '<a href="#" style="font-size:14px;vertical-align:middle;" class="dropdown-toggle text-dark" id="tableLeadsStatus-'.$aRow['id'].'" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
            $outputStatus .= '<span data-toggle="tooltip" title="'._l('ticket_single_change_status').'"><i class="fa-solid fa-chevron-down tw-opacity-70"></i></span>';
            $outputStatus .= '</a>';

            $outputStatus .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="tableLeadsStatus-'.$aRow['id'].'">';
            foreach ($statuses as $leadChangeStatus) {
                if ($aRow['status'] != $leadChangeStatus['id']) {
                    $outputStatus .= '<li>
                  <a href="#" onclick="lead_mark_as('.$leadChangeStatus['id'].','.$aRow['id'].'); return false;">
                     '.$leadChangeStatus['name'].'
                  </a>
               </li>';
                }
            }
            $outputStatus .= '</ul>';
            $outputStatus .= '</div>';
        }
        $outputStatus .= '</span>';
    }

    $row[] = $outputStatus;

    $subs = [];

    if (!empty($aRow['status']) && isset($subStatusByStatus[$aRow['status']])) {
        $subs = $subStatusByStatus[$aRow['status']];
    }

    $currentSub = '-';

    if (!empty($aRow['sub_status']) && isset($subStatusById[$aRow['sub_status']])) {
        $currentSub = $subStatusById[$aRow['sub_status']];
    }

    if ($currentSub === '-' && !empty($subs)) {
        $currentSub = $subs[0]['sub_name'];
    }

    $outputSubStatus = '';
    if (!empty($subs)) {
        $toggleId = 'tableLeadsSubStatus-'.$aRow['id'];
        $outputSubStatus .= '<div class="dropdown inline-block mleft5 table-export-exclude" style="display:inline-block;">';
        $outputSubStatus .= '<a href="#" id="'.$toggleId.'" class="dropdown-toggle label label-info" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
        $outputSubStatus .= htmlspecialchars($currentSub, ENT_QUOTES, 'UTF-8');
        $outputSubStatus .= ' <i class="fa fa-chevron-down"></i>';
        $outputSubStatus .= '</a>';
        $outputSubStatus .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="'.$toggleId.'">';

        foreach ($subs as $sub) {
            $escaped = htmlspecialchars($sub['sub_name'], ENT_QUOTES, 'UTF-8');
            $outputSubStatus .= '<li><a href="#" onclick="lead_mark_as(\'sub-'.$sub['id'].'\','.$aRow['id'].'); return false;">'.$escaped.'</a></li>';
        }

        $outputSubStatus .= '</ul>';
        $outputSubStatus .= '</div>';
    }

    $row[] = $outputSubStatus;

    $row[] = $aRow['company'];
    $row[] = $aRow['website'];
    $row[] = $aRow['source_name'];
    $row[] = $aRow['city'];
    $row[] = $aRow['address'];
    $row[] = $aRow['form_name'];

    $row[] = ($aRow['lastcontact'] == '0000-00-00 00:00:00' || !is_date($aRow['lastcontact']) ? '' : '<span data-toggle="tooltip" data-title="'._dt($aRow['lastcontact']).'" class="text-has-action is-date">'.time_ago($aRow['lastcontact']).'</span>');

    $row[] = '<span data-toggle="tooltip" data-title="'._dt($aRow['dateadded']).'" class="text-has-action is-date">'.time_ago($aRow['dateadded']).'</span>';
    // Custom fields add values
    foreach ($customFieldsColumns as $customFieldColumn) {
        $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
    }

    $row['DT_RowId'] = 'lead_'.$aRow['id'];

    if ($aRow['assigned'] == get_staff_user_id()) {
        $row['DT_RowClass'] = 'info';
    }

    if (isset($row['DT_RowClass'])) {
        $row['DT_RowClass'] .= ' has-row-options';
    } else {
        $row['DT_RowClass'] = 'has-row-options';
    }
    // echo "<pre>"; print_r($row); echo "</pre>";

    $row = hooks()->apply_filters('leads_table_row_data', $row, $aRow);

    $output['aaData'][] = $row;
}
