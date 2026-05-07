<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Webhook extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
        $this->load->model('leads_model');
        $this->load->database();

        require_once APPPATH.'vendor/autoload.php';
    }

    public function fetchCallDetails()
    {
        $raw = file_get_contents('php://input');
        $post = json_decode($raw, true);

        if (!$post) {
            echo json_encode(['status' => 'invalid_json']);

            return;
        }

        // BASIC VARIABLES
        $ref_id = $post['uuid'] ?? $post['ref_id'] ?? null;
        $lead_id = $post['reference_lead_id'] ?? null;
        $call_status = strtolower($post['call_status'] ?? '');
        $hangup_cause = strtolower($post['hangup_cause'] ?? '');
        $billsec = intval($post['billsec'] ?? 0);

        // CALL END DETECTION
        $call_ended = !empty($hangup_cause) || $billsec > 0;
        if (!empty($post['call_flow']) && is_array($post['call_flow'])) {
            foreach ($post['call_flow'] as $f) {
                if (($f['type'] ?? '') === 'hangup') {
                    $call_ended = true;
                }
            }
        }

        // DUPLICATE WEBHOOK CHECK — same table as insert
        if ($ref_id) {
            $exists = $this->db
                ->where('ref_id', $ref_id)
                ->count_all_results('tblcall_logs'); // ← fixed
            if ($exists > 0) {
                echo json_encode(['status' => 'duplicate']);

                return;
            }
        }

        // STAFF DETECTION
        $staff = null;
        $staff_id = null;
        $agent_id = $post['answered_agent']['id'] ?? null;
        $agent_ext = $post['answered_agent']['number'] ?? null;
        $agent_phone = $post['answered_agent']['agent_number'] ?? null;

        if ($agent_id) {
            $staff = $this->db->where('dialer_agent_id', $agent_id)->get('tblstaff')->row();
        }
        if (!$staff && $agent_ext) {
            $staff = $this->db->where('dialer_agent_id', $agent_ext)->get('tblstaff')->row();
        }
        if (!$staff && $agent_phone) {
            $staff = $this->db->where('dialer_phone', $agent_phone)->get('tblstaff')->row();
        }
        if ($staff) {
            $staff_id = $staff->staffid;
        }

        // DEBUG LOG
        file_put_contents(APPPATH.'/logs/call_debug.log',
            '['.date('Y-m-d H:i:s').']'
            .' ref='.$ref_id
            .' lead='.$lead_id
            .' staff='.$staff_id
            .' ended='.($call_ended ? 'yes' : 'no')
            .' billsec='.$billsec
            .' hangup='.$hangup_cause
            .PHP_EOL, FILE_APPEND
        );

        // SAVE CALL LOG
        $inserted = $this->db->insert('tblcall_logs', [
            'ref_id' => $ref_id,
            'lead_id' => $lead_id,
            'staff_id' => $staff_id,
            'status' => $billsec > 0 ? 'answered' : 'missed',
            'duration' => $billsec,
            'recording_url' => $post['recording_url'] ?? null,
            'call_to_number' => $post['call_to_number'] ?? null,
            'answered_agent_number' => $post['answered_agent_number'] ?? ($post['answered_agent']['number'] ?? null),
            'answered_agent_name' => $post['answered_agent_name'] ?? ($post['answered_agent']['name'] ?? null),
            'call_status' => $call_status,
            'webhookresponse' => json_encode($post),
            'created_at' => date('Y-m-d H:i:s'),
            'is_success' => $billsec > 0 ? 1 : 0, 
        ]);

        // Log insert result
        file_put_contents(APPPATH.'/logs/call_debug.log',
            '['.date('Y-m-d H:i:s').'] INSERT: '
            .($inserted ? 'OK' : 'FAILED — '.$this->db->error()['message'])
            .PHP_EOL, FILE_APPEND
        );

        // UPDATE LASTCONTACT — only on answered calls, no status change
        if ($lead_id && $call_ended && $billsec > 0) {
            $this->db->where('id', $lead_id)->update('tblleads', [
                'lastcontact' => date('Y-m-d H:i:s'),
            ]);
        }

        // UPDATE LEAD STATUS — only answered calls, only if still active
        if ($lead_id && $call_ended && $ref_id) {
            $active = $this->db->where('call_ref', $ref_id)->get('tblactive_calls')->row();
            // if ($active && $billsec > 0) {          // ← answered only, not missed
            //     $this->db->where('id', $lead_id)->update('tblleads', [
            //         'status'      => 4,
            //         'lastcontact' => date('Y-m-d H:i:s'),  // ← track when last called
            //     ]);
            // }
        }

        // REMOVE ACTIVE CALL
        if ($ref_id) {
            $this->db->where('call_ref', $ref_id)->delete('tblactive_calls');
        }

        echo json_encode(['status' => 'ok']);
    }

    // https://salescrm.businessbay.tech/api/Webhook/fetchCallDetails
}
