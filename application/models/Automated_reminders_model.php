<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Automated_reminders_model extends App_Model
{
    public function run_all()
    {
        $this->pending_followups();
        $this->missed_callbacks();
        $this->missed_checkins();
    }

    // ---------------------------------------------------
    // 1. Pending Lead Follow-ups
    // ---------------------------------------------------
    private function pending_followups()
    {
        $leads = $this->db
            ->where('is_active', 'active')
            ->where('(lastcontact IS NULL OR lastcontact < DATE_SUB(NOW(), INTERVAL 2 DAY))', null, false)
            ->where('assigned !=', 0)
            ->get(db_prefix() . 'leads')
            ->result();

        foreach ($leads as $lead) {

            if ($this->reminder_exists('lead', $lead->id)) {
                continue;
            }

            $this->create_reminder([
                'rel_type' => 'lead',
                'rel_id'   => $lead->id,
                'staff'    => $lead->assigned,
                'description' => 'Pending follow-up missed for lead: ' . $lead->name,
            ]);
        }
    }

    // ---------------------------------------------------
    // 2. Missed Callbacks
    // ---------------------------------------------------
    private function missed_callbacks()
    {
        $calls = $this->db
            ->where('is_success', 0)
            ->where('created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)', null, false)
            ->get(db_prefix() . 'call_logs')
            ->result();

        foreach ($calls as $call) {

            if ($this->reminder_exists('call', $call->id)) {
                continue;
            }

            $this->create_reminder([
                'rel_type' => 'call',
                'rel_id'   => $call->id,
                'staff'    => $call->staff_id,
                'description' => 'Missed callback for Lead ID: ' . $call->lead_id,
            ]);
        }
    }

    // ---------------------------------------------------
    // 3. Missed Attendance Check-outs
    // ---------------------------------------------------
    private function missed_checkins()
    {
        $rows = $this->db
            ->where('status', 'CHECKED_IN')
            ->where('check_in_time < DATE_SUB(NOW(), INTERVAL 9 HOUR)', null, false)
            ->get(db_prefix() . 'taskstimers')
            ->result();

        foreach ($rows as $row) {

            if ($this->reminder_exists('attendance', $row->id)) {
                continue;
            }

            $this->create_reminder([
                'rel_type' => 'attendance',
                'rel_id'   => $row->id,
                'staff'    => $row->staff_id,
                'description' => 'Missed check-out detected',
            ]);
        }
    }

    // ---------------------------------------------------
    // Shared Reminder Creator
    // ---------------------------------------------------
    private function create_reminder($data)
    {
        $reminder = [
            'rel_type'        => $data['rel_type'],
            'rel_id'          => $data['rel_id'],
            'staff'           => $data['staff'],
            'creator'         => 0, // system
            'notify_by_email' => 1,
            'isnotified'      => 0,
            'date'            => date('Y-m-d H:i:s'),
            'description'     => nl2br($data['description']),
        ];

        $this->db->insert(db_prefix() . 'reminders', $reminder);

        $this->create_notification($data);
    }

    // ---------------------------------------------------
    // Notification Creator
    // ---------------------------------------------------
    private function create_notification($data)
    {
        $this->db->insert(db_prefix() . 'notifications', [
            'touserid'      => $data['staff'],
            'fromuserid'    => 0,
            'from_fullname' => 'System',
            'date'          => date('Y-m-d H:i:s'),
            'description'   => $data['description'],
            'link'          => 'admin/' . $data['rel_type'] . '/' . $data['rel_id'],
        ]);
    }

    // ---------------------------------------------------
    // Prevent Duplicate Reminders
    // ---------------------------------------------------
    private function reminder_exists($rel_type, $rel_id)
    {
        return $this->db
            ->where('rel_type', $rel_type)
            ->where('rel_id', $rel_id)
            ->where('isnotified', 0)
            ->count_all_results(db_prefix() . 'reminders') > 0;
    }
}
