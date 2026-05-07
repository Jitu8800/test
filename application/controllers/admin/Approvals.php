<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Approvals extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Approval_model');
        $this->config->load('approval');
    }

    public function index()
    {
        $staff = get_staff(get_staff_user_id());
        $approval_roles = $this->config->item('approval_roles');

        $allowed_levels = [];

        // Super Admin
        if (empty($staff->role)) {
            $allowed_levels = 'ALL';
        } else {
            foreach ($approval_roles as $level => $roles) {
                if (in_array($staff->role, $roles)) {
                    $allowed_levels[] = $level;
                }
            }
        }

        $this->db->select('a.*, l.name, l.company');
        $this->db->from('tblapproval_requests a');
        $this->db->join('tblleads l', 'l.id = a.entity_id', 'left');
        $this->db->where('a.status', 'pending');

        if ($allowed_levels !== 'ALL') {
            if (!empty($allowed_levels)) {
                $this->db->where_in('a.current_level', $allowed_levels);
            } else {
                $this->db->where('1 = 0');
            }
        }

        $this->db->order_by('a.created_at', 'ASC');

        $data['approvals'] = $this->db->get()->result();
        $data['title'] = 'Approval Center';

            redirect(admin_url());

    }

    public function approve($id)
    {
        $this->Approval_model->approve(
            $id,
            get_staff_user_id(),
            $this->input->post('remarks')
        );

        set_alert('success', 'Request approved');
        redirect(admin_url('approvals'));
    }

    public function reject($id)
    {
        $this->Approval_model->reject(
            $id,
            get_staff_user_id(),
            $this->input->post('remarks')
        );

        set_alert('warning', 'Request rejected');
        redirect(admin_url('approvals'));
    }
}
