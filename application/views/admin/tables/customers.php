<?php

defined('BASEPATH') or exit('No direct script access allowed');

$this->ci->load->model('gdpr_model');
$lockAfterConvert      = get_option('lead_lock_after_convert_to_customer');
$has_permission_delete = has_permission('leads', '', 'delete');
$custom_fields         = get_table_custom_fields('leads');
$consentLeads          = get_option('gdpr_enable_consent_for_leads');
$CI = &get_instance();

if (!class_exists('customer_model')) {
    $CI->load->model('customer_model');
}

$statuses = $CI->customer_model->get_status();

// echo "<pre>"; print_r($statuses); echo "</pre>"; die;
$aColumns = [
    '1',
    db_prefix() . 'leads.id as id',
    db_prefix() . 'leads.name as name',
    ];
if (is_gdpr() && $consentLeads == '1') {
    $aColumns[] = '1';
}
$aColumns = array_merge($aColumns, ['city','source_campaign','job','description',
    db_prefix() . 'leads.email as email',
    db_prefix() . 'leads.phonenumber as phonenumber',
    // '(SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'leads.id and rel_type="lead" ORDER by tag_order ASC LIMIT 1) as tags',
    db_prefix() . 'customer_status.name as status_name',
    db_prefix() . 'customer_substatus.sub_name as substatus_name',

    'firstname as assigned_firstname',
    db_prefix() . 'leads_sources.name as source_name',
    'lastcontact',
    'dateadded'
]);

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'leads';

$join = [
    'LEFT JOIN ' . db_prefix() . 'staff 
        ON ' . db_prefix() . 'staff.staffid = ' . db_prefix() . 'leads.assigned',
    'LEFT JOIN ' . db_prefix() . 'customer_status 
        ON ' . db_prefix() . 'customer_status.id = ' . db_prefix() . 'leads.customer_status',
    'LEFT JOIN ' . db_prefix() . 'customer_substatus 
        ON ' . db_prefix() . 'customer_substatus.id = ' . db_prefix() . 'leads.customer_sub_status',
    'JOIN ' . db_prefix() . 'leads_sources 
        ON ' . db_prefix() . 'leads_sources.id = ' . db_prefix() . 'leads.source',
];


foreach ($custom_fields as $key => $field) {
    $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);
    array_push($customFieldsColumns, $selectAs);
    array_push($aColumns, 'ctable_' . $key . '.value as ' . $selectAs);
    array_push($join, 'LEFT JOIN ' . db_prefix() . 'customfieldsvalues as ctable_' . $key . ' ON ' . db_prefix() . 'leads.id = ctable_' . $key . '.relid AND ctable_' . $key . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $key . '.fieldid=' . $field['id']);
}

$where  = [];
$filter = false;
array_push($where, 'AND record_type = "broker"');
$customerStatuses = $CI->db->select('id')
    ->from(db_prefix() . 'customer_status')
    ->get()
    ->result_array();

$customerStatusIds = array_column($customerStatuses, 'id'); // get array of IDs

if (!empty($customerStatusIds)) {
    $where[] = 'AND ' . db_prefix() . 'leads.customer_status IN (' . implode(',', $customerStatusIds) . ')';
}

if ($this->ci->input->post('custom_view')) {
    $filter = $this->ci->input->post('custom_view');
    if ($filter == 'lost') {
        array_push($where, 'AND lost = 1');
    } elseif ($filter == 'junk') {
        array_push($where, 'AND junk = 1');
    } elseif ($filter == 'not_assigned') {
        array_push($where, 'AND assigned = 0');
    } elseif ($filter == 'contacted_today') {
        array_push($where, 'AND lastcontact LIKE "' . date('Y-m-d') . '%"');
    } elseif ($filter == 'created_today') {
        array_push($where, 'AND dateadded LIKE "' . date('Y-m-d') . '%"');
    } elseif ($filter == 'public') {
        array_push($where, 'AND is_public = 1');
    } elseif (startsWith($filter, 'consent_')) {
        array_push($where, 'AND ' . db_prefix() . 'leads.id IN (SELECT lead_id FROM ' . db_prefix() . 'consents WHERE purpose_id=' . $this->ci->db->escape_str(strafter($filter, 'consent_')) . ' and action="opt-in" AND date IN (SELECT MAX(date) FROM ' . db_prefix() . 'consents WHERE purpose_id=' . $this->ci->db->escape_str(strafter($filter, 'consent_')) . ' AND lead_id=' . db_prefix() . 'leads.id))');
    }
}

if (!$filter || ($filter && $filter != 'lost' && $filter != 'junk')) {
    array_push($where, 'AND lost = 0 AND junk = 0');
}

if (has_permission('leads', '', 'view') && $this->ci->input->post('assigned')) {
    array_push($where, 'AND assigned =' . $this->ci->db->escape_str($this->ci->input->post('assigned')));
}

if ($this->ci->input->post('status')
    && count($this->ci->input->post('status')) > 0
    && ($filter != 'lost' && $filter != 'junk')) {
    array_push($where, 'AND customer_status IN (' . implode(',', $this->ci->db->escape_str($this->ci->input->post('status'))) . ')');
}

if ($this->ci->input->post('source')) {
    array_push($where, 'AND source =' . $this->ci->db->escape_str($this->ci->input->post('source')));
}

if (!has_permission('leads', '', 'view')) {
    array_push($where, 'AND (assigned =' . get_staff_user_id() . ' OR addedfrom = ' . get_staff_user_id() . ' OR is_public = 1)');
}

$aColumns = hooks()->apply_filters('leads_table_sql_columns', $aColumns);

// Fix for big queries. Some hosting have max_join_limit
if (count($custom_fields) > 4) {
    @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
}

$additionalColumns = hooks()->apply_filters('leads_table_additional_columns_sql', [
    'junk',
    'lost',
    'color',
    'status',
    'sub_status',
    'assigned',
    'lastname as assigned_lastname',
    db_prefix() . 'leads.addedfrom as addedfrom',
    '(SELECT count(leadid) FROM ' . db_prefix() . 'clients WHERE ' . db_prefix() . 'clients.leadid=' . db_prefix() . 'leads.id) as is_converted',
    'zip',
]);

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalColumns);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

    $hrefAttr = 'href="' . admin_url('leads/index/' . $aRow['id']) . '" onclick="init_lead(' . $aRow['id'] . ');return false;"';
    $row[]    = '<a ' . $hrefAttr . '>' . $aRow['id'] . '</a>';

    $nameRow = '<a ' . $hrefAttr . '>' . $aRow['name'] . '</a>';

    $nameRow .= '<div class="row-options">';
    $nameRow .= '<a ' . $hrefAttr . '>' . _l('view') . '</a>';

    $locked = false;

    if ($aRow['is_converted'] > 0) {
        $locked = ((!is_admin() && $lockAfterConvert == 1) ? true : false);
    }

    if (!$locked) {
        $nameRow .= ' | <a href="' . admin_url('leads/index/' . $aRow['id'] . '?edit=true') . '" onclick="init_lead(' . $aRow['id'] . ', true);return false;">' . _l('edit') . '</a>';
    }

    if ($aRow['addedfrom'] == get_staff_user_id() || $has_permission_delete) {
        $nameRow .= ' | <a href="' . admin_url('leads/delete/' . $aRow['id']) . '" class="_delete text-danger">' . _l('delete') . '</a>';
    }
    $nameRow .= '</div>';


    $row[] = $nameRow;

    if (is_gdpr() && $consentLeads == '1') {
        $consentHTML = '<p class="bold"><a href="#" onclick="view_lead_consent(' . $aRow['id'] . '); return false;">' . _l('view_consent') . '</a></p>';
        $consents    = $this->ci->gdpr_model->get_consent_purposes($aRow['id'], 'lead');

        foreach ($consents as $consent) {
            $consentHTML .= '<p style="margin-bottom:0px;">' . $consent['name'] . (!empty($consent['consent_given']) ? '<i class="fa fa-check text-success pull-right"></i>' : '<i class="fa fa-remove text-danger pull-right"></i>') . '</p>';
        }
        $row[] = $consentHTML;
    }

    

    $row[] = ($aRow['email'] != '' ? '<a href="mailto:' . $aRow['email'] . '">' . $aRow['email'] . '</a>' : '');

    $row[] = ($aRow['phonenumber'] != '' ? '<a href="tel:' . $aRow['phonenumber'] . '">' . $aRow['phonenumber'] . '</a>' : '');

    $base_currency = get_base_currency();

    // $row[] .= render_tags($aRow['tags']);

    $assignedOutput = '';
    if ($aRow['assigned'] != 0) {
        $full_name = $aRow['assigned_firstname'] . ' ' . $aRow['assigned_lastname'];

        $assignedOutput = '<a data-toggle="tooltip" data-title="' . $full_name . '" href="' . admin_url('profile/' . $aRow['assigned']) . '">' . staff_profile_image($aRow['assigned'], [
            'staff-profile-image-small',
            ]) . '</a>';

        // For exporting
        $assignedOutput .= '<span class="hide">' . $full_name . '</span>';
    }

    $row[] = $assignedOutput;

     $outputStatus = '';
 if ($aRow['status_name'] == null) {
        if ($aRow['lost'] == 1) {
            $outputStatus = '<span class="label label-danger">' . _l('lead_lost') . '</span>';
        } elseif ($aRow['junk'] == 1) {
            $outputStatus = '<span class="label label-warning">' . _l('lead_junk') . '</span>';
        }
    } else {
        $outputStatus = '<span class="lead-status-' . $aRow['status'] . ' label' . (empty($aRow['color']) ? ' label-default': '') . '" style="color:' . $aRow['color'] . ';border:1px solid ' . adjust_hex_brightness($aRow['color'], 0.4) . ';background: ' . adjust_hex_brightness($aRow['color'], 0.04) . ';">' . $aRow['status_name'];

        if (!$locked) {
            $outputStatus .= '<div class="dropdown inline-block mleft5 table-export-exclude">';
            $outputStatus .= '<a href="#" style="font-size:14px;vertical-align:middle;" class="dropdown-toggle text-dark" id="tableLeadsStatus-' . $aRow['id'] . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
            $outputStatus .= '<span data-toggle="tooltip" title="' . _l('ticket_single_change_status') . '"><i class="fa-solid fa-chevron-down tw-opacity-70"></i></span>';
            $outputStatus .= '</a>';

            $outputStatus .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="tableLeadsStatus-' . $aRow['id'] . '">';
            foreach ($statuses as $leadChangeStatus) {
                if ($aRow['status'] != $leadChangeStatus['id']) {
                    $outputStatus .= '<li>
                  <a href="#" onclick="customer_mark_as(' . $leadChangeStatus['id'] . ',' . $aRow['id'] . '); return false;">
                     ' . $leadChangeStatus['name'] . '
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

            
         
     $statusId = null;
      
    if (!empty($aRow['status_name'])) {
        if (is_numeric($aRow['status_name'])) {
            $statusId = intval($aRow['status_name']);
        } else {
            $rowStatus = $CI->db->select('id')->from('tblcustomer_status')->where('name', $aRow['status_name'])->get()->row();
            $statusId = $rowStatus->id ?? null;
        }
    }

    $subs = [];
    if (empty($statusId)) {
        $CI->db->select('id, sub_name, status_id');
        $CI->db->from('tblcustomer_substatus');
        $CI->db->order_by('substatusorder', 'ASC');
        $subs = $CI->db->get()->result_array();
    } else {
        $CI->db->select('id, sub_name');
        $CI->db->from('tblcustomer_substatus');
        $CI->db->where('status_id', $statusId);
        $CI->db->order_by('substatusorder', 'ASC');
        $subs = $CI->db->get()->result_array();
    }


   $selectedSubNames = array();
if (!empty($aRow['substatus_name'])) {
    if (is_array($aRow['substatus_name'])) {
        $selectedSubNames = $aRow['substatus_name'];
    } else {
        $selectedSubNames = explode(',', $aRow['substatus_name']);
    }
}

$currentSub = 'Select';
    if (!empty($selectedSubNames)) {
        foreach ($subs as $sub) {
            if (in_array($sub['sub_name'], $selectedSubNames)) {
                $currentSub = $sub['sub_name']; 
                
                break;
            }
        }
    } elseif (!empty($subs)) {
        $currentSub = $subs[0]['sub_name'];
    }


    $outputSubStatus = '';

    if (!empty($subs)) {
        $toggleId = 'tableCustomerSubStatus-' . $aRow['id'];

        $outputSubStatus .= '<div class="dropdown inline-block mleft5 table-export-exclude" style="display:inline-block;">';
        $outputSubStatus .= '<a href="#" id="' . htmlspecialchars($toggleId, ENT_QUOTES, 'UTF-8') . '" class="dropdown-toggle label label-info" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
        $outputSubStatus .= htmlspecialchars($currentSub, ENT_QUOTES, 'UTF-8');
        $outputSubStatus .= ' <i class="fa fa-chevron-down"></i>';
        $outputSubStatus .= '</a>';

        $outputSubStatus .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="' . htmlspecialchars($toggleId, ENT_QUOTES, 'UTF-8') . '">';

        if (empty($statusId) && empty($selectedSubNames)) {
            $outputSubStatus .= '<li><a href="#" onclick="customer_mark_as(\'sub-all\',' . $aRow['id'] . '); return false;">All substatuses</a></li>';
            $outputSubStatus .= '<li role="separator" class="divider"></li>';
        }

        foreach ($subs as $sub) {
            $subName = $sub['sub_name'];
            $escaped = htmlspecialchars($subName, ENT_QUOTES, 'UTF-8');
            $isSelected = in_array($subName, $selectedSubNames) ? ' <i class="fa fa-check text-success"></i>' : '';
            $outputSubStatus .= '<li><a href="#" onclick="customer_mark_as(\'sub-' . $sub['id'] . '\',' . $aRow['id'] . '); return false;">' . $escaped . $isSelected . '</a></li>';
        }

        $outputSubStatus .= '</ul>';
        $outputSubStatus .= '</div>';
    } else {
        $outputSubStatus = '<span class="label label-default">Select</span>';
    }



    $row[] = $outputSubStatus;
    $row[] = $aRow['source_name'];
    $row[] = $aRow['city'];
    $row[] = $aRow['source_campaign'];
    $row[] = $aRow['job'];
    $row[] = $aRow['description'];

    $row[] = ($aRow['lastcontact'] == '0000-00-00 00:00:00' || !is_date($aRow['lastcontact']) ? '' : '<span data-toggle="tooltip" data-title="' . _dt($aRow['lastcontact']) . '" class="text-has-action is-date">' . time_ago($aRow['lastcontact']) . '</span>');

    $row[] = '<span data-toggle="tooltip" data-title="' . _dt($aRow['dateadded']) . '" class="text-has-action is-date">' . time_ago($aRow['dateadded']) . '</span>';
    // Custom fields add values
    foreach ($customFieldsColumns as $customFieldColumn) {
        $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
    }

    $row['DT_RowId'] = 'lead_' . $aRow['id'];

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