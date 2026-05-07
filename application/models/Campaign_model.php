<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Campaign_model extends CI_Model
{
    public function get_campaigns_with_stats()
    {
        // All campaigns from sources table
        $campaigns = $this->db
            ->select('id, name, campaign_id, is_on')
            ->where('campaign_id IS NOT NULL', null, false)
            ->where('campaign_id !=', '')
            ->order_by('id', 'ASC')
            ->get(db_prefix().'leads_sources')
            ->result_array();

        if (empty($campaigns)) {
            return [];
        }

        $source_ids = array_column($campaigns, 'id');

        // Total leads per source
        $total_leads = $this->db
            ->select('source, COUNT(id) as total')
            ->where_in('source', $source_ids)
            ->group_by('source')
            ->get(db_prefix().'leads')
            ->result_array();

        $total_map = array_column($total_leads, 'total', 'source');

        // Leads assigned today per source
        $today_leads = $this->db
            ->select('source, COUNT(id) as today')
            ->where_in('source', $source_ids)
            ->where('DATE(dateadded)', date('Y-m-d'))
            ->where('assigned !=', 0)
            ->group_by('source')
            ->get(db_prefix().'leads')
            ->result_array();

        $today_map = array_column($today_leads, 'today', 'source');

        // Assigned staff per source
        $assignments = $this->db
            ->select('source_id, staff_id')
            ->where('is_active', 1)
            ->where_in('source_id', $source_ids)
            ->get('tblcampaign_staff_assignments')
            ->result_array();

        $assigned_map = [];
        foreach ($assignments as $a) {
            $assigned_map[$a['source_id']][] = (int) $a['staff_id'];
        }

        // Merge stats into campaigns
        foreach ($campaigns as &$c) {
            $c['total_leads'] = (int) ($total_map[$c['id']] ?? 0);
            $c['leads_today'] = (int) ($today_map[$c['id']] ?? 0);
            $c['assigned_staff'] = $assigned_map[$c['id']] ?? [];
            $c['assigned_count'] = count($c['assigned_staff']);
        }

        return $campaigns;
    }

    public function get_eligible_staff()
    {
        $this->db->select('s.staffid, s.firstname, s.lastname, s.profile_image');
        $this->db->from(db_prefix().'staff AS s');
        $this->db->join('tbltaskstimers AS t', 't.staff_id = s.staffid', 'inner');
        $this->db->where('s.active', 1);
        $this->db->where('s.admin !=', 1);
        $this->db->where_in('s.role', [3, 14]);
        $this->db->where('s.auto_assign_leads', '1');
        $this->db->where('t.status', 'CHECKED_IN');
        $this->db->where('t.check_in_time IS NOT NULL', null, false);
        $this->db->where('t.check_out_time IS NULL', null, false);
        $this->db->group_by('s.staffid');
        $this->db->order_by('s.firstname', 'ASC');

        return $this->db->get()->result_array();
    }

    public function save_assignment($source_id, $staff_ids)
    {
        // Delete existing
        $this->db->where('source_id', $source_id)
                 ->delete('tblcampaign_staff_assignments');

        if (empty($staff_ids)) {
            return true;
        }

        // Insert new
        foreach ($staff_ids as $staff_id) {
            $this->db->insert('tblcampaign_staff_assignments', [
                'source_id' => $source_id,
                'staff_id' => $staff_id,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }
}
