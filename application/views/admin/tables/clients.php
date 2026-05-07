<?php

defined('BASEPATH') or exit('No direct script access allowed');


$this->ci->load->model('gdpr_model');
$this->ci->load->model('leads_model');
$CI = &get_instance(); // add this line

$statuses = $this->ci->leads_model->get_customer_status();

$hasPermissionDelete = has_permission('customers', '', 'delete');

$custom_fields = get_table_custom_fields('customers');
$this->ci->db->query("SET sql_mode = ''");

$aColumns = [
    '1',
    db_prefix() . 'clients.userid as userid',
    'company',
    'CONCAT(firstname, " ", lastname) as fullname',
    'email',
    db_prefix() . 'clients.phonenumber as phonenumber',
    db_prefix() . 'clients.active',
    // db_prefix() . 'clients.status', // <-- add this
    // db_prefix() . 'clients.sub_status', // <-- add this
    db_prefix() . 'customer_status.name as status_name',
    db_prefix() . 'customer_substatus.sub_name as substatus_name',
    '(SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'customer_groups 
        JOIN ' . db_prefix() . 'customers_groups 
        ON ' . db_prefix() . 'customer_groups.groupid = ' . db_prefix() . 'customers_groups.id 
        WHERE customer_id = ' . db_prefix() . 'clients.userid ORDER by name ASC) as customerGroups',
    db_prefix() . 'clients.datecreated as datecreated',
];


$sIndexColumn = 'userid';
$sTable       = db_prefix() . 'clients';
$where        = [];
// Add blank where all filter can be stored
$filter = [];

$join = [
    'LEFT JOIN ' . db_prefix() . 'contacts ON ' . db_prefix() . 'contacts.userid=' . db_prefix() . 'clients.userid AND ' . db_prefix() . 'contacts.is_primary=1',
    'LEFT JOIN ' . db_prefix() . 'customer_status ON ' . db_prefix() . 'customer_status.id = ' . db_prefix() . 'clients.status',
    'LEFT JOIN ' . db_prefix() . 'customer_substatus ON ' . db_prefix() . 'customer_substatus.id = ' . db_prefix() . 'clients.sub_status',

];

foreach ($custom_fields as $key => $field) {
    $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);
    array_push($customFieldsColumns, $selectAs);
    array_push($aColumns, 'ctable_' . $key . '.value as ' . $selectAs);
    array_push($join, 'LEFT JOIN ' . db_prefix() . 'customfieldsvalues as ctable_' . $key . ' ON ' . db_prefix() . 'clients.userid = ctable_' . $key . '.relid AND ctable_' . $key . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $key . '.fieldid=' . $field['id']);
}

$join = hooks()->apply_filters('customers_table_sql_join', $join);

// Filter by custom groups
$groups   = $this->ci->clients_model->get_groups();
$groupIds = [];
foreach ($groups as $group) {
    if ($this->ci->input->post('customer_group_' . $group['id'])) {
        array_push($groupIds, $group['id']);
    }
}
if (count($groupIds) > 0) {
    array_push($filter, 'AND ' . db_prefix() . 'clients.userid IN (SELECT customer_id FROM ' . db_prefix() . 'customer_groups WHERE groupid IN (' . implode(', ', $groupIds) . '))');
}

$countries  = $this->ci->clients_model->get_clients_distinct_countries();
$countryIds = [];
foreach ($countries as $country) {
    if ($this->ci->input->post('country_' . $country['country_id'])) {
        array_push($countryIds, $country['country_id']);
    }
}
if (count($countryIds) > 0) {
    array_push($filter, 'AND country IN (' . implode(',', $countryIds) . ')');
}


$this->ci->load->model('invoices_model');
// Filter by invoices
$invoiceStatusIds = [];
foreach ($this->ci->invoices_model->get_statuses() as $status) {
    if ($this->ci->input->post('invoices_' . $status)) {
        array_push($invoiceStatusIds, $status);
    }
}
if (count($invoiceStatusIds) > 0) {
    array_push($filter, 'AND ' . db_prefix() . 'clients.userid IN (SELECT clientid FROM ' . db_prefix() . 'invoices WHERE status IN (' . implode(', ', $invoiceStatusIds) . '))');
}

// Filter by estimates
$estimateStatusIds = [];
$this->ci->load->model('estimates_model');
foreach ($this->ci->estimates_model->get_statuses() as $status) {
    if ($this->ci->input->post('estimates_' . $status)) {
        array_push($estimateStatusIds, $status);
    }
}
if (count($estimateStatusIds) > 0) {
    array_push($filter, 'AND ' . db_prefix() . 'clients.userid IN (SELECT clientid FROM ' . db_prefix() . 'estimates WHERE status IN (' . implode(', ', $estimateStatusIds) . '))');
}

// Filter by projects
$projectStatusIds = [];
$this->ci->load->model('projects_model');
foreach ($this->ci->projects_model->get_project_statuses() as $status) {
    if ($this->ci->input->post('projects_' . $status['id'])) {
        array_push($projectStatusIds, $status['id']);
    }
}
if (count($projectStatusIds) > 0) {
    array_push($filter, 'AND ' . db_prefix() . 'clients.userid IN (SELECT clientid FROM ' . db_prefix() . 'projects WHERE status IN (' . implode(', ', $projectStatusIds) . '))');
}

// Filter by proposals
$proposalStatusIds = [];
$this->ci->load->model('proposals_model');
foreach ($this->ci->proposals_model->get_statuses() as $status) {
    if ($this->ci->input->post('proposals_' . $status)) {
        array_push($proposalStatusIds, $status);
    }
}
if (count($proposalStatusIds) > 0) {
    array_push($filter, 'AND ' . db_prefix() . 'clients.userid IN (SELECT rel_id FROM ' . db_prefix() . 'proposals WHERE status IN (' . implode(', ', $proposalStatusIds) . ') AND rel_type="customer")');
}

// Filter by having contracts by type
$this->ci->load->model('contracts_model');
$contractTypesIds = [];
$contract_types   = $this->ci->contracts_model->get_contract_types();

foreach ($contract_types as $type) {
    if ($this->ci->input->post('contract_type_' . $type['id'])) {
        array_push($contractTypesIds, $type['id']);
    }
}
if (count($contractTypesIds) > 0) {
    array_push($filter, 'AND ' . db_prefix() . 'clients.userid IN (SELECT client FROM ' . db_prefix() . 'contracts WHERE contract_type IN (' . implode(', ', $contractTypesIds) . '))');
}

// Filter by proposals
$customAdminIds = [];
foreach ($this->ci->clients_model->get_customers_admin_unique_ids() as $cadmin) {
    if ($this->ci->input->post('responsible_admin_' . $cadmin['staff_id'])) {
        array_push($customAdminIds, $cadmin['staff_id']);
    }
}

if (count($customAdminIds) > 0) {
    array_push($filter, 'AND ' . db_prefix() . 'clients.userid IN (SELECT customer_id FROM ' . db_prefix() . 'customer_admins WHERE staff_id IN (' . implode(', ', $customAdminIds) . '))');
}

if ($this->ci->input->post('requires_registration_confirmation')) {
    array_push($filter, 'AND ' . db_prefix() . 'clients.registration_confirmed=0');
}

if (count($filter) > 0) {
    array_push($where, 'AND (' . prepare_dt_filter($filter) . ')');
}

if (!has_permission('customers', '', 'view')) {
    array_push($where, 'AND ' . db_prefix() . 'clients.userid IN (SELECT customer_id FROM ' . db_prefix() . 'customer_admins WHERE staff_id=' . get_staff_user_id() . ')');
}

if ($this->ci->input->post('exclude_inactive')) {
    array_push($where, 'AND (' . db_prefix() . 'clients.active = 1 OR ' . db_prefix() . 'clients.active=0 AND registration_confirmed = 0)');
}

if ($this->ci->input->post('my_customers')) {
    array_push($where, 'AND ' . db_prefix() . 'clients.userid IN (SELECT customer_id FROM ' . db_prefix() . 'customer_admins WHERE staff_id=' . get_staff_user_id() . ')');
}

$aColumns = hooks()->apply_filters('customers_table_sql_columns', $aColumns);

// Fix for big queries. Some hosting have max_join_limit
if (count($custom_fields) > 4) {
    @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    db_prefix() . 'contacts.id as contact_id',
    'lastname',
    db_prefix() . 'clients.zip as zip',
    'registration_confirmed',
]);



$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    // Bulk actions
    $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['userid'] . '"><label></label></div>';
    // User id
    $row[] = $aRow['userid'];

    // Company
    $company  = $aRow['company'];
    $isPerson = false;

    if ($company == '') {
        $company  = _l('no_company_view_profile');
        $isPerson = true;
    }

    $url = admin_url('clients/client/' . $aRow['userid']);

    if ($isPerson && $aRow['contact_id']) {
        $url .= '?contactid=' . $aRow['contact_id'];
    }

    $company = '<a href="' . $url . '">' . $company . '</a>';

    $company .= '<div class="row-options">';
    $company .= '<a href="' . admin_url('clients/client/' . $aRow['userid'] . ($isPerson && $aRow['contact_id'] ? '?group=contacts' : '')) . '">' . _l('view') . '</a>';

    if ($aRow['registration_confirmed'] == 0 && is_admin()) {
        $company .= ' | <a href="' . admin_url('clients/confirm_registration/' . $aRow['userid']) . '" class="text-success bold">' . _l('confirm_registration') . '</a>';
    }
    if (!$isPerson) {
        $company .= ' | <a href="' . admin_url('clients/client/' . $aRow['userid'] . '?group=contacts') . '">' . _l('customer_contacts') . '</a>';
    }
    if ($hasPermissionDelete) {
        $company .= ' | <a href="' . admin_url('clients/delete/' . $aRow['userid']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
    }

    $company .= '</div>';

    $row[] = $company;

    // Primary contact
    $row[] = ($aRow['contact_id'] ? '<a href="' . admin_url('clients/client/' . $aRow['userid'] . '?contactid=' . $aRow['contact_id']) . '" target="_blank">' . trim($aRow['fullname']) . '</a>' : '');

    // Primary contact email
    $row[] = ($aRow['email'] ? '<a href="mailto:' . $aRow['email'] . '">' . $aRow['email'] . '</a>' : '');

    // Primary contact phone
    $row[] = ($aRow['phonenumber'] ? '<a href="tel:' . $aRow['phonenumber'] . '">' . $aRow['phonenumber'] . '</a>' : '');

    // Toggle active/inactive customer
    $toggleActive = '<div class="onoffswitch" data-toggle="tooltip" data-title="' . _l('customer_active_inactive_help') . '">
    <input type="checkbox"' . ($aRow['registration_confirmed'] == 0 ? ' disabled' : '') . ' data-switch-url="' . admin_url() . 'clients/change_client_status" name="onoffswitch" class="onoffswitch-checkbox" id="' . $aRow['userid'] . '" data-id="' . $aRow['userid'] . '" ' . ($aRow[db_prefix() . 'clients.active'] == 1 ? 'checked' : '') . '>
    <label class="onoffswitch-label" for="' . $aRow['userid'] . '"></label>
    </div>';

    // For exporting
    $toggleActive .= '<span class="hide">' . ($aRow[db_prefix() . 'clients.active'] == 1 ? _l('is_active_export') : _l('is_not_active_export')) . '</span>';

         
    $userId = intval($aRow['userid'] ?? 0);

    // Ensure we have the statuses list
    $statusesList = [];
    if (!empty($statuses) && is_array($statuses)) {
        $statusesList = $statuses;
    } else {
        $CI->db->select('id, name, color');
        $CI->db->from('tblcustomer_status');
        $CI->db->order_by('statusorder', 'ASC');
        $statusesList = $CI->db->get()->result_array();
    }

    // Defaults
    $currentStatusName  = 'Select';
    $currentStatusId    = null;
    $currentStatusColor = '#6c757d';

    if (!empty($aRow['status_name'])) {
        $search = $aRow['status_name'];
        foreach ($statusesList as $s) {
            if ($s['id'] == $search || $s['name'] == $search) {
                $currentStatusId    = intval($s['id']);
                $currentStatusName  = $s['name'];
                $currentStatusColor = $s['color'] ?? $currentStatusColor;
                break;
            }
        }
    }

    // Colors
    $borderColor = adjust_hex_brightness($currentStatusColor, 0.4);
    $bgColor     = adjust_hex_brightness($currentStatusColor, 0.04);

    // Build output
    $toggleId = 'tableCustomerStatus-' . $userId;
    $outputStatus  = '<span class="customer-status-' . htmlspecialchars($currentStatusName, ENT_QUOTES, 'UTF-8') . ' label" style="color:' . $currentStatusColor . ';border:1px solid ' . $borderColor . ';background:' . $bgColor . ';">';
    $outputStatus .= htmlspecialchars($currentStatusName, ENT_QUOTES, 'UTF-8');

    // Dropdown
    $outputStatus .= '<div class="dropdown inline-block mleft5 table-export-exclude">';
    $outputStatus .= '<a href="#" style="font-size:14px;vertical-align:middle;" class="dropdown-toggle text-dark" id="' . htmlspecialchars($toggleId, ENT_QUOTES, 'UTF-8') . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
    $outputStatus .= '<span data-toggle="tooltip" title="Change Status"><i class="fa-solid fa-chevron-down tw-opacity-70"></i></span>';
    $outputStatus .= '</a>';

    $outputStatus .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="' . htmlspecialchars($toggleId, ENT_QUOTES, 'UTF-8') . '">';

    // If no status selected → show all statuses
    if (empty($aRow['status_name'])) {
        foreach ($statusesList as $s) {
            $sId   = intval($s['id']);
            $sName = $s['name'];
            $outputStatus .= '<li><a href="#" onclick="customer_mark_as(' . $sId . ',' . $userId . '); return false;">' . htmlspecialchars($sName, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
    } else {
        // If status is set → show other statuses only
        foreach ($statusesList as $s) {
            if ($s['name'] == $currentStatusName || $s['id'] == $currentStatusId) {
                continue;
            }
            $sId   = intval($s['id']);
            $sName = $s['name'];
            $outputStatus .= '<li><a href="#" onclick="customer_mark_as(' . $sId . ',' . $userId . '); return false;">' . htmlspecialchars($sName, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
    }

    $outputStatus .= '</ul>';
    $outputStatus .= '</div>';
    $outputStatus .= '</span>';

 
    
    

    $userId = intval($aRow['userid'] ?? 0);


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


    $selectedSubNames = [];
    if (!empty($aRow['substatus_name'])) {
        $selectedSubNames = array_map('trim', explode(',', $aRow['substatus_name']));
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
        $toggleId = 'tableCustomerSubStatus-' . $userId;

        $outputSubStatus .= '<div class="dropdown inline-block mleft5 table-export-exclude" style="display:inline-block;">';
        $outputSubStatus .= '<a href="#" id="' . htmlspecialchars($toggleId, ENT_QUOTES, 'UTF-8') . '" class="dropdown-toggle label label-info" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
        $outputSubStatus .= htmlspecialchars($currentSub, ENT_QUOTES, 'UTF-8');
        $outputSubStatus .= ' <i class="fa fa-chevron-down"></i>';
        $outputSubStatus .= '</a>';

        $outputSubStatus .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="' . htmlspecialchars($toggleId, ENT_QUOTES, 'UTF-8') . '">';

        if (empty($statusId) && empty($selectedSubNames)) {
            $outputSubStatus .= '<li><a href="#" onclick="customer_mark_as(\'sub-all\',' . $userId . '); return false;">All substatuses</a></li>';
            $outputSubStatus .= '<li role="separator" class="divider"></li>';
        }

        foreach ($subs as $sub) {
            $subName = $sub['sub_name'];
            $escaped = htmlspecialchars($subName, ENT_QUOTES, 'UTF-8');
            $isSelected = in_array($subName, $selectedSubNames) ? ' <i class="fa fa-check text-success"></i>' : '';
            $outputSubStatus .= '<li><a href="#" onclick="customer_mark_as(\'sub-' . $sub['id'] . '\',' . $userId . '); return false;">' . $escaped . $isSelected . '</a></li>';
        }

        $outputSubStatus .= '</ul>';
        $outputSubStatus .= '</div>';
    } else {
        $outputSubStatus = '<span class="label label-default">Select</span>';
    }


    $row[] = $toggleActive;

    $row[] = $outputStatus;
    $row[] = $outputSubStatus; 
    $groupsRow = '';
    if ($aRow['customerGroups']) {
        $groups = explode(',', $aRow['customerGroups']);
        foreach ($groups as $group) {
            $groupsRow .= '<span class="label label-default mleft5 customer-group-list pointer">' . $group . '</span>';
        }
    }

    $row[] = $groupsRow;

    $row[] = _dt($aRow['datecreated']);

    // Custom fields add values
    foreach ($customFieldsColumns as $customFieldColumn) {
        $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
    }

    $row['DT_RowClass'] = 'has-row-options';

    if ($aRow['registration_confirmed'] == 0) {
        $row['DT_RowClass'] .= ' info requires-confirmation';
        $row['Data_Title']  = _l('customer_requires_registration_confirmation');
        $row['Data_Toggle'] = 'tooltip';
    }

    $row = hooks()->apply_filters('customers_table_row_data', $row, $aRow);

    $output['aaData'][] = $row;
    // echo "<pre>";print_r($row);die;
}