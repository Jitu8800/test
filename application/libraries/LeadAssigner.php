<?php

defined('BASEPATH') or exit('No direct script access allowed');

class LeadAssigner
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function autoAssignLeads()
    {
        $CI = $this->CI;

        // ── Fetch unassigned leads ────────────────────────────────
        $CI->db->select('id, source');
        $CI->db->from(db_prefix().'leads');
        $CI->db->where('assigned', 0);
        $unassignedLeads = $CI->db->get()->result_array();

        if (empty($unassignedLeads)) {
            return;
        }

        // ── STEP 1: Get all LOGGED-IN eligible staff ─────────────
        $CI->db->select('s.staffid, s.firstname, s.lastname');
        $CI->db->from(db_prefix().'staff AS s');
        $CI->db->join('tbltaskstimers AS t', 't.staff_id = s.staffid', 'inner');
        $CI->db->where('s.active', 1);
        $CI->db->where('s.admin !=', 1);
        $CI->db->where_in('s.role', [3, 14]);
        $CI->db->where('s.auto_assign_leads =', '1');
        $CI->db->where('t.status', 'CHECKED_IN');
        $CI->db->where('t.check_in_time IS NOT NULL', null, false);
        $CI->db->where('t.check_out_time IS NULL', null, false);
        $CI->db->where('t.check_in_time >=', date('Y-m-d H:i:s', strtotime('-8 hours')));
        $CI->db->group_by('s.staffid');

        $loggedInStaff = $CI->db->get()->result_array();

        if (empty($loggedInStaff)) {
            return;
        }

        // FIX 1: Cast all staffIds to int
        $staffIds = array_map('intval', array_column($loggedInStaff, 'staffid'));

        $staffNameMap = [];
        foreach ($loggedInStaff as $s) {
            $staffNameMap[(int) $s['staffid']] = trim($s['firstname'].' '.$s['lastname']);
        }

        // ── STEP 2: Count existing leads per staff ───────────────
        $CI->db->select('assigned, COUNT(id) AS lead_count');
        $CI->db->from(db_prefix().'leads');
        $CI->db->where_in('assigned', $staffIds);
        $CI->db->where('status', 4);
        $CI->db->group_by('assigned');

        $leadCounts = $CI->db->get()->result_array();

        $leadCountMap = array_fill_keys($staffIds, 0);
        foreach ($leadCounts as $row) {
            $leadCountMap[(int) $row['assigned']] = (int) $row['lead_count'];
        }

        // ── STEP 3: Load campaign rules ──────────────────────────
        $campaignRules = [];
        $staffCampaigns = [];

        $ruleRows = $CI->db
            ->select('source_id, staff_id')
            ->where('is_active', 1)
            ->get('tblcampaign_staff_assignments')
            ->result_array();

        foreach ($ruleRows as $r) {
            $sid = (int) $r['staff_id'];
            $src = (int) $r['source_id'];
            $campaignRules[$src][] = $sid;
            $staffCampaigns[$sid][] = $src;
        }

        // ── STEP 4: Assign each lead ─────────────────────────────
        $MAX_LIMIT = 20;

        foreach ($unassignedLeads as $lead) {
            $source_id = (int) ($lead['source'] ?? 0);

            if (!$source_id || empty($campaignRules[$source_id])) {
                continue;
            }

            $ruleStaffIds = $campaignRules[$source_id];
            $eligibleIds = array_values(array_intersect($ruleStaffIds, $staffIds));

            if (empty($eligibleIds)) {
                continue;
            }

            $eligibleStaff = [];
            foreach ($eligibleIds as $sid) {
                if (isset($leadCountMap[$sid]) && $leadCountMap[$sid] < $MAX_LIMIT) {
                    $eligibleStaff[$sid] = $leadCountMap[$sid];
                }
            }

            if (empty($eligibleStaff)) {
                continue;
            }

            $minLeadCount = min($eligibleStaff);
            $minStaff = array_keys($eligibleStaff, $minLeadCount);
            $assignedStaffId = $minStaff[array_rand($minStaff)];

            $CI->db->where('id', $lead['id']);
            $CI->db->update(db_prefix().'leads', ['assigned' => $assignedStaffId]);

            ++$leadCountMap[$assignedStaffId];

            $CI->db->insert(db_prefix().'lead_activity_log', [
                'date' => date('Y-m-d H:i:s'),
                'description' => 'Auto-assigned lead (campaign rule)',
                'leadid' => $lead['id'],
                'staffid' => $assignedStaffId,
                'full_name' => $staffNameMap[$assignedStaffId] ?? 'Unknown',
                'custom_activity' => 0,
            ]);
        }
    }
}
