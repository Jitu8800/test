<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Campaign extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Campaign_model', 'campaign_model');
        if (!is_admin()) {
            access_denied('Campaign Assignments');
        }
    }

    public function index()
    {
        $data['campaigns'] = $this->campaign_model->get_campaigns_with_stats();
        $data['staff'] = $this->campaign_model->get_eligible_staff();
        $data['title'] = 'Campaign Assignments';

        // ← ADD — show message if no eligible staff
        $data['no_staff_message'] = empty($data['staff'])
            ? 'No staff currently checked in with auto-assign enabled.'
            : null;

        $this->load->view('admin/campaign/manage_campaign', $data);
    }

    public function save()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $source_id = (int) $this->input->post('source_id');
        $staff_ids = $this->input->post('staff_ids') ?: [];
        $staff_ids = array_map('intval', array_filter($staff_ids));

        $success = $this->campaign_model->save_assignment($source_id, $staff_ids);

        $campaign = $this->db->get_where('tblleads_sources', ['id' => $source_id])->row();
        $name = $campaign ? $campaign->name : 'Campaign';

        echo json_encode([
            'success' => $success,
            'message' => $success
                ? "Staff assigned to '{$name}' successfully"
                : "Failed to assign staff to '{$name}'",
        ]);
    }

    public function bulk_save()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $assignments = $this->input->post('assignments') ?: [];
        $success = true;
        $updated_campaigns = [];

        foreach ($assignments as $source_id => $staff_ids) {
            $staff_ids = array_map('intval', array_filter((array) $staff_ids));

            if ($this->campaign_model->save_assignment((int) $source_id, $staff_ids)) {
                $campaign = $this->db->get_where('tblleads_sources', ['id' => $source_id])->row();
                if ($campaign) {
                    $updated_campaigns[] = $campaign->name;
                }
            } else {
                $success = false;
            }
        }

        $message = $success
            ? 'Staff assigned successfully for: '.implode(', ', $updated_campaigns)
            : 'Some campaign assignments failed';

        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
    }

    public function campaign_is_on()
    {
        $source_id = $this->input->post('source_id');
        $status = $this->input->post('status') ? 1 : 0;

        $message = $status ? 'Campaign started successfully' : 'Campaign stopped successfully';

        $this->db->where('id', $source_id);
        $this->db->update('tblleads_sources', [
            'is_on' => $status,
        ]);

        echo json_encode(['success' => true, 'message' => $message]);
    }
}
