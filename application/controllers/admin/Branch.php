<?php

use app\services\imap\Imap;
use app\services\LeadProfileBadges;
use app\services\leads\LeadsKanban;
use app\services\imap\ConnectionErrorException;
use Ddeboer\Imap\Exception\MailboxDoesNotExistException;

header('Content-Type: text/html; charset=utf-8');
defined('BASEPATH') or exit('No direct script access allowed');

class Branch extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Branch_model','leads_model');
    }


    
    public function index($id = '')
{
    
    if (!is_admin()) {
        access_denied('Web To Lead Access');
    }

    if ($this->input->is_ajax_request()) {
        $this->app->get_table_data('web_to_lead');
    }

    $data['title'] = _l('Branches Management');
    $data['branches'] = $this->db
        ->get(db_prefix() . 'branches')
        ->result_array();
 
  
 

    $this->load->view('admin/branches/manage_branches', $data);
}
 

    public function table()
    {
        if (!is_staff_member()) {
            ajax_access_denied();
        }
        $this->app->get_table_data($_POST['last_order_identifier']);
    }

    public function kanban()
    {
        if (!is_staff_member()) {
            ajax_access_denied();
        }

        $data['statuses']      = $this->leads_model->get_status();
        $data['base_currency'] = get_base_currency();
        $data['summary']       = get_leads_summary();

        echo $this->load->view('admin/branches/kan-ban', $data, true);
    }

        
    public function management($id = '')
{
    if (!is_admin()) {
        access_denied('Web To Lead Access');
    }

    if ($this->input->is_ajax_request()) {
        $this->app->get_table_data('web_to_lead');
    }

    $data['title'] = _l('Leads Management');
    $data['branches'] = $this->config->item('branches');

    $data['unsigned_leads_count'] = $this->db
        ->where('assigned', 0)
        ->count_all_results(db_prefix() . 'leads');

    $data['members'] = $this->db
        ->where('admin', 0)
        ->where('active', 1)
        ->get(db_prefix() . 'staff')
        ->result_array();

    $data['campaigns'] = $this->db
        ->select("
            l.source,
            COALESCE(s.name, 'Unknown') as source_name,
            GROUP_CONCAT(DISTINCT COALESCE(NULLIF(l.source_campaign, ''), 'N/A')) as campaigns,
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN l.assigned > 0 THEN 1 ELSE 0 END) as assigned_leads,
            SUM(CASE WHEN l.assigned = 0 THEN 1 ELSE 0 END) as remaining_leads,
            MAX(l.is_active) as status
        ")
        ->from(db_prefix() . 'leads l')
        ->join(db_prefix() . 'leads_sources s', 's.id = l.source', 'left')
        ->group_by('l.source')
        ->get()
        ->result_array();

    $data['totals'] = $this->db
        ->select("
            COUNT(id) as total_leads,
            SUM(CASE WHEN assigned > 0 THEN 1 ELSE 0 END) as total_assigned,
            SUM(CASE WHEN assigned = 0 THEN 1 ELSE 0 END) as total_unassigned
        ")
        ->from(db_prefix() . 'leads')
        ->get()
        ->row_array();

    $data['leadSources'] = $this->db
        ->select("s.id, s.name")
        ->from(db_prefix() . 'leads_sources s')
        ->join(db_prefix() . 'leads l', 'l.source = s.id', 'inner')
        ->group_by('s.id')
        ->get()
        ->result_array();

    $data['leadCampaigns'] = $this->db
        ->select("l.source_campaign")
        ->from(db_prefix() . 'leads l')
        ->where("l.source_campaign IS NOT NULL AND l.source_campaign != ''")
        ->group_by('l.source_campaign')
        ->get()
        ->result_array();
// echo "<pre>";print_r($data);die;
    $this->load->view('admin/branches/forms', $data);
}

 
public function assign_leads()
{
    $branch_ids   = $this->input->post('branch_ids');
    $responsible  = $this->input->post('responsible');
    $leads_count  = (int) $this->input->post('leads_count');
    $campaigns    = $this->input->post('campaigns');
    $source_id    = $this->input->post('source_id');

    if (empty($branch_ids) || $leads_count <= 0 || empty($source_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please select branch, agent(s), source, and enter a valid leads count.',
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
        ]);
        return;
    }

    if (in_array("ALL", $branch_ids)) {
        $this->db->where('active', 1);
        $allStaff = $this->db->get('tblstaff')->result_array();

        if (empty($allStaff)) {
            echo json_encode([
                'success' => false,
                'message' => 'No active staff found.',
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
            ]);
            return;
        }

        $responsible = array_column($allStaff, 'staffid');
        $branch_ids  = array_column($allStaff, 'branch_id');
    } else {
        $this->db->where_in('id', $branch_ids);
        $validBranches = $this->db->get('tblbranches')->result_array();
        if (count($validBranches) != count($branch_ids)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid branch selected!',
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
            ]);
            return;
        }

        $this->db->where_in('staffid', $responsible);
        $this->db->where_in('branch_id', $branch_ids);
        $validUsers = $this->db->get('tblstaff')->result_array();
        if (count($validUsers) != count($responsible)) {
            echo json_encode([
                'success' => false,
                'message' => 'Some selected users are not part of chosen branches.',
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
            ]);
            return;
        }
    }

    $this->db->where('assigned', 0);
    $this->db->where('source', $source_id);
     
    $available = $this->db->count_all_results('tblleads');

    if ($leads_count > $available) {
        echo json_encode([
            'success' => false,
            'message' => 'Not enough leads available. Requested: ' . $leads_count . ', Available: ' . $available,
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
        ]);
        return;
    }

    $this->db->where('assigned', 0);
    $this->db->where('source', $source_id);
    
    $this->db->limit($leads_count);
    $leads = $this->db->get('tblleads')->result_array();

    if (empty($leads)) {
        echo json_encode([
            'success' => false,
            'message' => 'No leads available to assign.',
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
        ]);
        return;
    }

    $assignedTotal = 0;
    $userIndex = 0;
    $userCount = count($responsible);

    foreach ($leads as $lead) {
        $staffid = $responsible[$userIndex];

        $this->db->where('id', $lead['id']);
        $this->db->update('tblleads', [
            'assigned'      => $staffid,
            'dateassigned'  => date('Y-m-d'),
        ]);

        $assignedTotal++;
        $userIndex = ($userIndex + 1) % $userCount;
    }

    echo json_encode([
        'success' => true,
        'message' => $assignedTotal . ' leads assigned successfully!',
        $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
    ]);
}


/* Manage leads sources */
    public function sources()
    {
        if (!is_admin()) {
            access_denied('Branch Sources');
        }
        $data['sources'] = $this->leads_model->get_source();
        $data['title']   = 'Leads branch';
        $this->load->view('admin/branches/manage_sources', $data);
    }


  
    /* Add or update leads sources */
    public function source()
    {
        if (!is_admin() && get_option('staff_members_create_inline_lead_source') == '0') {
            access_denied('Branch Sources');
        }
        if ($this->input->post()) {
            $data = $this->input->post();
            if (!$this->input->post('id')) {
                $inline = isset($data['inline']);
                if (isset($data['inline'])) {
                    unset($data['inline']);
                }

                $id = $this->leads_model->add_source($data);

                if (!$inline) {
                    if ($id) {
                        set_alert('success', _l('added_successfully', _l('lead_branch')));
                    }
                } else {
                    echo json_encode(['success' => $id ? true : false, 'id' => $id]);
                }
            } else {
                $id = $data['id'];
                unset($data['id']);
                $success = $this->leads_model->update_source($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('lead_branch')));
                }
            }
        }
    }

    /* Delete leads source */
    public function delete_source($id)
{
    if (!is_admin()) {
        access_denied('Delete Lead Source');
    }
    if (!$id) {
        redirect(admin_url('branch'));
    }

    $response = $this->leads_model->delete_source($id);
     

    if (is_array($response) && isset($response['referenced'])) {
        set_alert('warning', _l('is_referenced', _l('lead_source_lowercase')));
    } elseif ($response == true) {
        set_alert('success', _l('deleted', _l('lead_branch')));
    } else {
        set_alert('warning', _l('problem_deleting', _l('lead_source_lowercase')));
    }

    redirect(admin_url('branch')); // ✅ अब 404 नहीं देगा
}


    // Statuses
    /* View leads statuses */
    public function statuses()
    {
        if (!is_admin()) {
            access_denied('Leads Statuses');
        }
        $data['statuses'] = $this->leads_model->get_status();
        $data['title']    = 'Leads statuses';
        $this->load->view('admin/branches/manage_statuses', $data);
    }

    /* Add or update leads status */
    public function status()
    {
        if (!is_admin() && get_option('staff_members_create_inline_lead_status') == '0') {
            access_denied('Leads Statuses');
        }
        if ($this->input->post()) {
            $data = $this->input->post();
            if (!$this->input->post('id')) {
                $inline = isset($data['inline']);
                if (isset($data['inline'])) {
                    unset($data['inline']);
                }
                $id = $this->leads_model->add_status($data);
                if (!$inline) {
                    if ($id) {
                        set_alert('success', _l('added_successfully', _l('lead_status')));
                    }
                } else {
                    echo json_encode(['success' => $id ? true : false, 'id' => $id]);
                }
            } else {
                $id = $data['id'];
                unset($data['id']);
                $success = $this->leads_model->update_status($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('lead_status')));
                }
            }
        }
    }

    /* Delete leads status from databae */
    public function delete_status($id)
    {
        if (!is_admin()) {
            access_denied('Leads Statuses');
        }
        if (!$id) {
            redirect(admin_url('leads/statuses'));
        }
        $response = $this->leads_model->delete_status($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('lead_status_lowercase')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('lead_status')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('lead_status_lowercase')));
        }
        redirect(admin_url('leads/statuses'));
    }

    /* Add new lead note */
    public function add_note($rel_id)
    {
        if (!is_staff_member() || !$this->leads_model->staff_can_access_lead($rel_id)) {
            ajax_access_denied();
        }

        if ($this->input->post()) {
            $data = $this->input->post();

            if ($data['contacted_indicator'] == 'yes') {
                $contacted_date         = to_sql_date($data['custom_contact_date'], true);
                $data['date_contacted'] = $contacted_date;
            }

            unset($data['contacted_indicator']);
            unset($data['custom_contact_date']);

            // Causing issues with duplicate ID or if my prefixed file for lead.php is used
            $data['description'] = isset($data['lead_note_description']) ? $data['lead_note_description'] : $data['description'];

            if (isset($data['lead_note_description'])) {
                unset($data['lead_note_description']);
            }

            $note_id = $this->misc_model->add_note($data, 'lead', $rel_id);

            if ($note_id) {
                if (isset($contacted_date)) {
                    $this->db->where('id', $rel_id);
                    $this->db->update(db_prefix() . 'leads', [
                        'lastcontact' => $contacted_date,
                    ]);
                    if ($this->db->affected_rows() > 0) {
                        $this->leads_model->log_lead_activity($rel_id, 'not_lead_activity_contacted', false, serialize([
                            get_staff_full_name(get_staff_user_id()),
                            _dt($contacted_date),
                        ]));
                    }
                }
            }
        }
        echo json_encode(['leadView' => $this->_get_lead_data($rel_id), 'id' => $rel_id]);
    }

    public function email_integration_folders()
    {
        if (!is_admin()) {
            ajax_access_denied('Leads Test Email Integration');
        }

        app_check_imap_open_function();

        $imap = new Imap(
            $this->input->post('email'),
            $this->input->post('password', false),
            $this->input->post('imap_server'),
            $this->input->post('encryption')
        );

        try {
            echo json_encode($imap->getSelectableFolders());
        } catch (ConnectionErrorException $e) {
            echo json_encode([
                'alert_type' => 'warning',
                'message'    => $e->getMessage(),
            ]);
        }
    }

    public function test_email_integration()
    {
        if (!is_admin()) {
            access_denied('Leads Test Email Integration');
        }

        app_check_imap_open_function(admin_url('leads/email_integration'));

        $mail     = $this->leads_model->get_email_integration();
        $password = $mail->password;

        if (false == $this->encryption->decrypt($password)) {
            set_alert('danger', _l('failed_to_decrypt_password'));
            redirect(admin_url('leads/email_integration'));
        }

        $imap = new Imap(
            $mail->email,
            $this->encryption->decrypt($password),
            $mail->imap_server,
            $mail->encryption
        );

        try {
            $connection = $imap->testConnection();

            try {
                $connection->getMailbox($mail->folder);
                set_alert('success', _l('lead_email_connection_ok'));
            } catch (MailboxDoesNotExistException $e) {
                set_alert('danger', str_replace(["\n", 'Mailbox'], ['<br />', 'Folder'], addslashes($e->getMessage())));
            }
        } catch (ConnectionErrorException $e) {
            $error = str_replace("\n", '<br />', addslashes($e->getMessage()));
            set_alert('danger', _l('lead_email_connection_not_ok') . '<br /><br /><b>' . $error . '</b>');
        }

        redirect(admin_url('leads/email_integration'));
    }

    public function email_integration()
    {
        if (!is_admin()) {
            access_denied('Leads Email Intregration');
        }
        if ($this->input->post()) {
            $data             = $this->input->post();
            $data['password'] = $this->input->post('password', false);

            if (isset($data['fakeusernameremembered'])) {
                unset($data['fakeusernameremembered']);
            }
            if (isset($data['fakepasswordremembered'])) {
                unset($data['fakepasswordremembered']);
            }

            $success = $this->leads_model->update_email_integration($data);
            if ($success) {
                set_alert('success', _l('leads_email_integration_updated'));
            }
            redirect(admin_url('leads/email_integration'));
        }
        $data['roles']    = $this->roles_model->get();
        $data['sources']  = $this->leads_model->get_source();
        $data['statuses'] = $this->leads_model->get_status();

        $data['members'] = $this->staff_model->get('', [
            'active'       => 1,
            'is_not_staff' => 0,
        ]);

        $data['title'] = _l('leads_email_integration');
        $data['mail']  = $this->leads_model->get_email_integration();

        $data['bodyclass'] = 'leads-email-integration';
        $this->load->view('admin/branches/email_integration', $data);
    }

    public function change_status_color()
    {
        if ($this->input->post() && is_admin()) {
            $this->leads_model->change_status_color($this->input->post());
        }
    }

    public function import()
    {
        if (!is_admin() && get_option('allow_non_admin_members_to_import_leads') != '1') 
        {
            access_denied('Leads Import');
        }

        $dbFields = $this->db->list_fields(db_prefix() . 'leads');
        array_push($dbFields, 'tags');

        $this->load->library('import/import_leads', [], 'import');
        $this->import->setDatabaseFields($dbFields)->setCustomFields(get_custom_fields('leads'));

        if ($this->input->post('download_sample') === 'true') {
            $this->import->downloadSample();
        }

        if ($this->input->post()
            && isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '')
        {
            //echo "yes";die;
            $this->import->setSimulation($this->input->post('simulate'))
                          ->setTemporaryFileLocation($_FILES['file_csv']['tmp_name'])
                          ->setFilename($_FILES['file_csv']['name'])
                          ->perform();

            $data['total_rows_post'] = $this->import->totalRows();

           // echo $data['total_rows_post'];die;
            if (!$this->import->isSimulation()) {
                set_alert('success', _l('import_total_imported', $this->import->totalImported()));
            }
        }

        $data['statuses'] = $this->leads_model->get_status();
        $data['sources']  = $this->leads_model->get_source();
        $data['members']  = $this->staff_model->get('', ['is_not_staff' => 0, 'active' => 1]);

        $data['title'] = _l('import');
        $this->load->view('admin/branches/import', $data);
    }



    public function get_users_by_branch()
{
    $branch_ids = $this->input->post('branch_id'); 

    if (!empty($branch_ids)) {
        if (is_array($branch_ids) && in_array("", $branch_ids)) {
            $users = $this->db->get('staff')->result_array();
        } elseif (is_array($branch_ids)) {
            $this->db->where_in('branch_id', $branch_ids);
            $users = $this->db->get('staff')->result_array();
        } else {
            $this->db->where('branch_id', $branch_ids);
            $users = $this->db->get('staff')->result_array();
        }
    } else {
        $users = [];
    }

    echo json_encode($users);
}





    public function validate_unique_field()
    {
        if ($this->input->post()) {

            // First we need to check if the field is the same
            $lead_id = $this->input->post('lead_id');
            $field   = $this->input->post('field');
            $value   = $this->input->post($field);

            if ($lead_id != '') {
                $this->db->select($field);
                $this->db->where('id', $lead_id);
                $row = $this->db->get(db_prefix() . 'leads')->row();
                if ($row->{$field} == $value) {
                    echo json_encode(true);
                    die();
                }
            }

            echo total_rows(db_prefix() . 'leads', [ $field => $value ]) > 0 ? 'false' : 'true';
        }
    }

    public function bulk_action()
    {
        if (!is_staff_member()) {
            ajax_access_denied();
        }

        hooks()->do_action('before_do_bulk_action_for_leads');
        $total_deleted = 0;
        if ($this->input->post()) {
            $ids                   = $this->input->post('ids');
            $status                = $this->input->post('status');
            $source                = $this->input->post('source');
            $assigned              = $this->input->post('assigned');
            $visibility            = $this->input->post('visibility');
            $tags                  = $this->input->post('tags');
            $last_contact          = $this->input->post('last_contact');
            $lost                  = $this->input->post('lost');
            $has_permission_delete = has_permission('leads', '', 'delete');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if ($this->input->post('mass_delete')) {
                        if ($has_permission_delete) {
                            if ($this->leads_model->delete($id)) {
                                $total_deleted++;
                            }
                        }
                    } else {
                        if ($status || $source || $assigned || $last_contact || $visibility) {
                            $update = [];
                            if ($status) {
                                // We will use the same function to update the status
                                $this->leads_model->update_lead_status([
                                    'status' => $status,
                                    'leadid' => $id,
                                ]);
                            }
                            if ($source) {
                                $update['source'] = $source;
                            }
                            if ($assigned) {
                                $update['assigned'] = $assigned;
                            }
                            if ($last_contact) {
                                $last_contact          = to_sql_date($last_contact, true);
                                $update['lastcontact'] = $last_contact;
                            }

                            if ($visibility) {
                                if ($visibility == 'public') {
                                    $update['is_public'] = 1;
                                } else {
                                    $update['is_public'] = 0;
                                }
                            }

                            if (count($update) > 0) {
                                $this->db->where('id', $id);
                                $this->db->update(db_prefix() . 'leads', $update);
                            }
                        }
                        if ($tags) {
                            handle_tags_save($tags, $id, 'lead');
                        }
                        if ($lost == 'true') {
                            $this->leads_model->mark_as_lost($id);
                        }
                    }
                }
            }
        }

        if ($this->input->post('mass_delete')) {
            set_alert('success', _l('total_leads_deleted', $total_deleted));
        }
    }

    public function download_files($lead_id)
    {
        if (!is_staff_member() || !$this->leads_model->staff_can_access_lead($lead_id)) {
            ajax_access_denied();
        }

        $files = $this->leads_model->get_lead_attachments($lead_id);

        if (count($files) == 0) {
            redirect($_SERVER['HTTP_REFERER']);
        }

        $path = get_upload_path_by_type('lead') . $lead_id;

        $this->load->library('zip');

        foreach ($files as $file) {
            $this->zip->read_file($path . '/' . $file['file_name']);
        }

        $this->zip->download('files.zip');
        $this->zip->clear_data();
    }
}