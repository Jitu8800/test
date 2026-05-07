<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dialer_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get current dialer status row for staff.
     */
    public function get_status($staff_id)
    {
        return $this->db
            ->where('staff_id', $staff_id)
            ->get('tbldialer_status')
            ->row();
    }

    /**
     * Set dialer as running (start or resume).
     */
    public function set_running($staff_id)
    {
        $exists = $this->get_status($staff_id);

        $data = [
            'is_running' => 1,
            'is_paused' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($exists) {
            return $this->db
                ->where('staff_id', $staff_id)
                ->update('tbldialer_status', $data);
        }

        $data['staff_id'] = $staff_id;
        $data['created_at'] = date('Y-m-d H:i:s');

        return $this->db->insert('tbldialer_status', $data);
    }

    /**
     * Pause dialer.
     */
    public function pause($staff_id)
    {
        $exists = $this->get_status($staff_id);

        $data = [
            'is_running' => 0,
            'is_paused' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($exists) {
            return $this->db
                ->where('staff_id', $staff_id)
                ->update('tbldialer_status', $data);
        }

        $data['staff_id'] = $staff_id;
        $data['created_at'] = date('Y-m-d H:i:s');

        return $this->db->insert('tbldialer_status', $data);
    }

    /**
     * Stop dialer — both flags 0 (used for resume→idle cycle).
     */
    public function stop($staff_id)
    {
        $exists = $this->get_status($staff_id);

        $data = [
            'is_running' => 0,
            'is_paused' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($exists) {
            return $this->db
                ->where('staff_id', $staff_id)
                ->update('tbldialer_status', $data);
        }

        $data['staff_id'] = $staff_id;
        $data['created_at'] = date('Y-m-d H:i:s');

        return $this->db->insert('tbldialer_status', $data);
    }

    /**
     * Check if dialer is currently running for staff.
     */
    public function is_running($staff_id)
    {
        $row = $this->get_status($staff_id);

        return $row && $row->is_running == 1 && $row->is_paused == 0;
    }

    /**
     * Check if dialer is paused for staff.
     */
    public function is_paused($staff_id)
    {
        $row = $this->get_status($staff_id);

        return $row && $row->is_paused == 1;
    }
}
