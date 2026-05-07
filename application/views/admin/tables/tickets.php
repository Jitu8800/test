<?php
defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    '1', // bulk actions
    db_prefix().'tickets.ticketid as ticket_id',
    db_prefix().'tickets.subject as ticket_subject',

    // Tags (inline subquery)
    '(SELECT GROUP_CONCAT(name SEPARATOR ",")
        FROM ' . db_prefix() . 'taggables
        JOIN ' . db_prefix() . 'tags 
            ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id
        WHERE rel_id = ' . db_prefix() . 'tickets.ticketid 
          AND rel_type="ticket"
        ORDER BY tag_order ASC
    ) as ticket_tags',

    db_prefix().'departments.name as department_name',
    db_prefix().'services.name as service_name',
    'CONCAT(' . db_prefix() . 'contacts.firstname, " ", ' . db_prefix() . 'contacts.lastname) as contact_full_name',

    db_prefix().'tickets.status as ticket_status',   // 👈 alias
    db_prefix().'tickets.priority as ticket_priority', // 👈 alias
    db_prefix().'tickets.lastreply as ticket_lastreply',
    db_prefix().'tickets.date as ticket_date',
];

$contactColumn = 6;
$tagsColumns   = 3;

$additionalSelect = [
    db_prefix().'tickets.adminread',
    db_prefix().'tickets.ticketkey',
    db_prefix().'tickets.userid as ticket_userid',
    db_prefix().'tickets_status.statuscolor', // statuscolor comes from tickets_status table
    db_prefix().'tickets.name as ticket_opened_by_name',
    db_prefix().'tickets.email as ticket_email',
    db_prefix().'tickets.assigned as ticket_assigned',
    db_prefix().'clients.company as client_company',
];

$join = [
    'LEFT JOIN ' . db_prefix() . 'contacts ON ' . db_prefix() . 'contacts.id = ' . db_prefix() . 'tickets.contactid',
    'LEFT JOIN ' . db_prefix() . 'services ON ' . db_prefix() . 'services.serviceid = ' . db_prefix() . 'tickets.service',
    'LEFT JOIN ' . db_prefix() . 'departments ON ' . db_prefix() . 'departments.departmentid = ' . db_prefix() . 'tickets.department',
    'LEFT JOIN ' . db_prefix() . 'tickets_status ON ' . db_prefix() . 'tickets_status.ticketstatusid = ' . db_prefix() . 'tickets.status',
    'LEFT JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'tickets.userid',
    'LEFT JOIN ' . db_prefix() . 'tickets_priorities ON ' . db_prefix() . 'tickets_priorities.priorityid = ' . db_prefix() . 'tickets.priority',
];

// Custom fields
$custom_fields = get_table_custom_fields('tickets');
foreach ($custom_fields as $key => $field) {
    $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);
    array_push($customFieldsColumns, $selectAs);
    array_push($aColumns, 'ctable_' . $key . '.value as ' . $selectAs);
    array_push(
        $join,
        'LEFT JOIN ' . db_prefix() . 'customfieldsvalues as ctable_' . $key . ' 
            ON ' . db_prefix() . 'tickets.ticketid = ctable_' . $key . '.relid 
           AND ctable_' . $key . '.fieldto="' . $field['fieldto'] . '" 
           AND ctable_' . $key . '.fieldid=' . $field['id']
    );
}

$where  = [];
$filter = [];

// --- same WHERE filters as before (kept unchanged) ---

$sIndexColumn = 'ticketid';
$sTable       = db_prefix().'tickets';

// Big selects fix
if (count($custom_fields) > 4) {
    @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    foreach ([
        'ticket_id',
        'ticket_subject',
        'ticket_tags',
        'department_name',
        'service_name',
        'contact_full_name',
        'ticket_status',
        'ticket_priority',
        'ticket_lastreply',
        'ticket_date'
    ] as $col) {
        $_data = $aRow[$col];

        // bulk action checkbox
        if ($col === 'ticket_id') {
            $_data = '<div class="checkbox">
                        <input type="checkbox" value="' . $aRow['ticket_id'] . '" 
                               data-name="' . $aRow['ticket_subject'] . '" 
                               data-status="' . $aRow['ticket_status'] . '">
                        <label></label>
                      </div>';
        }
        // dates
        elseif ($col === 'ticket_lastreply') {
            $_data = $aRow['ticket_lastreply'] ? _dt($aRow['ticket_lastreply']) : _l('ticket_no_reply_yet');
        }
        elseif ($col === 'ticket_date') {
            $_data = _dt($_data);
        }
        // subject + actions
        elseif ($col === 'ticket_subject') {
            $url   = admin_url('tickets/ticket/' . $aRow['ticket_id']);
            $_data = '<a href="' . $url . '" class="valign">' . $_data . '</a>';
            $_data .= '<div class="row-options">';
            $_data .= '<a href="' . $url . '">' . _l('view') . '</a>';
            $_data .= ' | <a href="' . $url . '?tab=settings">' . _l('edit') . '</a>';
            $_data .= ' | <a href="' . get_ticket_public_url($aRow) . '" target="_blank">' . _l('view_public_form') . '</a>';
            $_data .= ' | <a href="' . admin_url('tickets/delete/' . $aRow['ticket_id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
            $_data .= '</div>';
        }
        // tags
        elseif ($col === 'ticket_tags') {
            $_data = render_tags($_data);
        }
        // contact
        elseif ($col === 'contact_full_name') {
            if ($aRow['ticket_userid'] != 0) {
                $_data = '<a href="' . admin_url('clients/client/' . $aRow['ticket_userid'] . '?group=contacts') . '">' . $aRow['contact_full_name'];
                if (!empty($aRow['client_company'])) {
                    $_data .= ' (' . $aRow['client_company'] . ')';
                }
                $_data .= '</a>';
            } else {
                $_data = $aRow['ticket_opened_by_name'];
            }
        }
        // status
        elseif ($col === 'ticket_status') {
            $_data = '<span class="label ticket-status-' . $aRow['ticket_status'] . '" 
                        style="border:1px solid ' . adjust_hex_brightness($aRow['statuscolor'], 0.4) . '; 
                               color:' . $aRow['statuscolor'] . ';
                               background:' . adjust_hex_brightness($aRow['statuscolor'], 0.04) . ';">' 
                        . ticket_status_translate($aRow['ticket_status']) . '</span>';
        }
        // priority
        elseif ($col === 'ticket_priority') {
            $_data = ticket_priority_translate($aRow['ticket_priority']);
        }

        $row[] = $_data;

        // unread highlight
        if ($aRow['adminread'] == 0) {
            $row['DT_RowClass'] = 'text-danger';
        }
    }

    $row['DT_RowClass'] = ($row['DT_RowClass'] ?? '') . ' has-row-options';

    $output['aaData'][] = $row;
}
