<?php

defined('BASEPATH') or exit('No direct script access allowed');

class MetaWebhook extends CI_Controller
{
    private $log_file;
    private $verify_token;
    private $system_token;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();

        $this->verify_token = META_VERIFY_TOKEN;
        $this->system_token = META_SYSTEM_USER_TOKEN;

        $this->log_file = APPPATH.'logs/meta_webhook.log';

        if (!is_dir(APPPATH.'logs')) {
            mkdir(APPPATH.'logs', 0777, true);
        }
    }

    /* ----------------------------------------------------
     * WEBHOOK ENTRY POINT
     * --------------------------------------------------*/
    public function receive()
    {
        // Webhook verification
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (
                isset($_GET['hub_mode'], $_GET['hub_verify_token'])
                && $_GET['hub_mode'] === 'subscribe'
                && $_GET['hub_verify_token'] === $this->verify_token
            ) {
                echo $_GET['hub_challenge'];
                exit;
            }
            http_response_code(403);
            exit;
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        $this->log('RAW_WEBHOOK', $payload);

        $leadgen_id = $payload['entry'][0]['changes'][0]['value']['leadgen_id'] ?? null;

        if (!$leadgen_id) {
            $this->log('INVALID_PAYLOAD', $payload);

            return $this->respond(['error' => 'Invalid payload'], 400);
        }

        $lead = $this->fetchLead($leadgen_id);

        if (isset($lead['error'])) {
            $this->log('LEAD_FETCH_FAILED', $lead);

            return $this->respond($lead, 500);
        }

        $saved = $this->saveLead($lead);

        return $this->respond([
            'success' => $saved,
            'lead_id' => $leadgen_id,
        ]);
    }

    /* ----------------------------------------------------
     * FETCH LEAD
     * --------------------------------------------------*/
    private function fetchLead($leadgen_id)
    {
        $url = "https://graph.facebook.com/v21.0/{$leadgen_id}"
            .'?fields=field_data,ad_id,adset_id,campaign_id,form_id,created_time'
            .'&access_token='.urlencode($this->system_token);

        return $this->curlGetJson($url);
    }

    /* ----------------------------------------------------
     * SAVE LEAD
     * --------------------------------------------------*/
    private function saveLead($lead)
    {
        // Duplicate protection
        $exists = $this->db->get_where('tblleads', [
            'meta_lead_id' => $lead['id'],
        ])->row();

        if ($exists) {
            $this->log('DUPLICATE_LEAD', $lead['id']);

            return false;
        }

        // Normalize fields
        $fields = [];
        foreach ($lead['field_data'] ?? [] as $f) {
            $fields[strtolower(trim($f['name']))] = $f['values'][0] ?? '';
        }

        // Fetch Meta names
        $campaign_name = $this->fetchName($lead['campaign_id'] ?? null);
        $ad_name = $this->fetchName($lead['ad_id'] ?? null);
        $form_name = $this->fetchName($lead['form_id'] ?? null);

        $insert = [
            'meta_lead_id' => $lead['id'],

            // REQUIRED FIELDS
            'name' => $fields['full_name'] ?? 'Unknown',
            'email' => $fields['email'] ?? '',
            'phonenumber' => $fields['phone_number'] ?? '',
            'company' => $fields['business_/_brand_name_'] ?? '',
            'city' => $fields['city'] ?? '',

            // CUSTOM QUESTIONS
            'title' => $fields['what_type_of_real_estate_business_do_you_run?'] ?? '',
            'q1' => $fields['_do_you_work_in_real_estate?'] ?? '',

            // META INFO
            'campaign_id' => $lead['campaign_id'] ?? '',
            'campaign_name' => $campaign_name,
            'ad_id' => $lead['ad_id'] ?? '',
            'ad_name' => $ad_name,
            'form_id' => $lead['form_id'] ?? '',
            'form_name' => $form_name,

            // RAW PAYLOAD
            'raw_payload' => json_encode($lead),

            // SYSTEM
            'status' => 4,
            'source' => 3,
            'addedfrom' => 1,
            'dateadded' => date('Y-m-d H:i:s'),
            'description' => 'Imported from Meta Lead Ads',
            'platform_text' => 'facebook',
        ];

        $this->log('INSERT_PAYLOAD', $insert);

        $ok = $this->db->insert('tblleads', $insert);

        if (!$ok) {
            $this->log('DB_ERROR', $this->db->error());

            return false;
        }

        return true;
    }

    /* ----------------------------------------------------
     * FETCH NAME (Campaign / Ad / Form)
     * --------------------------------------------------*/
    private function fetchName($id)
    {
        if (!$id) {
            return '';
        }

        $url = "https://graph.facebook.com/v21.0/{$id}"
            .'?fields=name&access_token='.urlencode($this->system_token);

        $res = $this->curlGetJson($url);

        return $res['name'] ?? '';
    }

    /* ----------------------------------------------------
     * CURL
     * --------------------------------------------------*/
    private function curlGetJson($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $resp = curl_exec($ch);

        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);

            return ['error' => $err];
        }

        curl_close($ch);

        return json_decode($resp, true);
    }

    /* ----------------------------------------------------
     * LOGGING
     * --------------------------------------------------*/
    private function log($title, $data)
    {
        $entry = "== {$title} | ".date('Y-m-d H:i:s')." ==\n";
        $entry .= print_r($data, true)."\n\n";
        file_put_contents($this->log_file, $entry, FILE_APPEND);
    }

    /* ----------------------------------------------------
     * RESPONSE
     * --------------------------------------------------*/
    private function respond($data, $status = 200)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
