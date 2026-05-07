<?php

defined('BASEPATH') or exit('No direct script access allowed');

class BusinessbayLandingLeads extends CI_Controller
{
    private $api_url;
    private $api_key;
    private $start_from;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();

        $this->api_url = BUSINESSBAY_CRM_API_URL;
        $this->api_key = BUSINESSBAY_CRM_API_KEY;
        $this->start_from = BUSINESSBAY_CRM_LEAD_DATE;
    }

    public function run()
    {
        date_default_timezone_set('Asia/Kolkata');

        // ✅ Rolling window (last 2 days)
        $startDate = date('Y-m-d', strtotime('-2 days'));
        $endDate = date('Y-m-d');

        $page = 1;
        $pageSize = 1000; // ✅ realistic pagination
        $inserted = 0;

        do {
            $url = $this->api_url.'?'.http_build_query([
                'page' => $page,
                'pageSize' => $pageSize,
                'dateFilter' => 'custom',
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['x-api-key: '.$this->api_key],
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (empty($response['result'])) {
                break;
            }

            $batchInsert = [];

            foreach ($response['result'] as $lead) {
                if (empty($lead['lead_id'])) {
                    continue;
                }

                $dateObj = new DateTime($lead['submitted_at'], new DateTimeZone('UTC'));
                $dateObj->setTimezone(new DateTimeZone('Asia/Kolkata'));

                $leadDate = $dateObj->format('Y-m-d');
                $leadDateTime = $dateObj->format('Y-m-d H:i:s');

                // ❌ removed old start_from restriction (no data loss)

                // ── Check 1: Duplicate by meta_lead_id ──
                $exists = $this->db->select('id')
                    ->where('meta_lead_id', $lead['lead_id'])
                    ->get('tblleads')
                    ->row();

                if ($exists) {
                    continue;
                }

                // ── Check 2: Duplicate by email + phone ──
                $email = trim($lead['email'] ?? '');
                $phone = trim($lead['contact'] ?? '');

                if (!empty($email) && !empty($phone)) {
                    $this->db->reset_query();
                    $dupExists = $this->db
                        ->where('email', $email)
                        ->where('phonenumber', $phone)
                        ->get('tblleads')
                        ->row();

                    if ($dupExists) {
                        continue;
                    }
                } elseif (!empty($phone)) {
                    $this->db->reset_query();
                    $dupExists = $this->db
                        ->where('phonenumber', $phone)
                        ->get('tblleads')
                        ->row();

                    if ($dupExists) {
                        continue;
                    }
                } elseif (!empty($email)) {
                    $this->db->reset_query();
                    $dupExists = $this->db
                        ->where('email', $email)
                        ->get('tblleads')
                        ->row();

                    if ($dupExists) {
                        continue;
                    }
                }

                $sourceId = $this->upsertSource($lead['enquiry_type'] ?? null);

                $batchInsert[] = [
                    'meta_lead_id' => $lead['lead_id'],
                    'record_type' => 'lead',
                    'name' => trim($lead['name']) ?? 'Unknown',
                    'email' => $lead['email'] ?? '',
                    'phonenumber' => $lead['contact'] ?? '',
                    'city' => trim($lead['city']) ?? '',
                    'country' => 0,
                    'title' => $lead['enquiry_type'] ?? '',
                    'description' => $lead['message'] ?? '',
                    'source' => $sourceId,
                    'status' => 4,
                    'addedfrom' => 1,
                    'assigned' => 0,
                    'from_form_id' => 0,
                    'platform_text' => 'external_api',
                    'platform' => 'V2',
                    'raw_payload' => json_encode($lead),
                    'dateadded' => $leadDateTime,
                ];

                // ✅ Batch insert
                if (count($batchInsert) >= 50) {
                    $inserted += $this->insertIgnoreBatch('tblleads', $batchInsert);
                    $batchInsert = [];
                }
            }

            // Insert remaining
            if (!empty($batchInsert)) {
                $inserted += $this->insertIgnoreBatch('tblleads', $batchInsert);
            }

            ++$page;
        } while (count($response['result']) == $pageSize);

        // ✅ Assign leads AFTER all inserts
        $this->load->library('LeadAssigner');
        $this->leadassigner->autoAssignLeads();

        echo "Inserted: {$inserted}";
    }

    private function upsertSource($enquiryType)
    {
        if (empty($enquiryType)) {
            return 3;
        }

        $existing = $this->db
            ->where('name', $enquiryType)
            ->get('tblleads_sources')
            ->row();

        if ($existing) {
            return $existing->id;
        }

        $this->db->insert('tblleads_sources', [
            'name' => $enquiryType,
        ]);

        $id = $this->db->insert_id();

        $this->db->set('campaign_id', $id)
                 ->where('id', $id)
                 ->update('tblleads_sources');

        return $this->db->insert_id();
    }

    private function insertIgnoreBatch($table, $batchInsert)
    {
        if (empty($batchInsert)) {
            return 0;
        }

        $columns = implode(',', array_keys($batchInsert[0]));

        $values = implode(',', array_map(function ($row) {
            return '('.implode(',', array_map([$this->db, 'escape'], array_values($row))).')';
        }, $batchInsert));

        $sql = "INSERT IGNORE INTO {$table} ({$columns}) VALUES {$values}";

        $this->db->query($sql);

        return $this->db->affected_rows();
    }
}
