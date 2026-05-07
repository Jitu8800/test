<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Approval_model extends CI_Model
{
    public function create_request($data)
    {
        $this->db->insert('tblapproval_requests', $data);
        return $this->db->insert_id();
    }

    public function get_request($id)
    {
        return $this->db->get_where('tblapproval_requests', ['id' => $id])->row();
    }

    private function can_approve($approval, $staff_id)
    {
        $staff = get_staff($staff_id);

        // Super Admin (no role)
        if (empty($staff->role)) {
            return true;
        }

        $this->config->load('approval');
        $approval_roles = $this->config->item('approval_roles');

        return isset($approval_roles[$approval->current_level]) &&
               in_array($staff->role, $approval_roles[$approval->current_level]);
    }

    public function approve($approval_id, $staff_id, $remarks = '')
    {
        $approval = $this->get_request($approval_id);

        if (!$approval || $approval->status !== 'pending') {
            return false;
        }

        if (!$this->can_approve($approval, $staff_id)) {
            return false;
        }

        $this->db->insert('tblapproval_logs', [
            'approval_id' => $approval_id,
            'level'       => $approval->current_level,
            'action'      => 'approved',
            'action_by'   => $staff_id,
            'remarks'     => $remarks
        ]);

        if ($approval->current_level >= $approval->total_levels) {

            $this->db->where('id', $approval_id)->update('tblapproval_requests', [
                'status'     => 'approved',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $this->apply_entity_action($approval);

        } else {
            $this->db->where('id', $approval_id)->update('tblapproval_requests', [
                'current_level' => $approval->current_level + 1
            ]);
        }

        return true;
    }

    public function reject($approval_id, $staff_id, $remarks = '')
    {
        $approval = $this->get_request($approval_id);

        if (!$approval || $approval->status !== 'pending') {
            return false;
        }

        if (!$this->can_approve($approval, $staff_id)) {
            return false;
        }

        $this->db->insert('tblapproval_logs', [
            'approval_id' => $approval_id,
            'level'       => $approval->current_level,
            'action'      => 'rejected',
            'action_by'   => $staff_id,
            'remarks'     => $remarks
        ]);

        $this->db->where('id', $approval_id)->update('tblapproval_requests', [
            'status'     => 'rejected',
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return true;
    }

    private function apply_entity_action($approval)
    {
        if ($approval->entity_type === 'discount') {

            $this->db->where('id', $approval->entity_id);
            $this->db->update('tblleads', [
                'payment_discount' => $approval->requested_amount
            ]);

        } elseif ($approval->entity_type === 'refund') {

            $this->db->where('id', $approval->entity_id);
            $this->db->update('tblleads', [
                'refund_status'    => 'pending',
                'refunded_amount' => $approval->requested_amount
            ]);
        }
    }
}
