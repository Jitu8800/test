<?php

defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_action('app_admin_head', 'leads_app_admin_head_data');

function leads_app_admin_head_data()
{
    ?>
    <script>
        var leadUniqueValidationFields = <?php echo json_decode(json_encode(get_option('lead_unique_validation'))); ?>;
        var leadAttachmentsDropzone;
    </script>
    <?php
}

/**
 * Check if the user is lead creator
 * @since  Version 1.0.4
 * @param  mixed  $leadid leadid
 * @param  mixed  $staff_id staff id (Optional)
 * @return boolean
 */

function is_lead_creator($lead_id, $staff_id = '')
{
    if (!is_numeric($staff_id)) {
        $staff_id = get_staff_user_id();
    }

    return total_rows(db_prefix() . 'leads', [
        'addedfrom' => $staff_id,
        'id'        => $lead_id,
    ]) > 0;
}

/**
 * Lead consent URL
 * @param  mixed $id lead id
 * @return string
 */
function lead_consent_url($id)
{
    return site_url('consent/l/' . get_lead_hash($id));
}

/**
 * Lead public form URL
 * @param  mixed $id lead id
 * @return string
 */
function leads_public_url($id)
{
    return site_url('forms/l/' . get_lead_hash($id));
}

/**
 * Get and generate lead hash if don't exists.
 * @param  mixed $id  lead id
 * @return string
 */
function get_lead_hash($id)
{
    $CI   = &get_instance();
    $hash = '';

    $CI->db->select('hash');
    $CI->db->where('id', $id);
    $lead = $CI->db->get(db_prefix() . 'leads')->row();
    if ($lead) {
        $hash = $lead->hash;
        if (empty($hash)) {
            $hash = app_generate_hash() . '-' . app_generate_hash();
            $CI->db->where('id', $id);
            $CI->db->update(db_prefix() . 'leads', ['hash' => $hash]);
        }
    }

    return $hash;
}

/**
 * Get leads summary
 * @return array
 */
function get_leads_summary()
{
    $CI = &get_instance();
    if (!class_exists('leads_model')) {
        $CI->load->model('leads_model');
    }
    $statuses = $CI->leads_model->get_status();

    $totalStatuses         = count($statuses);
    $has_permission_view   = has_permission('leads', '', 'view');
    $sql                   = '';
    $whereNoViewPermission = '(addedfrom = ' . get_staff_user_id() . ' OR assigned=' . get_staff_user_id() . ' OR is_public = 1)';

    $statuses[] = [
        'lost'  => true,
        'name'  => _l('lost_leads'),
        'color' => '#fc2d42',
    ];

/*    $statuses[] = [
        'junk'  => true,
        'name'  => _l('junk_leads'),
        'color' => '',
    ];*/

    foreach ($statuses as $status) {
        $sql .= ' SELECT COUNT(*) as total';
        $sql .= ' FROM ' . db_prefix() . 'leads';

        if (isset($status['lost'])) {
            $sql .= ' WHERE lost=1';
        } elseif (isset($status['junk'])) {
            $sql .= ' WHERE junk=1';
        } else {
            $sql .= ' WHERE status=' . $status['id'];
        }
        if (!$has_permission_view) {
            $sql .= ' AND ' . $whereNoViewPermission;
        }
        $sql .= ' UNION ALL ';
        $sql = trim($sql);
    }

    $result = [];

    // Remove the last UNION ALL
    $sql    = substr($sql, 0, -10);
    $result = $CI->db->query($sql)->result();

    if (!$has_permission_view) {
        $CI->db->where($whereNoViewPermission);
    }

    $total_leads = $CI->db->count_all_results(db_prefix() . 'leads');

    // foreach ($statuses as $key => $status) {
    //     if (isset($status['lost']) || isset($status['junk'])) {
    //         $statuses[$key]['percent'] = ($total_leads > 0 ? number_format(($result[$key]->total * 100) / $total_leads, 2) : 0);
    //     }

    //     $statuses[$key]['total'] = $result[$key]->total;
    //     $statuses[$key]['value'] = $result[$key]->value;
    // }

    return $statuses;
}



// customer summary


function get_customers_summary()
{
    $CI = &get_instance();
    if (!class_exists('leads_model')) {
        $CI->load->model('leads_model');
    }

    $has_permission_view   = has_permission('leads', '', 'view');
    $sql                   = '';
    $whereNoViewPermission = '(addedfrom = ' . get_staff_user_id() . ' OR assigned=' . get_staff_user_id() . ' OR is_public = 1)';

    // Define customer statuses manually (active/inactive/break)
    $statuses = [
        [
            'id'    => 'active',
            'name'  => _l('customer_status_active'),
            'color' => '#28a745',
        ],
        [
            'id'    => 'inactive',
            'name'  => _l('customer_status_inactive'),
            'color' => '#ffc107',
        ],
        [
            'id'    => 'break',
            'name'  => _l('customer_status_break'),
            'color' => '#dc3545',
        ],
    ];
        $statuses = $CI->db->get(db_prefix() . 'customer_status')->result_array();

    foreach ($statuses as $status) {
        $sql .= ' SELECT COUNT(*) as total';
        $sql .= ' FROM ' . db_prefix() . 'leads';
        $sql .= " WHERE record_type = 'broker' AND customer_status='" . $status['id'] . "'";

        if (!$has_permission_view) {
            $sql .= ' AND ' . $whereNoViewPermission;
        }

        $sql .= ' UNION ALL ';
        $sql = trim($sql);
    }

    // Remove the last UNION ALL
    $sql    = substr($sql, 0, -10);
    $result = $CI->db->query($sql)->result();

    if (!$has_permission_view) {
        $CI->db->where($whereNoViewPermission);
    }

    $CI->db->where('record_type', 'broker');
    $total_customers = $CI->db->count_all_results(db_prefix() . 'leads');

    foreach ($statuses as $key => $status) {
        $statuses[$key]['total']   = $result[$key]->total;
        $statuses[$key]['percent'] = ($total_customers > 0
            ? number_format(($result[$key]->total * 100) / $total_customers, 2)
            : 0);
    }

    return $statuses;
}


/**
 * Render lead status select field with ability to create inline statuses with + sign
 * @param  array  $statuses         current statuses
 * @param  string  $selected        selected status
 * @param  string  $lang_key        the label of the select
 * @param  string  $name            the name of the select
 * @param  array   $select_attrs    additional select attributes
 * @param  boolean $exclude_default whether to exclude default Client status
 * @return string
 */
function render_leads_status_select($statuses, $selected = '', $lang_key = '', $name = 'status', $select_attrs = [], $exclude_default = false)
{
    foreach ($statuses as $key => $status) {
        if ($status['isdefault'] == 1) {
            if ($exclude_default == false) {
                $statuses[$key]['option_attributes'] = ['data-subtext' => _l('leads_converted_to_client')];
            } else {
                unset($statuses[$key]);
            }

            break;
        }
    }

    if (is_admin() || get_option('staff_members_create_inline_lead_status') == '1') {
        return render_select_with_input_group($name, $statuses, ['id', 'name'], $lang_key, $selected, '<div class="input-group-btn"><a href="#" class="btn btn-default" onclick="new_lead_status_inline();return false;" class="inline-field-new"><i class="fa fa-plus"></i></a></div>', $select_attrs);
    }

    return render_select($name, $statuses, ['id', 'name'], $lang_key, $selected, $select_attrs);
}


if (!function_exists('get_substatus')) {
    function get_substatus($status_id)
    {
        $CI = &get_instance();
        $CI->db->where('status_id', $status_id);
        $substatuses = $CI->db->get(db_prefix() . 'leads_substatus')->result_array();
        return $substatuses; 
    }
}



if (!function_exists('get_branches')) {
    function get_branches()
    {
        $CI = &get_instance();
        $branches = $CI->db->get(db_prefix() . 'branches')->result_array();
        return $branches;
    }
}


if (!function_exists('get_branch_name_by_id')) {
    function get_branch_name_by_id($branchId = null)
    {
        if (empty($branchId)) {
            return '-';
        }
        $CI = &get_instance();
        $CI->db->where('id', $branchId);
        $branch = $CI->db->get(db_prefix() . 'branches')->row();

        return $branch ? $branch->name : '-';
    }
}



/**
 * Render lead source select field with ability to create inline source with + sign
 * @param  array   $sources         current sourcees
 * @param  string  $selected        selected source
 * @param  string  $lang_key        the label of the select
 * @param  string  $name            the name of the select
 * @param  array   $select_attrs    additional select attributes
 * @return string
 */
function render_leads_source_select($sources, $selected = '', $lang_key = '', $name = 'source', $select_attrs = [])
{
    if (is_admin() || get_option('staff_members_create_inline_lead_source') == '1') {
        echo render_select_with_input_group($name, $sources, ['id', 'name'], $lang_key, $selected, '<div class="input-group-btn"><a href="#" class="btn btn-default" onclick="new_lead_source_inline();return false;" class="inline-field-new"><i class="fa fa-plus"></i></a></div>', $select_attrs);
    } else {
        echo render_select($name, $sources, ['id', 'name'], $lang_key, $selected, $select_attrs);
    }
}

/**
 * Load lead language
 * Used in public GDPR form
 * @param  string $lead_id
 * @return string return loaded language
 */
function load_lead_language($lead_id)
{
    $CI = & get_instance();
    $CI->db->where('id', $lead_id);
    $lead = $CI->db->get(db_prefix() . 'leads')->row();

    // Lead not found or default language already loaded
    if (!$lead || empty($lead->default_language)) {
        return false;
    }

    $language = $lead->default_language;

    if (!file_exists(APPPATH . 'language/' . $language)) {
        return false;
    }

    $CI->lang->is_loaded = [];
    $CI->lang->language  = [];

    $CI->lang->load($language . '_lang', $language);
    load_custom_lang_file($language);
    $CI->lang->set_last_loaded_language($language);

    return true;
}
